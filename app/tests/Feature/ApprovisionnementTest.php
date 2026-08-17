<?php

namespace Tests\Feature;

use App\Enums\PorteeCodeAcces;
use App\Enums\TypeProduit;
use App\Enums\UserRole;
use App\Models\Boutique;
use App\Models\CodeAcces;
use App\Models\Lot;
use App\Models\Produit;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ApprovisionnementTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array<string, mixed>
     */
    private function donnees(Boutique $boutique, Produit $produit, array $remplacements = []): array
    {
        return array_merge([
            'boutique_id' => $boutique->id,
            'produit_id' => $produit->id,
            'quantite' => 25,
            'numero_lot' => 'LOT-APPRO',
            'date_peremption' => now()->addYear()->toDateString(),
        ], $remplacements);
    }

    public function test_a_patron_fills_the_stock_of_a_boutique(): void
    {
        $tenant = Tenant::factory()->create();
        $boutique = Boutique::factory()->create(['tenant_id' => $tenant->id]);
        $patron = User::factory()->create(['tenant_id' => $tenant->id, 'role' => UserRole::Patron]);
        $produit = Produit::factory()->create(['tenant_id' => $tenant->id, 'nom' => 'Urée 46%']);

        $this->actingAs($patron)->post('/stock/approvisionnements', $this->donnees($boutique, $produit))
            ->assertRedirect(route('stock.index'));

        $this->assertDatabaseHas('lots', ['produit_id' => $produit->id, 'numero_lot' => 'LOT-APPRO']);
        $this->assertDatabaseHas('stock_boutiques', [
            'boutique_id' => $boutique->id,
            'produit_id' => $produit->id,
            'quantite' => 25,
        ]);
        $this->assertDatabaseHas('journal_activites', ['action' => 'stock.approvisionne']);
    }

    public function test_restocking_the_same_batch_adds_up_instead_of_duplicating_it(): void
    {
        $tenant = Tenant::factory()->create();
        $boutique = Boutique::factory()->create(['tenant_id' => $tenant->id]);
        $patron = User::factory()->create(['tenant_id' => $tenant->id, 'role' => UserRole::Patron]);
        $produit = Produit::factory()->create(['tenant_id' => $tenant->id]);

        $this->actingAs($patron)->post('/stock/approvisionnements', $this->donnees($boutique, $produit))->assertRedirect();
        $this->actingAs($patron)->post('/stock/approvisionnements', $this->donnees($boutique, $produit, ['quantite' => 15]))->assertRedirect();

        $this->assertSame(1, Lot::where('produit_id', $produit->id)->count());
        $this->assertDatabaseHas('stock_boutiques', [
            'boutique_id' => $boutique->id,
            'produit_id' => $produit->id,
            'quantite' => 40,
        ]);
    }

    public function test_an_existing_batch_cannot_be_given_a_different_expiry_date(): void
    {
        $tenant = Tenant::factory()->create();
        $boutique = Boutique::factory()->create(['tenant_id' => $tenant->id]);
        $patron = User::factory()->create(['tenant_id' => $tenant->id, 'role' => UserRole::Patron]);
        $produit = Produit::factory()->create(['tenant_id' => $tenant->id]);

        $this->actingAs($patron)->post('/stock/approvisionnements', $this->donnees($boutique, $produit))->assertRedirect();

        // Même numéro de lot, autre péremption : refusé, sinon la traçabilité
        // de ce qui est déjà en rayon sous ce numéro serait faussée.
        $this->actingAs($patron)->from('/stock/approvisionnements/creer')
            ->post('/stock/approvisionnements', $this->donnees($boutique, $produit, [
                'date_peremption' => now()->addMonths(2)->toDateString(),
            ]))->assertSessionHasErrors('numero_lot');

        $this->assertDatabaseHas('stock_boutiques', [
            'boutique_id' => $boutique->id,
            'produit_id' => $produit->id,
            'quantite' => 25,
        ]);
    }

    public function test_the_expiry_date_is_required_and_must_be_in_the_future(): void
    {
        $tenant = Tenant::factory()->create();
        $boutique = Boutique::factory()->create(['tenant_id' => $tenant->id]);
        $patron = User::factory()->create(['tenant_id' => $tenant->id, 'role' => UserRole::Patron]);

        // Le type est fixé, pas tiré par la fabrique : depuis que la date n'est
        // exigée que sur les produits à traçabilité obligatoire, un produit au
        // type aléatoire ferait passer ce test une fois sur deux.
        $produit = Produit::factory()->create([
            'tenant_id' => $tenant->id,
            'type' => TypeProduit::ProduitPhytosanitaire,
        ]);

        $donnees = $this->donnees($boutique, $produit);
        unset($donnees['date_peremption']);

        $this->actingAs($patron)->from('/stock/approvisionnements/creer')
            ->post('/stock/approvisionnements', $donnees)
            ->assertSessionHasErrors('date_peremption');

        $this->actingAs($patron)->from('/stock/approvisionnements/creer')
            ->post('/stock/approvisionnements', $this->donnees($boutique, $produit, [
                'date_peremption' => now()->subDay()->toDateString(),
            ]))->assertSessionHasErrors('date_peremption');

        $this->assertDatabaseCount('stock_boutiques', 0);
    }

    public function test_restocking_a_product_without_mandatory_traceability_needs_no_date(): void
    {
        $tenant = Tenant::factory()->create();
        $boutique = Boutique::factory()->create(['tenant_id' => $tenant->id]);
        $patron = User::factory()->create(['tenant_id' => $tenant->id, 'role' => UserRole::Patron]);
        $produit = Produit::factory()->create([
            'tenant_id' => $tenant->id,
            'type' => TypeProduit::IntrantAgricole,
        ]);

        $donnees = $this->donnees($boutique, $produit);
        unset($donnees['date_peremption']);

        $this->actingAs($patron)->from('/stock/approvisionnements/creer')
            ->post('/stock/approvisionnements', $donnees)
            ->assertSessionHasNoErrors();

        $this->assertDatabaseCount('stock_boutiques', 1);
        $this->assertNull(Lot::where('produit_id', $produit->id)->firstOrFail()->date_peremption);
    }

    public function test_a_gerant_needs_a_code_and_can_only_fill_his_own_boutique(): void
    {
        $tenant = Tenant::factory()->create();
        $boutique = Boutique::factory()->create(['tenant_id' => $tenant->id]);
        $autreBoutique = Boutique::factory()->create(['tenant_id' => $tenant->id]);
        $patron = User::factory()->create(['tenant_id' => $tenant->id, 'role' => UserRole::Patron]);
        $gerant = User::factory()->create([
            'tenant_id' => $tenant->id, 'role' => UserRole::Gerant, 'boutique_id' => $boutique->id,
        ]);
        $produit = Produit::factory()->create(['tenant_id' => $tenant->id]);

        // Sans code : refusé.
        $this->actingAs($gerant)->post('/stock/approvisionnements', $this->donnees($boutique, $produit))
            ->assertForbidden();

        $code = CodeAcces::create([
            'tenant_id' => $tenant->id,
            'boutique_id' => $boutique->id,
            'portee' => PorteeCodeAcces::Approvisionnement,
            'genere_par_user_id' => $patron->id,
            'destinataire_user_id' => $gerant->id,
            'expire_le' => now()->addDay(),
        ]);

        $this->actingAs($gerant)->post('/acces-privilegie', ['code' => $code->code])->assertRedirect();

        // Avec code : accepté pour sa boutique…
        $this->actingAs($gerant)->post('/stock/approvisionnements', $this->donnees($boutique, $produit))
            ->assertRedirect(route('stock.index'));

        // …mais refusé pour une autre.
        $this->actingAs($gerant)->from('/stock/approvisionnements/creer')
            ->post('/stock/approvisionnements', $this->donnees($autreBoutique, $produit, ['numero_lot' => 'LOT-X']))
            ->assertSessionHasErrors('boutique_id');

        $this->assertDatabaseMissing('stock_boutiques', ['boutique_id' => $autreBoutique->id]);
        $this->assertDatabaseHas('journal_activites', [
            'action' => 'stock.approvisionne',
            'code_acces_id' => $code->id,
        ]);
    }

    public function test_a_gerant_cannot_correct_the_stock_afterwards(): void
    {
        $tenant = Tenant::factory()->create();
        $boutique = Boutique::factory()->create(['tenant_id' => $tenant->id]);
        $patron = User::factory()->create(['tenant_id' => $tenant->id, 'role' => UserRole::Patron]);
        $gerant = User::factory()->create([
            'tenant_id' => $tenant->id, 'role' => UserRole::Gerant, 'boutique_id' => $boutique->id,
        ]);
        $produit = Produit::factory()->create(['tenant_id' => $tenant->id]);

        $code = CodeAcces::create([
            'tenant_id' => $tenant->id,
            'boutique_id' => $boutique->id,
            'portee' => PorteeCodeAcces::Complet,
            'genere_par_user_id' => $patron->id,
            'destinataire_user_id' => $gerant->id,
            'expire_le' => now()->addDay(),
        ]);

        $this->actingAs($gerant)->post('/acces-privilegie', ['code' => $code->code])->assertRedirect();
        $this->actingAs($gerant)->post('/stock/approvisionnements', $this->donnees($boutique, $produit))->assertRedirect();

        // L'inventaire est le seul moyen d'ajuster une quantité : il reste
        // réservé au patron, même avec un code d'accès ouvert.
        $this->actingAs($gerant)->get('/stock/inventaires/create')->assertForbidden();
        $this->actingAs($gerant)->post('/stock/inventaires', [
            'boutique_id' => $boutique->id,
            'lignes' => [],
        ])->assertForbidden();
    }

    public function test_a_vendeur_cannot_fill_the_stock(): void
    {
        $tenant = Tenant::factory()->create();
        $boutique = Boutique::factory()->create(['tenant_id' => $tenant->id]);
        $vendeur = User::factory()->create([
            'tenant_id' => $tenant->id, 'role' => UserRole::Vendeur, 'boutique_id' => $boutique->id,
        ]);
        $produit = Produit::factory()->create(['tenant_id' => $tenant->id]);

        $this->actingAs($vendeur)->get('/stock/approvisionnements/creer')->assertForbidden();
        $this->actingAs($vendeur)->post('/stock/approvisionnements', $this->donnees($boutique, $produit))->assertForbidden();
    }

    public function test_a_product_from_another_tenant_cannot_be_restocked(): void
    {
        $tenant = Tenant::factory()->create();
        $boutique = Boutique::factory()->create(['tenant_id' => $tenant->id]);
        $patron = User::factory()->create(['tenant_id' => $tenant->id, 'role' => UserRole::Patron]);
        $produitEtranger = Produit::factory()->create(['tenant_id' => Tenant::factory()->create()->id]);

        $this->actingAs($patron)->from('/stock/approvisionnements/creer')
            ->post('/stock/approvisionnements', $this->donnees($boutique, $produitEtranger))
            ->assertSessionHasErrors('produit_id');
    }
}
