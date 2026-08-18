<?php

namespace Tests\Feature;

use App\Enums\TypeProduit;
use App\Enums\UniteMesure;
use App\Enums\UserRole;
use App\Models\Boutique;
use App\Models\Categorie;
use App\Models\Lot;
use App\Models\Produit;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CatalogueProduitsTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Jeu de données complet pour créer un produit : depuis que le stock
     * initial est saisi en même temps que la fiche, la boutique, le lot et la
     * date de péremption font partie du formulaire.
     *
     * @param  array<string, mixed>  $remplacements
     * @return array<string, mixed>
     */
    private function donneesProduit(Boutique $boutique, array $remplacements = []): array
    {
        return array_merge([
            'nom' => 'Engrais NPK 15-15-15',
            'type' => TypeProduit::IntrantAgricole->value,
            'unite_mesure' => UniteMesure::Sac->value,
            'prix_achat' => 12000,
            'prix_vente' => 15000,
            'stock_min' => 10,
            'stock_max' => 200,
            'boutique_id' => $boutique->id,
            'quantite_initiale' => 40,
            'numero_lot' => 'LOT-2026-01',
            'date_peremption' => now()->addYear()->toDateString(),
        ], $remplacements);
    }

    public function test_a_patron_can_create_a_product_with_its_initial_stock(): void
    {
        $tenant = Tenant::factory()->create();
        $patron = User::factory()->create(['tenant_id' => $tenant->id, 'role' => UserRole::Patron]);
        $boutique = Boutique::factory()->create(['tenant_id' => $tenant->id]);
        $categorie = Categorie::factory()->create(['tenant_id' => $tenant->id]);

        $this->actingAs($patron)->post('/produits', $this->donneesProduit($boutique, [
            'categorie_id' => $categorie->id,
            'code_barres' => '1234567890123',
        ]))->assertRedirect();

        $produit = Produit::where('nom', 'Engrais NPK 15-15-15')->firstOrFail();
        $this->assertSame($tenant->id, $produit->tenant_id);
        $this->assertSame(10, $produit->stock_min);
        $this->assertSame(200, $produit->stock_max);

        $this->assertDatabaseHas('lots', [
            'produit_id' => $produit->id,
            'numero_lot' => 'LOT-2026-01',
        ]);
        $this->assertDatabaseHas('stock_boutiques', [
            'produit_id' => $produit->id,
            'boutique_id' => $boutique->id,
            'quantite' => 40,
        ]);
    }

    public function test_the_expiry_date_is_required_on_a_phytosanitary_product(): void
    {
        $tenant = Tenant::factory()->create();
        $patron = User::factory()->create(['tenant_id' => $tenant->id, 'role' => UserRole::Patron]);
        $boutique = Boutique::factory()->create(['tenant_id' => $tenant->id]);

        $donnees = $this->donneesProduit($boutique, [
            'nom' => 'Glyphosate 1L',
            'type' => TypeProduit::ProduitPhytosanitaire->value,
        ]);
        unset($donnees['date_peremption']);

        $this->actingAs($patron)->from('/produits/create')->post('/produits', $donnees)
            ->assertSessionHasErrors('date_peremption');

        $this->assertDatabaseMissing('produits', ['nom' => 'Glyphosate 1L']);
    }

    public function test_a_product_without_mandatory_traceability_needs_no_date(): void
    {
        $tenant = Tenant::factory()->create();
        $patron = User::factory()->create(['tenant_id' => $tenant->id, 'role' => UserRole::Patron]);
        $boutique = Boutique::factory()->create(['tenant_id' => $tenant->id]);

        // Hors phytosanitaires, exiger une date pousserait à en inventer une,
        // et les alertes de péremption se rempliraient d'échéances fictives.
        $donnees = $this->donneesProduit($boutique, [
            'nom' => 'Houe manuelle',
            'type' => TypeProduit::IntrantAgricole->value,
        ]);
        unset($donnees['date_peremption']);

        $this->actingAs($patron)->from('/produits/create')->post('/produits', $donnees)
            ->assertSessionHasNoErrors();

        $produit = Produit::where('nom', 'Houe manuelle')->firstOrFail();
        $this->assertNull(Lot::where('produit_id', $produit->id)->first()?->date_peremption);
    }

    public function test_an_already_expired_batch_is_rejected(): void
    {
        $tenant = Tenant::factory()->create();
        $patron = User::factory()->create(['tenant_id' => $tenant->id, 'role' => UserRole::Patron]);
        $boutique = Boutique::factory()->create(['tenant_id' => $tenant->id]);

        $this->actingAs($patron)->from('/produits/create')->post('/produits', $this->donneesProduit($boutique, [
            'date_peremption' => now()->subDay()->toDateString(),
        ]))->assertSessionHasErrors('date_peremption');
    }

    public function test_the_min_and_max_stock_are_required_and_ordered(): void
    {
        $tenant = Tenant::factory()->create();
        $patron = User::factory()->create(['tenant_id' => $tenant->id, 'role' => UserRole::Patron]);
        $boutique = Boutique::factory()->create(['tenant_id' => $tenant->id]);

        $donnees = $this->donneesProduit($boutique);
        unset($donnees['stock_max']);

        $this->actingAs($patron)->from('/produits/create')->post('/produits', $donnees)
            ->assertSessionHasErrors('stock_max');

        // Un maximum sous le minimum n'a pas de sens.
        $this->actingAs($patron)->from('/produits/create')->post('/produits', $this->donneesProduit($boutique, [
            'stock_min' => 50,
            'stock_max' => 20,
        ]))->assertSessionHasErrors('stock_max');
    }

    public function test_the_initial_stock_cannot_land_in_another_tenant_boutique(): void
    {
        $tenant = Tenant::factory()->create();
        $patron = User::factory()->create(['tenant_id' => $tenant->id, 'role' => UserRole::Patron]);
        $boutiqueEtrangere = Boutique::factory()->create(['tenant_id' => Tenant::factory()->create()->id]);

        $this->actingAs($patron)->from('/produits/create')->post('/produits', $this->donneesProduit($boutiqueEtrangere))
            ->assertSessionHasErrors('boutique_id');

        $this->assertDatabaseMissing('produits', ['nom' => 'Engrais NPK 15-15-15']);
    }

    public function test_a_vendeur_cannot_create_a_product(): void
    {
        $tenant = Tenant::factory()->create();
        $boutique = Boutique::factory()->create(['tenant_id' => $tenant->id]);
        $vendeur = User::factory()->create([
            'tenant_id' => $tenant->id, 'role' => UserRole::Vendeur, 'boutique_id' => $boutique->id,
        ]);

        $this->actingAs($vendeur)->post('/produits', $this->donneesProduit($boutique))->assertForbidden();
        $this->assertDatabaseMissing('produits', ['nom' => 'Engrais NPK 15-15-15']);
    }

    public function test_products_are_scoped_to_the_tenant(): void
    {
        $tenantA = Tenant::factory()->create();
        $tenantB = Tenant::factory()->create();

        $patronA = User::factory()->create(['tenant_id' => $tenantA->id, 'role' => UserRole::Patron]);
        Produit::factory()->create(['tenant_id' => $tenantA->id, 'nom' => 'Produit A']);
        Produit::factory()->create(['tenant_id' => $tenantB->id, 'nom' => 'Produit B']);

        $response = $this->actingAs($patronA)->get('/produits');

        $response->assertOk();
        $response->assertSee('Produit A');
        $response->assertDontSee('Produit B');
    }

    public function test_barcode_must_be_unique_within_the_tenant(): void
    {
        $tenant = Tenant::factory()->create();
        $patron = User::factory()->create(['tenant_id' => $tenant->id, 'role' => UserRole::Patron]);
        $boutique = Boutique::factory()->create(['tenant_id' => $tenant->id]);
        Produit::factory()->create(['tenant_id' => $tenant->id, 'code_barres' => '1111111111111']);

        $this->actingAs($patron)->from('/produits/create')->post('/produits', $this->donneesProduit($boutique, [
            'nom' => 'Autre produit',
            'code_barres' => '1111111111111',
        ]))->assertSessionHasErrors('code_barres');
    }

    public function test_a_patron_can_create_a_category(): void
    {
        $tenant = Tenant::factory()->create();
        $patron = User::factory()->create(['tenant_id' => $tenant->id, 'role' => UserRole::Patron]);

        $response = $this->actingAs($patron)->post('/categories', ['nom' => 'Engrais']);

        $response->assertRedirect(route('produits.index'));
        $this->assertDatabaseHas('categories', ['nom' => 'Engrais', 'tenant_id' => $tenant->id]);
    }
}
