<?php

namespace Tests\Feature;

use App\Enums\ModePaiement;
use App\Enums\UserRole;
use App\Models\Boutique;
use App\Models\Lot;
use App\Models\Produit;
use App\Models\StockBoutique;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    use RefreshDatabase;

    private function enregistrerVente(Tenant $tenant, Boutique $boutique, User $vendeur, Produit $produit, int $quantite): void
    {
        $lot = Lot::factory()->create(['tenant_id' => $tenant->id, 'produit_id' => $produit->id]);
        StockBoutique::factory()->create([
            'tenant_id' => $tenant->id,
            'boutique_id' => $boutique->id,
            'produit_id' => $produit->id,
            'lot_id' => $lot->id,
            'quantite' => $quantite + 50,
        ]);

        $this->actingAs($vendeur)->post('/ventes', [
            'boutique_id' => $boutique->id,
            'mode_paiement' => ModePaiement::Especes->value,
            'lignes' => [
                ['produit_id' => $produit->id, 'quantite' => $quantite],
            ],
        ])->assertRedirect();
    }

    public function test_the_dashboard_shows_revenue_and_margin_from_sales(): void
    {
        $tenant = Tenant::factory()->create();
        $boutique = Boutique::factory()->create(['tenant_id' => $tenant->id]);
        $patron = User::factory()->create(['tenant_id' => $tenant->id, 'role' => UserRole::Patron]);
        $vendeur = User::factory()->create(['tenant_id' => $tenant->id, 'role' => UserRole::Vendeur, 'boutique_id' => $boutique->id]);
        $produit = Produit::factory()->create(['tenant_id' => $tenant->id, 'prix_achat' => 1000, 'prix_vente' => 1500]);

        $this->enregistrerVente($tenant, $boutique, $vendeur, $produit, 10);

        $response = $this->actingAs($patron)->get('/dashboard');

        $response->assertOk();
        $response->assertSee('15 000 FCFA');
        $response->assertSee('5 000 FCFA');
    }

    public function test_the_dashboard_lists_best_selling_products(): void
    {
        $tenant = Tenant::factory()->create();
        $boutique = Boutique::factory()->create(['tenant_id' => $tenant->id]);
        $patron = User::factory()->create(['tenant_id' => $tenant->id, 'role' => UserRole::Patron]);
        $vendeur = User::factory()->create(['tenant_id' => $tenant->id, 'role' => UserRole::Vendeur, 'boutique_id' => $boutique->id]);
        $produitPopulaire = Produit::factory()->create(['tenant_id' => $tenant->id, 'nom' => 'Engrais NPK', 'prix_vente' => 1000]);
        $produitMoinsVendu = Produit::factory()->create(['tenant_id' => $tenant->id, 'nom' => 'Semence rare', 'prix_vente' => 1000]);

        $this->enregistrerVente($tenant, $boutique, $vendeur, $produitPopulaire, 20);
        $this->enregistrerVente($tenant, $boutique, $vendeur, $produitMoinsVendu, 2);

        $response = $this->actingAs($patron)->get('/dashboard');

        $response->assertOk();
        $response->assertSeeInOrder(['Engrais NPK', 'Semence rare']);
    }

    public function test_the_dashboard_flags_dormant_stock_never_sold(): void
    {
        $tenant = Tenant::factory()->create();
        $boutique = Boutique::factory()->create(['tenant_id' => $tenant->id]);
        $patron = User::factory()->create(['tenant_id' => $tenant->id, 'role' => UserRole::Patron]);
        $produit = Produit::factory()->create(['tenant_id' => $tenant->id, 'nom' => 'Produit invendu']);
        $lot = Lot::factory()->create(['tenant_id' => $tenant->id, 'produit_id' => $produit->id]);
        StockBoutique::factory()->create(['tenant_id' => $tenant->id, 'boutique_id' => $boutique->id, 'produit_id' => $produit->id, 'lot_id' => $lot->id, 'quantite' => 30]);

        $response = $this->actingAs($patron)->get('/dashboard');

        $response->assertOk();
        $response->assertSee('Produit invendu');
        $response->assertSee(__('Jamais vendu'));
    }

    public function test_a_recently_sold_product_is_not_flagged_as_dormant(): void
    {
        $tenant = Tenant::factory()->create();
        $boutique = Boutique::factory()->create(['tenant_id' => $tenant->id]);
        $patron = User::factory()->create(['tenant_id' => $tenant->id, 'role' => UserRole::Patron]);
        $vendeur = User::factory()->create(['tenant_id' => $tenant->id, 'role' => UserRole::Vendeur, 'boutique_id' => $boutique->id]);
        $produit = Produit::factory()->create(['tenant_id' => $tenant->id, 'nom' => 'Produit actif']);

        $this->enregistrerVente($tenant, $boutique, $vendeur, $produit, 1);

        $response = $this->actingAs($patron)->get('/dashboard');

        $response->assertOk();
        $response->assertSee(__('Aucun produit dormant.'));
    }

    public function test_a_gerant_only_sees_kpis_for_his_own_boutique(): void
    {
        $tenant = Tenant::factory()->create();
        $boutiqueA = Boutique::factory()->create(['tenant_id' => $tenant->id]);
        $boutiqueB = Boutique::factory()->create(['tenant_id' => $tenant->id]);
        $gerantA = User::factory()->create(['tenant_id' => $tenant->id, 'role' => UserRole::Gerant, 'boutique_id' => $boutiqueA->id]);
        $vendeurB = User::factory()->create(['tenant_id' => $tenant->id, 'role' => UserRole::Vendeur, 'boutique_id' => $boutiqueB->id]);
        $produit = Produit::factory()->create(['tenant_id' => $tenant->id, 'prix_vente' => 1000]);

        $this->enregistrerVente($tenant, $boutiqueB, $vendeurB, $produit, 5);

        $response = $this->actingAs($gerantA)->get('/dashboard');

        $response->assertOk();
        $response->assertSee('0 FCFA');
        $response->assertDontSee('5 000 FCFA');
    }
}
