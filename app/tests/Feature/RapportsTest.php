<?php

namespace Tests\Feature;

use App\Enums\ModePaiement;
use App\Enums\UserRole;
use App\Models\BonCommande;
use App\Models\Boutique;
use App\Models\Fournisseur;
use App\Models\Lot;
use App\Models\Produit;
use App\Models\StockBoutique;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RapportsTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_patron_can_export_sales_as_csv(): void
    {
        $tenant = Tenant::factory()->create();
        $boutique = Boutique::factory()->create(['tenant_id' => $tenant->id]);
        $patron = User::factory()->create(['tenant_id' => $tenant->id, 'role' => UserRole::Patron]);
        $vendeur = User::factory()->create(['tenant_id' => $tenant->id, 'role' => UserRole::Vendeur, 'boutique_id' => $boutique->id]);
        $produit = Produit::factory()->create(['tenant_id' => $tenant->id, 'prix_vente' => 4000]);
        $lot = Lot::factory()->create(['tenant_id' => $tenant->id, 'produit_id' => $produit->id]);
        StockBoutique::factory()->create(['tenant_id' => $tenant->id, 'boutique_id' => $boutique->id, 'produit_id' => $produit->id, 'lot_id' => $lot->id, 'quantite' => 10]);

        $this->actingAs($vendeur)->post('/ventes', [
            'boutique_id' => $boutique->id,
            'mode_paiement' => ModePaiement::Especes->value,
            'lignes' => [
                ['produit_id' => $produit->id, 'quantite' => 2],
            ],
        ])->assertRedirect();

        $response = $this->actingAs($patron)->get('/rapports/ventes.csv');

        $response->assertOk();
        $response->assertHeader('Content-Type', 'text/csv; charset=utf-8');

        $contenu = $response->streamedContent();
        $this->assertStringContainsString('VT-000001', $contenu);
        $this->assertStringContainsString('8000', $contenu);
    }

    public function test_a_gerant_only_exports_purchases_for_his_own_boutique(): void
    {
        $tenant = Tenant::factory()->create();
        $boutiqueA = Boutique::factory()->create(['tenant_id' => $tenant->id]);
        $boutiqueB = Boutique::factory()->create(['tenant_id' => $tenant->id]);
        $gerantA = User::factory()->create(['tenant_id' => $tenant->id, 'role' => UserRole::Gerant, 'boutique_id' => $boutiqueA->id]);
        $fournisseurA = Fournisseur::factory()->create(['tenant_id' => $tenant->id]);
        $fournisseurB = Fournisseur::factory()->create(['tenant_id' => $tenant->id]);

        BonCommande::factory()->create(['tenant_id' => $tenant->id, 'boutique_id' => $boutiqueA->id, 'fournisseur_id' => $fournisseurA->id, 'numero' => 'BC-AAA']);
        BonCommande::factory()->create(['tenant_id' => $tenant->id, 'boutique_id' => $boutiqueB->id, 'fournisseur_id' => $fournisseurB->id, 'numero' => 'BC-BBB']);

        $response = $this->actingAs($gerantA)->get('/rapports/achats.csv');

        $response->assertOk();
        $contenu = $response->streamedContent();
        $this->assertStringContainsString('BC-AAA', $contenu);
        $this->assertStringNotContainsString('BC-BBB', $contenu);
    }

    public function test_the_stock_export_lists_products_with_expiry_dates(): void
    {
        $tenant = Tenant::factory()->create();
        $boutique = Boutique::factory()->create(['tenant_id' => $tenant->id]);
        $patron = User::factory()->create(['tenant_id' => $tenant->id, 'role' => UserRole::Patron]);
        $produit = Produit::factory()->create(['tenant_id' => $tenant->id, 'nom' => 'Glyphosate export']);
        $lot = Lot::factory()->create(['tenant_id' => $tenant->id, 'produit_id' => $produit->id, 'date_peremption' => '2027-01-15']);
        StockBoutique::factory()->create(['tenant_id' => $tenant->id, 'boutique_id' => $boutique->id, 'produit_id' => $produit->id, 'lot_id' => $lot->id, 'quantite' => 42]);

        $response = $this->actingAs($patron)->get('/rapports/stock.csv');

        $response->assertOk();
        $contenu = $response->streamedContent();
        $this->assertStringContainsString('Glyphosate export', $contenu);
        $this->assertStringContainsString('2027-01-15', $contenu);
        $this->assertStringContainsString('42', $contenu);
    }
}
