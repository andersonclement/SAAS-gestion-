<?php

namespace Tests\Feature;

use App\Enums\ModePaiement;
use App\Enums\TypeClient;
use App\Enums\UserRole;
use App\Models\Boutique;
use App\Models\Client;
use App\Models\Lot;
use App\Models\Produit;
use App\Models\StockBoutique;
use App\Models\Tenant;
use App\Models\User;
use App\Models\Vente;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class VentesTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_vendeur_can_create_a_sale_and_stock_is_decremented(): void
    {
        $tenant = Tenant::factory()->create();
        $boutique = Boutique::factory()->create(['tenant_id' => $tenant->id]);
        $vendeur = User::factory()->create(['tenant_id' => $tenant->id, 'role' => UserRole::Vendeur, 'boutique_id' => $boutique->id]);
        $client = Client::factory()->create(['tenant_id' => $tenant->id]);
        $produit = Produit::factory()->create(['tenant_id' => $tenant->id, 'prix_vente' => 5000]);
        $lot = Lot::factory()->create(['tenant_id' => $tenant->id, 'produit_id' => $produit->id]);
        $stock = StockBoutique::factory()->create(['tenant_id' => $tenant->id, 'boutique_id' => $boutique->id, 'produit_id' => $produit->id, 'lot_id' => $lot->id, 'quantite' => 20]);

        $response = $this->actingAs($vendeur)->post('/ventes', [
            'boutique_id' => $boutique->id,
            'client_id' => $client->id,
            'mode_paiement' => ModePaiement::Especes->value,
            'lignes' => [
                ['produit_id' => $produit->id, 'quantite' => 5],
            ],
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('ventes', ['tenant_id' => $tenant->id, 'boutique_id' => $boutique->id, 'client_id' => $client->id, 'vendeur_id' => $vendeur->id]);
        $this->assertDatabaseHas('ligne_ventes', ['produit_id' => $produit->id, 'lot_id' => $lot->id, 'quantite' => 5, 'prix_unitaire' => 5000]);
        $this->assertDatabaseHas('stock_boutiques', ['id' => $stock->id, 'quantite' => 15]);
        $this->assertDatabaseHas('paiements', ['mode' => 'especes', 'montant' => 25000]);
    }

    public function test_a_sale_can_be_made_without_a_client(): void
    {
        $tenant = Tenant::factory()->create();
        $boutique = Boutique::factory()->create(['tenant_id' => $tenant->id]);
        $vendeur = User::factory()->create(['tenant_id' => $tenant->id, 'role' => UserRole::Vendeur, 'boutique_id' => $boutique->id]);
        $produit = Produit::factory()->create(['tenant_id' => $tenant->id, 'prix_vente' => 1000]);
        $lot = Lot::factory()->create(['tenant_id' => $tenant->id, 'produit_id' => $produit->id]);
        StockBoutique::factory()->create(['tenant_id' => $tenant->id, 'boutique_id' => $boutique->id, 'produit_id' => $produit->id, 'lot_id' => $lot->id, 'quantite' => 10]);

        $response = $this->actingAs($vendeur)->post('/ventes', [
            'boutique_id' => $boutique->id,
            'mode_paiement' => ModePaiement::MobileMoney->value,
            'lignes' => [
                ['produit_id' => $produit->id, 'quantite' => 2],
            ],
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('ventes', ['boutique_id' => $boutique->id, 'client_id' => null]);
    }

    public function test_a_sale_cannot_exceed_available_stock(): void
    {
        $tenant = Tenant::factory()->create();
        $boutique = Boutique::factory()->create(['tenant_id' => $tenant->id]);
        $vendeur = User::factory()->create(['tenant_id' => $tenant->id, 'role' => UserRole::Vendeur, 'boutique_id' => $boutique->id]);
        $produit = Produit::factory()->create(['tenant_id' => $tenant->id]);
        $lot = Lot::factory()->create(['tenant_id' => $tenant->id, 'produit_id' => $produit->id]);
        StockBoutique::factory()->create(['tenant_id' => $tenant->id, 'boutique_id' => $boutique->id, 'produit_id' => $produit->id, 'lot_id' => $lot->id, 'quantite' => 3]);

        $response = $this->actingAs($vendeur)->post('/ventes', [
            'boutique_id' => $boutique->id,
            'mode_paiement' => ModePaiement::Especes->value,
            'lignes' => [
                ['produit_id' => $produit->id, 'quantite' => 10],
            ],
        ]);

        $response->assertSessionHasErrors('lignes.0.quantite');
        $this->assertDatabaseMissing('ventes', ['boutique_id' => $boutique->id]);
    }

    public function test_stock_is_allocated_from_the_lot_expiring_soonest_first(): void
    {
        $tenant = Tenant::factory()->create();
        $boutique = Boutique::factory()->create(['tenant_id' => $tenant->id]);
        $vendeur = User::factory()->create(['tenant_id' => $tenant->id, 'role' => UserRole::Vendeur, 'boutique_id' => $boutique->id]);
        $produit = Produit::factory()->create(['tenant_id' => $tenant->id, 'prix_vente' => 2000]);

        $lotLointain = Lot::factory()->create(['tenant_id' => $tenant->id, 'produit_id' => $produit->id, 'date_peremption' => now()->addYear()]);
        $lotProche = Lot::factory()->create(['tenant_id' => $tenant->id, 'produit_id' => $produit->id, 'date_peremption' => now()->addDays(10)]);

        StockBoutique::factory()->create(['tenant_id' => $tenant->id, 'boutique_id' => $boutique->id, 'produit_id' => $produit->id, 'lot_id' => $lotLointain->id, 'quantite' => 10]);
        $stockProche = StockBoutique::factory()->create(['tenant_id' => $tenant->id, 'boutique_id' => $boutique->id, 'produit_id' => $produit->id, 'lot_id' => $lotProche->id, 'quantite' => 5]);

        $this->actingAs($vendeur)->post('/ventes', [
            'boutique_id' => $boutique->id,
            'mode_paiement' => ModePaiement::Especes->value,
            'lignes' => [
                ['produit_id' => $produit->id, 'quantite' => 3],
            ],
        ]);

        $this->assertDatabaseHas('ligne_ventes', ['lot_id' => $lotProche->id, 'quantite' => 3]);
        $this->assertDatabaseMissing('ligne_ventes', ['lot_id' => $lotLointain->id]);
        $this->assertDatabaseHas('stock_boutiques', ['id' => $stockProche->id, 'quantite' => 2]);
    }

    public function test_a_sale_can_split_across_multiple_lots_when_one_is_insufficient(): void
    {
        $tenant = Tenant::factory()->create();
        $boutique = Boutique::factory()->create(['tenant_id' => $tenant->id]);
        $vendeur = User::factory()->create(['tenant_id' => $tenant->id, 'role' => UserRole::Vendeur, 'boutique_id' => $boutique->id]);
        $produit = Produit::factory()->create(['tenant_id' => $tenant->id]);

        $lotA = Lot::factory()->create(['tenant_id' => $tenant->id, 'produit_id' => $produit->id, 'date_peremption' => now()->addDays(5)]);
        $lotB = Lot::factory()->create(['tenant_id' => $tenant->id, 'produit_id' => $produit->id, 'date_peremption' => now()->addDays(30)]);
        StockBoutique::factory()->create(['tenant_id' => $tenant->id, 'boutique_id' => $boutique->id, 'produit_id' => $produit->id, 'lot_id' => $lotA->id, 'quantite' => 4]);
        StockBoutique::factory()->create(['tenant_id' => $tenant->id, 'boutique_id' => $boutique->id, 'produit_id' => $produit->id, 'lot_id' => $lotB->id, 'quantite' => 10]);

        $this->actingAs($vendeur)->post('/ventes', [
            'boutique_id' => $boutique->id,
            'mode_paiement' => ModePaiement::Especes->value,
            'lignes' => [
                ['produit_id' => $produit->id, 'quantite' => 6],
            ],
        ]);

        $this->assertDatabaseHas('ligne_ventes', ['lot_id' => $lotA->id, 'quantite' => 4]);
        $this->assertDatabaseHas('ligne_ventes', ['lot_id' => $lotB->id, 'quantite' => 2]);
    }

    public function test_a_comptable_cannot_register_a_sale(): void
    {
        $tenant = Tenant::factory()->create();
        $boutique = Boutique::factory()->create(['tenant_id' => $tenant->id]);
        $comptable = User::factory()->create(['tenant_id' => $tenant->id, 'role' => UserRole::Comptable]);
        $produit = Produit::factory()->create(['tenant_id' => $tenant->id]);
        $lot = Lot::factory()->create(['tenant_id' => $tenant->id, 'produit_id' => $produit->id]);
        StockBoutique::factory()->create(['tenant_id' => $tenant->id, 'boutique_id' => $boutique->id, 'produit_id' => $produit->id, 'lot_id' => $lot->id, 'quantite' => 10]);

        $response = $this->actingAs($comptable)->post('/ventes', [
            'boutique_id' => $boutique->id,
            'mode_paiement' => ModePaiement::Especes->value,
            'lignes' => [
                ['produit_id' => $produit->id, 'quantite' => 1],
            ],
        ]);

        $response->assertForbidden();
    }

    public function test_a_vendeur_cannot_sell_from_another_boutique(): void
    {
        $tenant = Tenant::factory()->create();
        $boutiqueA = Boutique::factory()->create(['tenant_id' => $tenant->id]);
        $boutiqueB = Boutique::factory()->create(['tenant_id' => $tenant->id]);
        $vendeur = User::factory()->create(['tenant_id' => $tenant->id, 'role' => UserRole::Vendeur, 'boutique_id' => $boutiqueA->id]);
        $produit = Produit::factory()->create(['tenant_id' => $tenant->id]);
        $lot = Lot::factory()->create(['tenant_id' => $tenant->id, 'produit_id' => $produit->id]);
        StockBoutique::factory()->create(['tenant_id' => $tenant->id, 'boutique_id' => $boutiqueB->id, 'produit_id' => $produit->id, 'lot_id' => $lot->id, 'quantite' => 10]);

        $response = $this->actingAs($vendeur)->post('/ventes', [
            'boutique_id' => $boutiqueB->id,
            'mode_paiement' => ModePaiement::Especes->value,
            'lignes' => [
                ['produit_id' => $produit->id, 'quantite' => 1],
            ],
        ]);

        $response->assertSessionHasErrors('boutique_id');
    }

    public function test_sales_are_scoped_to_the_tenant(): void
    {
        $tenantA = Tenant::factory()->create();
        $tenantB = Tenant::factory()->create();
        $boutiqueA = Boutique::factory()->create(['tenant_id' => $tenantA->id]);
        $boutiqueB = Boutique::factory()->create(['tenant_id' => $tenantB->id]);
        $vendeurA = User::factory()->create(['tenant_id' => $tenantA->id, 'role' => UserRole::Vendeur, 'boutique_id' => $boutiqueA->id]);

        Vente::factory()->create(['tenant_id' => $tenantA->id, 'boutique_id' => $boutiqueA->id, 'vendeur_id' => $vendeurA->id, 'numero' => 'VT-A']);
        Vente::factory()->create(['tenant_id' => $tenantB->id, 'boutique_id' => $boutiqueB->id, 'numero' => 'VT-B']);

        $response = $this->actingAs($vendeurA)->get('/ventes');

        $response->assertOk();
        $response->assertSee('VT-A');
        $response->assertDontSee('VT-B');
    }

    public function test_a_patron_can_create_a_client(): void
    {
        $tenant = Tenant::factory()->create();
        $patron = User::factory()->create(['tenant_id' => $tenant->id, 'role' => UserRole::Patron]);

        $response = $this->actingAs($patron)->post('/clients', [
            'nom' => 'Coopérative AgroSud',
            'type' => TypeClient::Cooperative->value,
        ]);

        $response->assertRedirect(route('clients.index'));
        $this->assertDatabaseHas('clients', ['nom' => 'Coopérative AgroSud', 'tenant_id' => $tenant->id]);
    }

    public function test_the_invoice_shows_the_shop_products_lot_and_payment_status(): void
    {
        $tenant = Tenant::factory()->create();
        $boutique = Boutique::factory()->create(['tenant_id' => $tenant->id, 'nom' => 'AgroPlus Centre-ville']);
        $vendeur = User::factory()->create(['tenant_id' => $tenant->id, 'role' => UserRole::Vendeur, 'boutique_id' => $boutique->id]);
        $client = Client::factory()->create(['tenant_id' => $tenant->id, 'plafond_credit' => 100000]);
        $produit = Produit::factory()->create(['tenant_id' => $tenant->id, 'nom' => 'Glyphosate 1L', 'prix_vente' => 6500]);
        $lot = Lot::factory()->create(['tenant_id' => $tenant->id, 'produit_id' => $produit->id, 'numero_lot' => 'LOT-2026-001']);
        StockBoutique::factory()->create(['tenant_id' => $tenant->id, 'boutique_id' => $boutique->id, 'produit_id' => $produit->id, 'lot_id' => $lot->id, 'quantite' => 10]);

        $venteResponse = $this->actingAs($vendeur)->post('/ventes', [
            'boutique_id' => $boutique->id,
            'client_id' => $client->id,
            'mode_paiement' => ModePaiement::Especes->value,
            'montant_paye' => 5000,
            'date_echeance' => now()->addDays(15)->toDateString(),
            'lignes' => [
                ['produit_id' => $produit->id, 'quantite' => 1],
            ],
        ]);
        $venteResponse->assertSessionDoesntHaveErrors();

        $vente = Vente::latest()->first();

        $response = $this->actingAs($vendeur)->get(route('ventes.facture', $vente));

        $response->assertOk();
        $response->assertSee('AgroPlus Centre-ville');
        $response->assertSee('Glyphosate 1L');
        $response->assertSee('LOT-2026-001');
        $response->assertSee('1 500 FCFA');
        $response->assertSee('Reste à payer');
    }

    public function test_a_fully_paid_invoice_is_marked_as_paid_in_full(): void
    {
        $tenant = Tenant::factory()->create();
        $boutique = Boutique::factory()->create(['tenant_id' => $tenant->id]);
        $vendeur = User::factory()->create(['tenant_id' => $tenant->id, 'role' => UserRole::Vendeur, 'boutique_id' => $boutique->id]);
        $produit = Produit::factory()->create(['tenant_id' => $tenant->id, 'prix_vente' => 5000]);
        $lot = Lot::factory()->create(['tenant_id' => $tenant->id, 'produit_id' => $produit->id]);
        StockBoutique::factory()->create(['tenant_id' => $tenant->id, 'boutique_id' => $boutique->id, 'produit_id' => $produit->id, 'lot_id' => $lot->id, 'quantite' => 10]);

        $this->actingAs($vendeur)->post('/ventes', [
            'boutique_id' => $boutique->id,
            'mode_paiement' => ModePaiement::Especes->value,
            'lignes' => [
                ['produit_id' => $produit->id, 'quantite' => 1],
            ],
        ])->assertRedirect();

        $vente = Vente::latest()->first();

        $response = $this->actingAs($vendeur)->get(route('ventes.facture', $vente));

        $response->assertOk();
        $response->assertSee('Payé comptant');
    }

    public function test_a_vendeur_from_another_boutique_cannot_view_the_invoice(): void
    {
        $tenant = Tenant::factory()->create();
        $boutiqueA = Boutique::factory()->create(['tenant_id' => $tenant->id]);
        $boutiqueB = Boutique::factory()->create(['tenant_id' => $tenant->id]);
        $vendeurB = User::factory()->create(['tenant_id' => $tenant->id, 'role' => UserRole::Vendeur, 'boutique_id' => $boutiqueB->id]);
        $vente = Vente::factory()->create(['tenant_id' => $tenant->id, 'boutique_id' => $boutiqueA->id]);

        $response = $this->actingAs($vendeurB)->get(route('ventes.facture', $vente));

        $response->assertForbidden();
    }
}
