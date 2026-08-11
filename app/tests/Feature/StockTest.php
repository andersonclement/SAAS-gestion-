<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Boutique;
use App\Models\Lot;
use App\Models\Produit;
use App\Models\StockBoutique;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StockTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_patron_sees_stock_across_all_boutiques(): void
    {
        $tenant = Tenant::factory()->create();
        $boutiqueA = Boutique::factory()->create(['tenant_id' => $tenant->id, 'nom' => 'Boutique A']);
        $boutiqueB = Boutique::factory()->create(['tenant_id' => $tenant->id, 'nom' => 'Boutique B']);
        $patron = User::factory()->create(['tenant_id' => $tenant->id, 'role' => UserRole::Patron]);

        $produit = Produit::factory()->create(['tenant_id' => $tenant->id]);
        $lot = Lot::factory()->create(['tenant_id' => $tenant->id, 'produit_id' => $produit->id]);
        StockBoutique::factory()->create(['tenant_id' => $tenant->id, 'boutique_id' => $boutiqueA->id, 'produit_id' => $produit->id, 'lot_id' => $lot->id, 'quantite' => 10]);
        StockBoutique::factory()->create(['tenant_id' => $tenant->id, 'boutique_id' => $boutiqueB->id, 'produit_id' => $produit->id, 'lot_id' => $lot->id, 'quantite' => 5]);

        $response = $this->actingAs($patron)->get('/stock');

        $response->assertOk();
        $response->assertSee('Boutique A');
        $response->assertSee('Boutique B');
    }

    public function test_a_gerant_only_sees_stock_from_his_own_boutique(): void
    {
        $tenant = Tenant::factory()->create();
        $boutiqueA = Boutique::factory()->create(['tenant_id' => $tenant->id, 'nom' => 'Boutique A']);
        $boutiqueB = Boutique::factory()->create(['tenant_id' => $tenant->id, 'nom' => 'Boutique B']);
        $gerant = User::factory()->create(['tenant_id' => $tenant->id, 'role' => UserRole::Gerant, 'boutique_id' => $boutiqueA->id]);

        $produit = Produit::factory()->create(['tenant_id' => $tenant->id]);
        $lot = Lot::factory()->create(['tenant_id' => $tenant->id, 'produit_id' => $produit->id]);
        StockBoutique::factory()->create(['tenant_id' => $tenant->id, 'boutique_id' => $boutiqueA->id, 'produit_id' => $produit->id, 'lot_id' => $lot->id]);
        StockBoutique::factory()->create(['tenant_id' => $tenant->id, 'boutique_id' => $boutiqueB->id, 'produit_id' => $produit->id, 'lot_id' => $lot->id]);

        $response = $this->actingAs($gerant)->get('/stock');

        $response->assertOk();
        $response->assertSee('Boutique A');
        $response->assertDontSee('Boutique B');
    }

    public function test_a_low_stock_alert_is_shown_below_the_threshold(): void
    {
        $tenant = Tenant::factory()->create();
        $boutique = Boutique::factory()->create(['tenant_id' => $tenant->id]);
        $patron = User::factory()->create(['tenant_id' => $tenant->id, 'role' => UserRole::Patron]);
        $produit = Produit::factory()->create(['tenant_id' => $tenant->id, 'seuil_alerte' => 20]);
        $lot = Lot::factory()->create(['tenant_id' => $tenant->id, 'produit_id' => $produit->id]);
        StockBoutique::factory()->create(['tenant_id' => $tenant->id, 'boutique_id' => $boutique->id, 'produit_id' => $produit->id, 'lot_id' => $lot->id, 'quantite' => 5]);

        $response = $this->actingAs($patron)->get('/stock');

        $response->assertOk();
        $response->assertSee('Stock faible');
    }

    public function test_a_patron_can_transfer_stock_between_boutiques(): void
    {
        $tenant = Tenant::factory()->create();
        $boutiqueA = Boutique::factory()->create(['tenant_id' => $tenant->id]);
        $boutiqueB = Boutique::factory()->create(['tenant_id' => $tenant->id]);
        $patron = User::factory()->create(['tenant_id' => $tenant->id, 'role' => UserRole::Patron]);
        $produit = Produit::factory()->create(['tenant_id' => $tenant->id]);
        $lot = Lot::factory()->create(['tenant_id' => $tenant->id, 'produit_id' => $produit->id]);
        $stock = StockBoutique::factory()->create(['tenant_id' => $tenant->id, 'boutique_id' => $boutiqueA->id, 'produit_id' => $produit->id, 'lot_id' => $lot->id, 'quantite' => 30]);

        $response = $this->actingAs($patron)->post('/stock/transferts', [
            'stock_boutique_id' => $stock->id,
            'boutique_destination_id' => $boutiqueB->id,
            'quantite' => 10,
        ]);

        $response->assertRedirect(route('stock.index'));
        $this->assertDatabaseHas('stock_boutiques', ['id' => $stock->id, 'quantite' => 20]);
        $this->assertDatabaseHas('stock_boutiques', ['boutique_id' => $boutiqueB->id, 'lot_id' => $lot->id, 'quantite' => 10]);
        $this->assertDatabaseHas('transferts_stock', [
            'boutique_source_id' => $boutiqueA->id,
            'boutique_destination_id' => $boutiqueB->id,
            'quantite' => 10,
        ]);
    }

    public function test_a_transfer_cannot_exceed_available_stock(): void
    {
        $tenant = Tenant::factory()->create();
        $boutiqueA = Boutique::factory()->create(['tenant_id' => $tenant->id]);
        $boutiqueB = Boutique::factory()->create(['tenant_id' => $tenant->id]);
        $patron = User::factory()->create(['tenant_id' => $tenant->id, 'role' => UserRole::Patron]);
        $produit = Produit::factory()->create(['tenant_id' => $tenant->id]);
        $lot = Lot::factory()->create(['tenant_id' => $tenant->id, 'produit_id' => $produit->id]);
        $stock = StockBoutique::factory()->create(['tenant_id' => $tenant->id, 'boutique_id' => $boutiqueA->id, 'produit_id' => $produit->id, 'lot_id' => $lot->id, 'quantite' => 5]);

        $response = $this->actingAs($patron)->post('/stock/transferts', [
            'stock_boutique_id' => $stock->id,
            'boutique_destination_id' => $boutiqueB->id,
            'quantite' => 10,
        ]);

        $response->assertSessionHasErrors('quantite');
        $this->assertDatabaseHas('stock_boutiques', ['id' => $stock->id, 'quantite' => 5]);
    }

    public function test_a_gerant_cannot_transfer_stock_from_another_boutique(): void
    {
        $tenant = Tenant::factory()->create();
        $boutiqueA = Boutique::factory()->create(['tenant_id' => $tenant->id]);
        $boutiqueB = Boutique::factory()->create(['tenant_id' => $tenant->id]);
        $gerant = User::factory()->create(['tenant_id' => $tenant->id, 'role' => UserRole::Gerant, 'boutique_id' => $boutiqueB->id]);
        $produit = Produit::factory()->create(['tenant_id' => $tenant->id]);
        $lot = Lot::factory()->create(['tenant_id' => $tenant->id, 'produit_id' => $produit->id]);
        $stock = StockBoutique::factory()->create(['tenant_id' => $tenant->id, 'boutique_id' => $boutiqueA->id, 'produit_id' => $produit->id, 'lot_id' => $lot->id, 'quantite' => 30]);

        $response = $this->actingAs($gerant)->post('/stock/transferts', [
            'stock_boutique_id' => $stock->id,
            'boutique_destination_id' => $boutiqueB->id,
            'quantite' => 5,
        ]);

        $response->assertSessionHasErrors('stock_boutique_id');
    }

    public function test_recording_an_inventory_adjusts_stock_to_the_physical_count(): void
    {
        $tenant = Tenant::factory()->create();
        $boutique = Boutique::factory()->create(['tenant_id' => $tenant->id]);
        $patron = User::factory()->create(['tenant_id' => $tenant->id, 'role' => UserRole::Patron]);
        $produit = Produit::factory()->create(['tenant_id' => $tenant->id]);
        $lot = Lot::factory()->create(['tenant_id' => $tenant->id, 'produit_id' => $produit->id]);
        $stock = StockBoutique::factory()->create(['tenant_id' => $tenant->id, 'boutique_id' => $boutique->id, 'produit_id' => $produit->id, 'lot_id' => $lot->id, 'quantite' => 50]);

        $response = $this->actingAs($patron)->post('/stock/inventaires', [
            'boutique_id' => $boutique->id,
            'lignes' => [
                $stock->id => ['quantite_physique' => 47],
            ],
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('stock_boutiques', ['id' => $stock->id, 'quantite' => 47]);
        $this->assertDatabaseHas('ligne_inventaires', [
            'produit_id' => $produit->id,
            'quantite_theorique' => 50,
            'quantite_physique' => 47,
        ]);
    }

    public function test_a_vendeur_cannot_transfer_or_inventory_stock(): void
    {
        $tenant = Tenant::factory()->create();
        $boutique = Boutique::factory()->create(['tenant_id' => $tenant->id]);
        $vendeur = User::factory()->create(['tenant_id' => $tenant->id, 'role' => UserRole::Vendeur, 'boutique_id' => $boutique->id]);

        $this->actingAs($vendeur)->get('/stock/transferts/create')->assertForbidden();
        $this->actingAs($vendeur)->get('/stock/inventaires/create')->assertForbidden();
    }

    public function test_a_gerant_cannot_perform_an_inventory_adjustment(): void
    {
        $tenant = Tenant::factory()->create();
        $boutique = Boutique::factory()->create(['tenant_id' => $tenant->id]);
        $gerant = User::factory()->create(['tenant_id' => $tenant->id, 'role' => UserRole::Gerant, 'boutique_id' => $boutique->id]);
        $produit = Produit::factory()->create(['tenant_id' => $tenant->id]);
        $lot = Lot::factory()->create(['tenant_id' => $tenant->id, 'produit_id' => $produit->id]);
        $stock = StockBoutique::factory()->create(['tenant_id' => $tenant->id, 'boutique_id' => $boutique->id, 'produit_id' => $produit->id, 'lot_id' => $lot->id, 'quantite' => 20]);

        $this->actingAs($gerant)->get('/stock/inventaires/create')->assertForbidden();

        $response = $this->actingAs($gerant)->post('/stock/inventaires', [
            'boutique_id' => $boutique->id,
            'lignes' => [
                $stock->id => ['quantite_physique' => 5],
            ],
        ]);

        $response->assertForbidden();
        $this->assertDatabaseHas('stock_boutiques', ['id' => $stock->id, 'quantite' => 20]);
    }
}
