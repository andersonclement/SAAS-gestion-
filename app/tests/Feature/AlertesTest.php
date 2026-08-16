<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Boutique;
use App\Models\Lot;
use App\Models\Produit;
use App\Models\StockBoutique;
use App\Models\Tenant;
use App\Models\User;
use App\Models\Vente;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AlertesTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_product_at_zero_stock_is_flagged_as_out_of_stock(): void
    {
        $tenant = Tenant::factory()->create();
        $boutique = Boutique::factory()->create(['tenant_id' => $tenant->id]);
        $patron = User::factory()->create(['tenant_id' => $tenant->id, 'role' => UserRole::Patron]);
        $produit = Produit::factory()->create(['tenant_id' => $tenant->id, 'nom' => 'Semence épuisée']);
        $lot = Lot::factory()->create(['tenant_id' => $tenant->id, 'produit_id' => $produit->id]);
        StockBoutique::factory()->create(['tenant_id' => $tenant->id, 'boutique_id' => $boutique->id, 'produit_id' => $produit->id, 'lot_id' => $lot->id, 'quantite' => 0]);

        $response = $this->actingAs($patron)->get('/alertes');

        $response->assertOk();
        $response->assertSee('Semence épuisée');
    }

    public function test_a_product_below_threshold_is_flagged_as_low_stock(): void
    {
        $tenant = Tenant::factory()->create();
        $boutique = Boutique::factory()->create(['tenant_id' => $tenant->id]);
        $patron = User::factory()->create(['tenant_id' => $tenant->id, 'role' => UserRole::Patron]);
        $produit = Produit::factory()->create(['tenant_id' => $tenant->id, 'nom' => 'Engrais rare', 'stock_min' => 10]);
        $lot = Lot::factory()->create(['tenant_id' => $tenant->id, 'produit_id' => $produit->id]);
        StockBoutique::factory()->create(['tenant_id' => $tenant->id, 'boutique_id' => $boutique->id, 'produit_id' => $produit->id, 'lot_id' => $lot->id, 'quantite' => 5]);

        $response = $this->actingAs($patron)->get('/alertes');

        $response->assertOk();
        $response->assertSee('Engrais rare');
    }

    public function test_a_product_reaching_exactly_its_minimum_is_flagged(): void
    {
        $tenant = Tenant::factory()->create();
        $boutique = Boutique::factory()->create(['tenant_id' => $tenant->id]);
        $patron = User::factory()->create(['tenant_id' => $tenant->id, 'role' => UserRole::Patron]);
        $produit = Produit::factory()->create(['tenant_id' => $tenant->id, 'nom' => 'Engrais pile au seuil', 'stock_min' => 10, 'stock_max' => 100]);
        $lot = Lot::factory()->create(['tenant_id' => $tenant->id, 'produit_id' => $produit->id]);
        StockBoutique::factory()->create(['tenant_id' => $tenant->id, 'boutique_id' => $boutique->id, 'produit_id' => $produit->id, 'lot_id' => $lot->id, 'quantite' => 10]);

        $this->actingAs($patron)->get('/alertes')
            ->assertOk()
            ->assertSeeInOrder(['Stock au minimum', 'Engrais pile au seuil']);
    }

    public function test_a_product_above_its_maximum_is_flagged_as_overstock(): void
    {
        $tenant = Tenant::factory()->create();
        $boutique = Boutique::factory()->create(['tenant_id' => $tenant->id]);
        $patron = User::factory()->create(['tenant_id' => $tenant->id, 'role' => UserRole::Patron]);
        $produit = Produit::factory()->create(['tenant_id' => $tenant->id, 'nom' => 'Engrais en trop', 'stock_min' => 10, 'stock_max' => 50]);
        $lot = Lot::factory()->create(['tenant_id' => $tenant->id, 'produit_id' => $produit->id]);
        StockBoutique::factory()->create(['tenant_id' => $tenant->id, 'boutique_id' => $boutique->id, 'produit_id' => $produit->id, 'lot_id' => $lot->id, 'quantite' => 51]);

        $this->actingAs($patron)->get('/alertes')
            ->assertOk()
            ->assertSeeInOrder(['Surstock', 'Engrais en trop']);
    }

    public function test_a_product_without_a_maximum_is_never_flagged_as_overstock(): void
    {
        $tenant = Tenant::factory()->create();
        $boutique = Boutique::factory()->create(['tenant_id' => $tenant->id]);
        $patron = User::factory()->create(['tenant_id' => $tenant->id, 'role' => UserRole::Patron]);
        $produit = Produit::factory()->create(['tenant_id' => $tenant->id, 'nom' => 'Ancien produit', 'stock_min' => 1, 'stock_max' => 0]);
        $lot = Lot::factory()->create(['tenant_id' => $tenant->id, 'produit_id' => $produit->id]);
        StockBoutique::factory()->create(['tenant_id' => $tenant->id, 'boutique_id' => $boutique->id, 'produit_id' => $produit->id, 'lot_id' => $lot->id, 'quantite' => 9999]);

        $this->actingAs($patron)->get('/alertes')
            ->assertOk()
            ->assertSee(__('Aucun produit au-dessus de son maximum.'));
    }

    public function test_an_expired_lot_still_in_stock_is_flagged(): void
    {
        $tenant = Tenant::factory()->create();
        $boutique = Boutique::factory()->create(['tenant_id' => $tenant->id]);
        $patron = User::factory()->create(['tenant_id' => $tenant->id, 'role' => UserRole::Patron]);
        $produit = Produit::factory()->create(['tenant_id' => $tenant->id, 'nom' => 'Pesticide périmé']);
        $lot = Lot::factory()->create(['tenant_id' => $tenant->id, 'produit_id' => $produit->id, 'date_peremption' => now()->subDays(5)]);
        StockBoutique::factory()->create(['tenant_id' => $tenant->id, 'boutique_id' => $boutique->id, 'produit_id' => $produit->id, 'lot_id' => $lot->id, 'quantite' => 3]);

        $response = $this->actingAs($patron)->get('/alertes');

        $response->assertOk();
        $response->assertSeeInOrder(['Péremption dépassée', 'Pesticide périmé']);
    }

    public function test_a_lot_expiring_within_30_days_is_flagged_as_soon(): void
    {
        $tenant = Tenant::factory()->create();
        $boutique = Boutique::factory()->create(['tenant_id' => $tenant->id]);
        $patron = User::factory()->create(['tenant_id' => $tenant->id, 'role' => UserRole::Patron]);
        $produit = Produit::factory()->create(['tenant_id' => $tenant->id, 'nom' => 'Pesticide bientôt périmé']);
        $lot = Lot::factory()->create(['tenant_id' => $tenant->id, 'produit_id' => $produit->id, 'date_peremption' => now()->addDays(10)]);
        StockBoutique::factory()->create(['tenant_id' => $tenant->id, 'boutique_id' => $boutique->id, 'produit_id' => $produit->id, 'lot_id' => $lot->id, 'quantite' => 3]);

        $response = $this->actingAs($patron)->get('/alertes');

        $response->assertOk();
        $response->assertSeeInOrder(['Péremption proche (30 jours)', 'Pesticide bientôt périmé']);
    }

    public function test_an_overdue_credit_sale_appears_in_the_alert_center(): void
    {
        $tenant = Tenant::factory()->create();
        $boutique = Boutique::factory()->create(['tenant_id' => $tenant->id]);
        $patron = User::factory()->create(['tenant_id' => $tenant->id, 'role' => UserRole::Patron]);
        $vente = Vente::factory()->create([
            'tenant_id' => $tenant->id,
            'boutique_id' => $boutique->id,
            'date_echeance' => now()->subDays(3),
        ]);
        $produit = Produit::factory()->create(['tenant_id' => $tenant->id]);
        $lot = Lot::factory()->create(['tenant_id' => $tenant->id, 'produit_id' => $produit->id]);
        $vente->lignes()->create(['produit_id' => $produit->id, 'lot_id' => $lot->id, 'quantite' => 1, 'prix_unitaire' => 1000]);

        $response = $this->actingAs($patron)->get('/alertes');

        $response->assertOk();
        $response->assertSee($vente->numero);
    }

    public function test_the_navigation_badge_counts_active_alerts(): void
    {
        $tenant = Tenant::factory()->create();
        $boutique = Boutique::factory()->create(['tenant_id' => $tenant->id]);
        $patron = User::factory()->create(['tenant_id' => $tenant->id, 'role' => UserRole::Patron]);
        $produit = Produit::factory()->create(['tenant_id' => $tenant->id]);
        $lot = Lot::factory()->create(['tenant_id' => $tenant->id, 'produit_id' => $produit->id]);
        StockBoutique::factory()->create(['tenant_id' => $tenant->id, 'boutique_id' => $boutique->id, 'produit_id' => $produit->id, 'lot_id' => $lot->id, 'quantite' => 0]);

        $response = $this->actingAs($patron)->get('/dashboard');

        $response->assertOk();
        $response->assertSeeInOrder(['Alertes', '1']);
    }

    public function test_a_gerant_only_sees_alerts_for_his_own_boutique(): void
    {
        $tenant = Tenant::factory()->create();
        $boutiqueA = Boutique::factory()->create(['tenant_id' => $tenant->id]);
        $boutiqueB = Boutique::factory()->create(['tenant_id' => $tenant->id]);
        $gerantA = User::factory()->create(['tenant_id' => $tenant->id, 'role' => UserRole::Gerant, 'boutique_id' => $boutiqueA->id]);
        $produit = Produit::factory()->create(['tenant_id' => $tenant->id, 'nom' => 'Produit boutique B']);
        $lot = Lot::factory()->create(['tenant_id' => $tenant->id, 'produit_id' => $produit->id]);
        StockBoutique::factory()->create(['tenant_id' => $tenant->id, 'boutique_id' => $boutiqueB->id, 'produit_id' => $produit->id, 'lot_id' => $lot->id, 'quantite' => 0]);

        $response = $this->actingAs($gerantA)->get('/alertes');

        $response->assertOk();
        $response->assertDontSee('Produit boutique B');
    }
}
