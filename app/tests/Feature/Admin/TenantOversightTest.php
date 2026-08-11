<?php

namespace Tests\Feature\Admin;

use App\Enums\ModePaiement;
use App\Enums\UserRole;
use App\Models\Admin;
use App\Models\Boutique;
use App\Models\Lot;
use App\Models\Produit;
use App\Models\StockBoutique;
use App\Models\Tenant;
use App\Models\User;
use App\Support\Journal;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Vérifie que le superadmin peut analyser le flux complet d'un tenant
 * (ventes, journal d'activité) sans être authentifié côté tenant, et sans
 * fuite entre les données de deux clients différents.
 */
class TenantOversightTest extends TestCase
{
    use RefreshDatabase;

    private function creerVente(Tenant $tenant, Boutique $boutique, User $vendeur, Produit $produit): void
    {
        $this->actingAs($vendeur)->post('/ventes', [
            'boutique_id' => $boutique->id,
            'mode_paiement' => ModePaiement::Especes->value,
            'lignes' => [
                ['produit_id' => $produit->id, 'quantite' => 1],
            ],
        ])->assertRedirect();
    }

    public function test_the_overview_shows_commercial_kpis_for_the_tenant(): void
    {
        $admin = Admin::factory()->create();
        $tenant = Tenant::factory()->create();
        $boutique = Boutique::factory()->create(['tenant_id' => $tenant->id]);
        $vendeur = User::factory()->create(['tenant_id' => $tenant->id, 'role' => UserRole::Vendeur, 'boutique_id' => $boutique->id]);
        $produit = Produit::factory()->create(['tenant_id' => $tenant->id, 'prix_vente' => 7000]);
        $lot = Lot::factory()->create(['tenant_id' => $tenant->id, 'produit_id' => $produit->id]);
        StockBoutique::factory()->create(['tenant_id' => $tenant->id, 'boutique_id' => $boutique->id, 'produit_id' => $produit->id, 'lot_id' => $lot->id, 'quantite' => 5]);

        $this->creerVente($tenant, $boutique, $vendeur, $produit);

        $response = $this->actingAs($admin, 'admin')->get(route('admin.tenants.show', $tenant));

        $response->assertOk();
        $response->assertSee('7 000 FCFA');
        $response->assertSee($boutique->nom);
    }

    public function test_the_journal_lists_activity_for_the_tenant_and_can_be_filtered_by_action(): void
    {
        $admin = Admin::factory()->create();
        $tenant = Tenant::factory()->create();
        $boutique = Boutique::factory()->create(['tenant_id' => $tenant->id]);
        $patron = User::factory()->create(['tenant_id' => $tenant->id, 'role' => UserRole::Patron]);

        $this->actingAs($patron)->get('/dashboard');
        Journal::enregistrer('test.action', 'Une action de test bien identifiable', $boutique->id);

        $response = $this->actingAs($admin, 'admin')->get(route('admin.tenants.journal', $tenant));

        $response->assertOk();
        $response->assertSee('Une action de test bien identifiable');

        $filtre = $this->actingAs($admin, 'admin')
            ->get(route('admin.tenants.journal', ['tenant' => $tenant, 'action' => 'test.action']));

        $filtre->assertOk();
        $filtre->assertSee('Une action de test bien identifiable');
    }

    public function test_the_journal_of_one_tenant_never_shows_another_tenants_activity(): void
    {
        $admin = Admin::factory()->create();
        $tenantA = Tenant::factory()->create();
        $tenantB = Tenant::factory()->create();
        $patronA = User::factory()->create(['tenant_id' => $tenantA->id, 'role' => UserRole::Patron]);
        $patronB = User::factory()->create(['tenant_id' => $tenantB->id, 'role' => UserRole::Patron]);

        $this->actingAs($patronA);
        Journal::enregistrer('test.action', 'Action du tenant A');
        $this->actingAs($patronB);
        Journal::enregistrer('test.action', 'Action du tenant B');

        $response = $this->actingAs($admin, 'admin')->get(route('admin.tenants.journal', $tenantA));

        $response->assertOk();
        $response->assertSee($patronA->name);
        $response->assertDontSee($patronB->name);
    }

    public function test_the_sales_page_lists_every_sale_for_the_tenant(): void
    {
        $admin = Admin::factory()->create();
        $tenant = Tenant::factory()->create();
        $boutique = Boutique::factory()->create(['tenant_id' => $tenant->id]);
        $vendeur = User::factory()->create(['tenant_id' => $tenant->id, 'role' => UserRole::Vendeur, 'boutique_id' => $boutique->id]);
        $produit = Produit::factory()->create(['tenant_id' => $tenant->id, 'prix_vente' => 3000]);
        $lot = Lot::factory()->create(['tenant_id' => $tenant->id, 'produit_id' => $produit->id]);
        StockBoutique::factory()->create(['tenant_id' => $tenant->id, 'boutique_id' => $boutique->id, 'produit_id' => $produit->id, 'lot_id' => $lot->id, 'quantite' => 5]);

        $this->creerVente($tenant, $boutique, $vendeur, $produit);

        $response = $this->actingAs($admin, 'admin')->get(route('admin.tenants.ventes', $tenant));

        $response->assertOk();
        $response->assertSee('VT-000001');
        $response->assertSee('3 000 FCFA');
    }

    public function test_a_tenant_user_cannot_access_another_tenants_overview(): void
    {
        $tenantA = Tenant::factory()->create();
        $tenantB = Tenant::factory()->create();
        $patronA = User::factory()->create(['tenant_id' => $tenantA->id, 'role' => UserRole::Patron]);

        $response = $this->actingAs($patronA)->get(route('admin.tenants.show', $tenantB));

        $response->assertRedirect(route('admin.login'));
    }
}
