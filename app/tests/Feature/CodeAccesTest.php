<?php

namespace Tests\Feature;

use App\Enums\PorteeCodeAcces;
use App\Enums\TypeProduit;
use App\Enums\UniteMesure;
use App\Enums\UserRole;
use App\Models\Boutique;
use App\Models\CodeAcces;
use App\Models\Produit;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CodeAccesTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array{0: Tenant, 1: Boutique, 2: User, 3: User}
     */
    private function equipe(): array
    {
        $tenant = Tenant::factory()->create();
        $boutique = Boutique::factory()->create(['tenant_id' => $tenant->id]);
        $patron = User::factory()->create(['tenant_id' => $tenant->id, 'role' => UserRole::Patron]);
        $gerant = User::factory()->create([
            'tenant_id' => $tenant->id, 'role' => UserRole::Gerant, 'boutique_id' => $boutique->id,
        ]);

        return [$tenant, $boutique, $patron, $gerant];
    }

    private function code(Tenant $tenant, Boutique $boutique, User $patron, User $gerant, array $remplacements = []): CodeAcces
    {
        return CodeAcces::create(array_merge([
            'tenant_id' => $tenant->id,
            'boutique_id' => $boutique->id,
            'portee' => PorteeCodeAcces::Complet,
            'genere_par_user_id' => $patron->id,
            'destinataire_user_id' => $gerant->id,
            'expire_le' => now()->addDay(),
        ], $remplacements));
    }

    /**
     * @return array<string, mixed>
     */
    private function donneesProduit(Boutique $boutique): array
    {
        return [
            'nom' => 'Urée 46%',
            'type' => TypeProduit::IntrantAgricole->value,
            'unite_mesure' => UniteMesure::Sac->value,
            'prix_achat' => 10000,
            'prix_vente' => 13000,
            'stock_min' => 5,
            'stock_max' => 100,
            'boutique_id' => $boutique->id,
            'quantite_initiale' => 20,
            'numero_lot' => 'LOT-A',
            'date_peremption' => now()->addYear()->toDateString(),
        ];
    }

    public function test_a_patron_delivers_a_code_to_one_of_his_gerants(): void
    {
        [, $boutique, $patron, $gerant] = $this->equipe();

        $this->actingAs($patron)->post('/codes-acces', [
            'destinataire_user_id' => $gerant->id,
            'portee' => PorteeCodeAcces::Catalogue->value,
            'duree_heures' => 48,
            'motif' => 'Mise en place du stock initial',
        ])->assertRedirect();

        $code = CodeAcces::withoutGlobalScopes()->firstOrFail();
        $this->assertSame($gerant->id, $code->destinataire_user_id);
        $this->assertSame($boutique->id, $code->boutique_id);
        $this->assertSame(PorteeCodeAcces::Catalogue, $code->portee);
        $this->assertMatchesRegularExpression('/^ACC-[A-Z0-9]{4}-[A-Z0-9]{4}$/', $code->code);
        $this->assertTrue($code->estValide());
    }

    public function test_a_gerant_cannot_deliver_codes(): void
    {
        [, , , $gerant] = $this->equipe();

        $this->actingAs($gerant)->get('/codes-acces')->assertForbidden();
        $this->actingAs($gerant)->post('/codes-acces', [
            'destinataire_user_id' => $gerant->id,
            'portee' => PorteeCodeAcces::Complet->value,
            'duree_heures' => 24,
        ])->assertForbidden();
    }

    public function test_a_gerant_cannot_create_a_product_without_an_open_code(): void
    {
        [, $boutique, , $gerant] = $this->equipe();

        $this->actingAs($gerant)->post('/produits', $this->donneesProduit($boutique))->assertForbidden();
        $this->assertDatabaseMissing('produits', ['nom' => 'Urée 46%']);
    }

    public function test_a_gerant_creates_a_product_once_the_code_is_entered(): void
    {
        [$tenant, $boutique, $patron, $gerant] = $this->equipe();
        $code = $this->code($tenant, $boutique, $patron, $gerant);

        $this->actingAs($gerant)->post('/acces-privilegie', ['code' => $code->code])
            ->assertRedirect(route('dashboard'))
            ->assertSessionHasNoErrors();

        $this->actingAs($gerant)->post('/produits', $this->donneesProduit($boutique))->assertRedirect();

        $this->assertDatabaseHas('produits', ['nom' => 'Urée 46%', 'tenant_id' => $tenant->id]);

        // Le code porte désormais la trace de l'opération.
        $this->assertDatabaseHas('journal_activites', [
            'action' => 'produit.cree',
            'code_acces_id' => $code->id,
        ]);
        $this->assertNotNull($code->fresh()->active_le);
    }

    public function test_a_code_only_works_for_the_gerant_it_was_issued_to(): void
    {
        [$tenant, $boutique, $patron, $gerant] = $this->equipe();
        $autreGerant = User::factory()->create([
            'tenant_id' => $tenant->id, 'role' => UserRole::Gerant, 'boutique_id' => $boutique->id,
        ]);
        $code = $this->code($tenant, $boutique, $patron, $gerant);

        $this->actingAs($autreGerant)->from('/acces-privilegie')
            ->post('/acces-privilegie', ['code' => $code->code])
            ->assertSessionHasErrors('code');

        $this->actingAs($autreGerant)->post('/produits', $this->donneesProduit($boutique))->assertForbidden();
    }

    public function test_an_expired_code_is_refused(): void
    {
        [$tenant, $boutique, $patron, $gerant] = $this->equipe();
        $code = $this->code($tenant, $boutique, $patron, $gerant, ['expire_le' => now()->subMinute()]);

        $this->actingAs($gerant)->from('/acces-privilegie')
            ->post('/acces-privilegie', ['code' => $code->code])
            ->assertSessionHasErrors('code');
    }

    public function test_a_revoked_code_closes_the_access_immediately(): void
    {
        [$tenant, $boutique, $patron, $gerant] = $this->equipe();
        $code = $this->code($tenant, $boutique, $patron, $gerant);

        $this->actingAs($gerant)->post('/acces-privilegie', ['code' => $code->code])->assertRedirect();
        $this->actingAs($gerant)->post('/produits', $this->donneesProduit($boutique))->assertRedirect();

        $this->actingAs($patron)->post("/codes-acces/{$code->id}/revoquer")->assertRedirect();
        $this->assertFalse($code->fresh()->estValide());

        // La session du gérant est déjà ouverte : c'est la revérification à
        // chaque requête qui doit lui couper l'accès.
        $this->actingAs($gerant)->post('/produits', array_merge($this->donneesProduit($boutique), [
            'nom' => 'Produit après révocation',
            'numero_lot' => 'LOT-B',
        ]))->assertForbidden();

        $this->assertDatabaseMissing('produits', ['nom' => 'Produit après révocation']);
    }

    public function test_a_catalogue_code_does_not_cover_restocking(): void
    {
        [$tenant, $boutique, $patron, $gerant] = $this->equipe();
        $code = $this->code($tenant, $boutique, $patron, $gerant, ['portee' => PorteeCodeAcces::Catalogue]);
        $produit = Produit::factory()->create(['tenant_id' => $tenant->id]);

        $this->actingAs($gerant)->post('/acces-privilegie', ['code' => $code->code])->assertRedirect();

        $this->actingAs($gerant)->post('/stock/approvisionnements', [
            'boutique_id' => $boutique->id,
            'produit_id' => $produit->id,
            'quantite' => 10,
            'numero_lot' => 'LOT-C',
            'date_peremption' => now()->addYear()->toDateString(),
        ])->assertForbidden();
    }

    public function test_the_gerant_can_close_the_access_himself(): void
    {
        [$tenant, $boutique, $patron, $gerant] = $this->equipe();
        $code = $this->code($tenant, $boutique, $patron, $gerant);

        $this->actingAs($gerant)->post('/acces-privilegie', ['code' => $code->code])->assertRedirect();
        $this->actingAs($gerant)->delete('/acces-privilegie')->assertRedirect();

        $this->actingAs($gerant)->post('/produits', $this->donneesProduit($boutique))->assertForbidden();
    }

    public function test_a_code_from_another_tenant_is_refused(): void
    {
        [, $boutique, , $gerant] = $this->equipe();

        $autreTenant = Tenant::factory()->create();
        $autreBoutique = Boutique::factory()->create(['tenant_id' => $autreTenant->id]);
        $autrePatron = User::factory()->create(['tenant_id' => $autreTenant->id, 'role' => UserRole::Patron]);
        $autreGerant = User::factory()->create([
            'tenant_id' => $autreTenant->id, 'role' => UserRole::Gerant, 'boutique_id' => $autreBoutique->id,
        ]);
        $codeEtranger = $this->code($autreTenant, $autreBoutique, $autrePatron, $autreGerant);

        $this->actingAs($gerant)->from('/acces-privilegie')
            ->post('/acces-privilegie', ['code' => $codeEtranger->code])
            ->assertSessionHasErrors('code');

        $this->actingAs($gerant)->post('/produits', $this->donneesProduit($boutique))->assertForbidden();
    }

    public function test_the_patron_reads_the_operations_carried_out_under_a_code(): void
    {
        [$tenant, $boutique, $patron, $gerant] = $this->equipe();
        $code = $this->code($tenant, $boutique, $patron, $gerant);

        $this->actingAs($gerant)->post('/acces-privilegie', ['code' => $code->code])->assertRedirect();
        $this->actingAs($gerant)->post('/produits', $this->donneesProduit($boutique))->assertRedirect();

        $this->actingAs($patron)->get("/codes-acces/{$code->id}")
            ->assertOk()
            ->assertSee($code->code)
            ->assertSee($gerant->name)
            ->assertSee('Urée 46%');
    }

    public function test_a_patron_cannot_read_a_code_of_another_tenant(): void
    {
        [, , $patron] = $this->equipe();

        $autreTenant = Tenant::factory()->create();
        $autreBoutique = Boutique::factory()->create(['tenant_id' => $autreTenant->id]);
        $autrePatron = User::factory()->create(['tenant_id' => $autreTenant->id, 'role' => UserRole::Patron]);
        $autreGerant = User::factory()->create([
            'tenant_id' => $autreTenant->id, 'role' => UserRole::Gerant, 'boutique_id' => $autreBoutique->id,
        ]);
        $codeEtranger = $this->code($autreTenant, $autreBoutique, $autrePatron, $autreGerant);

        // 404 et non 403 : la portée de tenant masque le code dès la
        // résolution de la route, si bien que la réponse ne révèle même pas
        // qu'un code portant cet identifiant existe.
        $this->actingAs($patron)->get("/codes-acces/{$codeEtranger->id}")->assertNotFound();
        $this->actingAs($patron)->post("/codes-acces/{$codeEtranger->id}/revoquer")->assertNotFound();
        $this->assertTrue($codeEtranger->fresh()->estValide());
    }

    public function test_a_code_cannot_be_issued_to_a_gerant_without_a_boutique(): void
    {
        [$tenant, , $patron] = $this->equipe();
        $gerantSansBoutique = User::factory()->create([
            'tenant_id' => $tenant->id, 'role' => UserRole::Gerant, 'boutique_id' => null,
        ]);

        $this->actingAs($patron)->from('/codes-acces/creer')->post('/codes-acces', [
            'destinataire_user_id' => $gerantSansBoutique->id,
            'portee' => PorteeCodeAcces::Complet->value,
            'duree_heures' => 24,
        ])->assertSessionHasErrors('destinataire_user_id');
    }

    public function test_a_vendeur_never_gains_access_even_with_a_code(): void
    {
        [$tenant, $boutique, $patron, $gerant] = $this->equipe();
        $vendeur = User::factory()->create([
            'tenant_id' => $tenant->id, 'role' => UserRole::Vendeur, 'boutique_id' => $boutique->id,
        ]);
        $code = $this->code($tenant, $boutique, $patron, $gerant);

        $this->actingAs($vendeur)->from('/acces-privilegie')
            ->post('/acces-privilegie', ['code' => $code->code])
            ->assertSessionHasErrors('code');

        $this->actingAs($vendeur)->post('/produits', $this->donneesProduit($boutique))->assertForbidden();
    }
}
