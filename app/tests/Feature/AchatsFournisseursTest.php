<?php

namespace Tests\Feature;

use App\Enums\TypeProduit;
use App\Enums\UniteMesure;
use App\Enums\UserRole;
use App\Models\BonCommande;
use App\Models\Boutique;
use App\Models\Fournisseur;
use App\Models\Produit;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AchatsFournisseursTest extends TestCase
{
    use RefreshDatabase;

    private function tenantAvecBoutique(): array
    {
        $tenant = Tenant::factory()->create();
        $boutique = Boutique::factory()->create(['tenant_id' => $tenant->id]);

        return [$tenant, $boutique];
    }

    public function test_a_patron_can_create_a_supplier(): void
    {
        [$tenant] = $this->tenantAvecBoutique();
        $patron = User::factory()->create(['tenant_id' => $tenant->id, 'role' => UserRole::Patron]);

        $response = $this->actingAs($patron)->post('/fournisseurs', [
            'nom' => 'AgroImport SARL',
            'contact' => 'M. Kouassi',
            'telephone' => '+225 27 00 00 00 00',
        ]);

        $response->assertRedirect(route('fournisseurs.index'));
        $this->assertDatabaseHas('fournisseurs', ['nom' => 'AgroImport SARL', 'tenant_id' => $tenant->id]);
    }

    public function test_a_patron_can_create_a_purchase_order_with_lines(): void
    {
        [$tenant, $boutique] = $this->tenantAvecBoutique();
        $patron = User::factory()->create(['tenant_id' => $tenant->id, 'role' => UserRole::Patron]);
        $fournisseur = Fournisseur::factory()->create(['tenant_id' => $tenant->id]);
        $produit = Produit::factory()->create(['tenant_id' => $tenant->id, 'type' => TypeProduit::IntrantAgricole]);

        $response = $this->actingAs($patron)->post('/achats', [
            'boutique_id' => $boutique->id,
            'fournisseur_id' => $fournisseur->id,
            'lignes' => [
                ['produit_id' => $produit->id, 'quantite' => 20, 'prix_unitaire' => 12000],
            ],
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('bon_commandes', [
            'tenant_id' => $tenant->id,
            'boutique_id' => $boutique->id,
            'statut' => 'brouillon',
        ]);
        $this->assertDatabaseHas('ligne_bon_commandes', [
            'produit_id' => $produit->id,
            'quantite' => 20,
            'prix_unitaire' => 12000,
        ]);
    }

    public function test_a_gerant_cannot_order_for_another_boutique(): void
    {
        [$tenant, $boutiqueA] = $this->tenantAvecBoutique();
        $boutiqueB = Boutique::factory()->create(['tenant_id' => $tenant->id]);
        $gerant = User::factory()->create(['tenant_id' => $tenant->id, 'role' => UserRole::Gerant, 'boutique_id' => $boutiqueA->id]);
        $fournisseur = Fournisseur::factory()->create(['tenant_id' => $tenant->id]);
        $produit = Produit::factory()->create(['tenant_id' => $tenant->id]);

        $response = $this->actingAs($gerant)->post('/achats', [
            'boutique_id' => $boutiqueB->id,
            'fournisseur_id' => $fournisseur->id,
            'lignes' => [
                ['produit_id' => $produit->id, 'quantite' => 5, 'prix_unitaire' => 1000],
            ],
        ]);

        $response->assertSessionHasErrors('boutique_id');
    }

    public function test_a_vendeur_cannot_create_a_purchase_order(): void
    {
        [$tenant, $boutique] = $this->tenantAvecBoutique();
        $vendeur = User::factory()->create(['tenant_id' => $tenant->id, 'role' => UserRole::Vendeur, 'boutique_id' => $boutique->id]);
        $fournisseur = Fournisseur::factory()->create(['tenant_id' => $tenant->id]);
        $produit = Produit::factory()->create(['tenant_id' => $tenant->id]);

        $response = $this->actingAs($vendeur)->post('/achats', [
            'boutique_id' => $boutique->id,
            'fournisseur_id' => $fournisseur->id,
            'lignes' => [
                ['produit_id' => $produit->id, 'quantite' => 5, 'prix_unitaire' => 1000],
            ],
        ]);

        $response->assertForbidden();
    }

    public function test_receiving_a_purchase_order_creates_a_lot_and_updates_stock(): void
    {
        [$tenant, $boutique] = $this->tenantAvecBoutique();
        $patron = User::factory()->create(['tenant_id' => $tenant->id, 'role' => UserRole::Patron]);
        $bonCommande = BonCommande::factory()->create(['tenant_id' => $tenant->id, 'boutique_id' => $boutique->id]);
        $produit = Produit::factory()->create(['tenant_id' => $tenant->id, 'type' => TypeProduit::IntrantAgricole, 'unite_mesure' => UniteMesure::Sac]);
        $ligne = $bonCommande->lignes()->create(['produit_id' => $produit->id, 'quantite' => 15, 'prix_unitaire' => 8000]);

        $response = $this->actingAs($patron)->post("/achats/{$bonCommande->id}/receptionner", [
            'lignes' => [
                $ligne->id => [
                    'numero_lot' => 'LOT-2026-08-A',
                    'date_fabrication' => '2026-06-01',
                    'date_peremption' => '2027-06-01',
                ],
            ],
        ]);

        $response->assertRedirect(route('achats.show', $bonCommande));
        $this->assertDatabaseHas('bon_commandes', ['id' => $bonCommande->id, 'statut' => 'recu']);
        $this->assertDatabaseHas('lots', ['produit_id' => $produit->id, 'numero_lot' => 'LOT-2026-08-A']);
        $this->assertDatabaseHas('stock_boutiques', [
            'boutique_id' => $boutique->id,
            'produit_id' => $produit->id,
            'quantite' => 15,
        ]);
    }

    public function test_phytosanitary_products_require_an_expiry_date_at_reception(): void
    {
        [$tenant, $boutique] = $this->tenantAvecBoutique();
        $patron = User::factory()->create(['tenant_id' => $tenant->id, 'role' => UserRole::Patron]);
        $bonCommande = BonCommande::factory()->create(['tenant_id' => $tenant->id, 'boutique_id' => $boutique->id]);
        $produit = Produit::factory()->create(['tenant_id' => $tenant->id, 'type' => TypeProduit::ProduitPhytosanitaire]);
        $ligne = $bonCommande->lignes()->create(['produit_id' => $produit->id, 'quantite' => 10, 'prix_unitaire' => 5000]);

        $response = $this->actingAs($patron)->post("/achats/{$bonCommande->id}/receptionner", [
            'lignes' => [
                $ligne->id => [
                    'numero_lot' => 'LOT-PHYTO-01',
                ],
            ],
        ]);

        $response->assertSessionHasErrors("lignes.{$ligne->id}.date_peremption");
        $this->assertDatabaseHas('bon_commandes', ['id' => $bonCommande->id, 'statut' => 'brouillon']);
    }

    public function test_a_received_purchase_order_cannot_be_received_again(): void
    {
        [$tenant, $boutique] = $this->tenantAvecBoutique();
        $patron = User::factory()->create(['tenant_id' => $tenant->id, 'role' => UserRole::Patron]);
        $bonCommande = BonCommande::factory()->create([
            'tenant_id' => $tenant->id,
            'boutique_id' => $boutique->id,
            'statut' => 'recu',
        ]);

        $response = $this->actingAs($patron)->get("/achats/{$bonCommande->id}/receptionner");

        $response->assertForbidden();
    }

    public function test_purchase_orders_are_scoped_to_the_tenant(): void
    {
        [$tenantA, $boutiqueA] = $this->tenantAvecBoutique();
        [$tenantB, $boutiqueB] = $this->tenantAvecBoutique();

        $patronA = User::factory()->create(['tenant_id' => $tenantA->id, 'role' => UserRole::Patron]);
        $fournisseurA = Fournisseur::factory()->create(['tenant_id' => $tenantA->id]);
        $fournisseurB = Fournisseur::factory()->create(['tenant_id' => $tenantB->id]);
        BonCommande::factory()->create(['tenant_id' => $tenantA->id, 'boutique_id' => $boutiqueA->id, 'fournisseur_id' => $fournisseurA->id, 'numero' => 'BC-A']);
        BonCommande::factory()->create(['tenant_id' => $tenantB->id, 'boutique_id' => $boutiqueB->id, 'fournisseur_id' => $fournisseurB->id, 'numero' => 'BC-B']);

        $response = $this->actingAs($patronA)->get('/achats');

        $response->assertOk();
        $response->assertSee('BC-A');
        $response->assertDontSee('BC-B');
    }
}
