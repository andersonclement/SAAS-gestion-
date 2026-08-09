<?php

namespace Tests\Feature;

use App\Enums\TypeProduit;
use App\Enums\UniteMesure;
use App\Enums\UserRole;
use App\Models\Categorie;
use App\Models\Produit;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CatalogueProduitsTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_patron_can_create_a_product(): void
    {
        $tenant = Tenant::factory()->create();
        $patron = User::factory()->create(['tenant_id' => $tenant->id, 'role' => UserRole::Patron]);
        $categorie = Categorie::factory()->create(['tenant_id' => $tenant->id]);

        $response = $this->actingAs($patron)->post('/produits', [
            'nom' => 'Engrais NPK 15-15-15',
            'categorie_id' => $categorie->id,
            'type' => TypeProduit::IntrantAgricole->value,
            'unite_mesure' => UniteMesure::Sac->value,
            'code_barres' => '1234567890123',
            'prix_achat' => 12000,
            'prix_vente' => 15000,
            'seuil_alerte' => 10,
        ]);

        $response->assertRedirect(route('produits.index'));
        $this->assertDatabaseHas('produits', [
            'nom' => 'Engrais NPK 15-15-15',
            'tenant_id' => $tenant->id,
            'type' => TypeProduit::IntrantAgricole->value,
        ]);
    }

    public function test_a_gerant_can_create_a_product(): void
    {
        $tenant = Tenant::factory()->create();
        $gerant = User::factory()->create(['tenant_id' => $tenant->id, 'role' => UserRole::Gerant]);

        $response = $this->actingAs($gerant)->post('/produits', [
            'nom' => 'Glyphosate 1L',
            'type' => TypeProduit::ProduitPhytosanitaire->value,
            'unite_mesure' => UniteMesure::Litre->value,
            'prix_achat' => 5000,
            'prix_vente' => 6500,
            'seuil_alerte' => 5,
        ]);

        $response->assertRedirect(route('produits.index'));
        $this->assertDatabaseHas('produits', ['nom' => 'Glyphosate 1L']);
    }

    public function test_a_vendeur_cannot_create_a_product(): void
    {
        $tenant = Tenant::factory()->create();
        $vendeur = User::factory()->create(['tenant_id' => $tenant->id, 'role' => UserRole::Vendeur]);

        $response = $this->actingAs($vendeur)->post('/produits', [
            'nom' => 'Semence de maïs',
            'type' => TypeProduit::IntrantAgricole->value,
            'unite_mesure' => UniteMesure::Sac->value,
            'prix_achat' => 3000,
            'prix_vente' => 4000,
            'seuil_alerte' => 5,
        ]);

        $response->assertForbidden();
        $this->assertDatabaseMissing('produits', ['nom' => 'Semence de maïs']);
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
        Produit::factory()->create(['tenant_id' => $tenant->id, 'code_barres' => '1111111111111']);

        $response = $this->actingAs($patron)->from('/produits/create')->post('/produits', [
            'nom' => 'Autre produit',
            'type' => TypeProduit::IntrantAgricole->value,
            'unite_mesure' => UniteMesure::Unite->value,
            'code_barres' => '1111111111111',
            'prix_achat' => 100,
            'prix_vente' => 150,
            'seuil_alerte' => 1,
        ]);

        $response->assertSessionHasErrors('code_barres');
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
