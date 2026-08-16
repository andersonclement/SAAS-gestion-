<?php

namespace Tests\Feature;

use App\Enums\ModePaiement;
use App\Enums\PorteePromotion;
use App\Enums\TypePromotion;
use App\Enums\UserRole;
use App\Models\Boutique;
use App\Models\Categorie;
use App\Models\Client;
use App\Models\Lot;
use App\Models\Produit;
use App\Models\Promotion;
use App\Models\StockBoutique;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PromotionsTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_patron_can_create_a_promotion(): void
    {
        $tenant = Tenant::factory()->create();
        $patron = User::factory()->create(['tenant_id' => $tenant->id, 'role' => UserRole::Patron]);

        $response = $this->actingAs($patron)->post('/promotions', [
            'nom' => 'Campagne de semis',
            'type' => TypePromotion::Pourcentage->value,
            'valeur' => 10,
            'portee' => PorteePromotion::Globale->value,
            'date_debut' => now()->toDateString(),
            'date_fin' => now()->addMonth()->toDateString(),
        ]);

        $response->assertRedirect(route('promotions.index'));
        $this->assertDatabaseHas('promotions', ['nom' => 'Campagne de semis', 'tenant_id' => $tenant->id]);
    }

    public function test_a_gerant_cannot_create_a_promotion(): void
    {
        $tenant = Tenant::factory()->create();
        $boutique = Boutique::factory()->create(['tenant_id' => $tenant->id]);
        $gerant = User::factory()->create(['tenant_id' => $tenant->id, 'role' => UserRole::Gerant, 'boutique_id' => $boutique->id]);

        $response = $this->actingAs($gerant)->post('/promotions', [
            'nom' => 'Remise gérant',
            'type' => TypePromotion::Pourcentage->value,
            'valeur' => 10,
            'portee' => PorteePromotion::Globale->value,
            'date_debut' => now()->toDateString(),
            'date_fin' => now()->addMonth()->toDateString(),
        ]);

        $response->assertForbidden();
    }

    public function test_a_percentage_promotion_on_a_product_reduces_the_sale_price(): void
    {
        $tenant = Tenant::factory()->create();
        $boutique = Boutique::factory()->create(['tenant_id' => $tenant->id]);
        $vendeur = User::factory()->create(['tenant_id' => $tenant->id, 'role' => UserRole::Vendeur, 'boutique_id' => $boutique->id]);
        $produit = Produit::factory()->create(['tenant_id' => $tenant->id, 'prix_vente' => 10000]);
        $lot = Lot::factory()->create(['tenant_id' => $tenant->id, 'produit_id' => $produit->id]);
        StockBoutique::factory()->create(['tenant_id' => $tenant->id, 'boutique_id' => $boutique->id, 'produit_id' => $produit->id, 'lot_id' => $lot->id, 'quantite' => 10]);

        Promotion::factory()->create([
            'tenant_id' => $tenant->id,
            'type' => TypePromotion::Pourcentage,
            'valeur' => 20,
            'portee' => PorteePromotion::Produit,
            'produit_id' => $produit->id,
        ]);

        $this->actingAs($vendeur)->post('/ventes', [
            'boutique_id' => $boutique->id,
            'mode_paiement' => ModePaiement::Especes->value,
            'lignes' => [
                ['produit_id' => $produit->id, 'quantite' => 1],
            ],
        ])->assertRedirect();

        $this->assertDatabaseHas('ligne_ventes', ['produit_id' => $produit->id, 'prix_unitaire' => 8000]);
    }

    public function test_a_category_promotion_applies_to_all_its_products(): void
    {
        $tenant = Tenant::factory()->create();
        $boutique = Boutique::factory()->create(['tenant_id' => $tenant->id]);
        $vendeur = User::factory()->create(['tenant_id' => $tenant->id, 'role' => UserRole::Vendeur, 'boutique_id' => $boutique->id]);
        $categorie = Categorie::factory()->create(['tenant_id' => $tenant->id]);
        $produit = Produit::factory()->create(['tenant_id' => $tenant->id, 'categorie_id' => $categorie->id, 'prix_vente' => 5000]);
        $lot = Lot::factory()->create(['tenant_id' => $tenant->id, 'produit_id' => $produit->id]);
        StockBoutique::factory()->create(['tenant_id' => $tenant->id, 'boutique_id' => $boutique->id, 'produit_id' => $produit->id, 'lot_id' => $lot->id, 'quantite' => 10]);

        Promotion::factory()->create([
            'tenant_id' => $tenant->id,
            'type' => TypePromotion::MontantFixe,
            'valeur' => 500,
            'portee' => PorteePromotion::Categorie,
            'categorie_id' => $categorie->id,
        ]);

        $this->actingAs($vendeur)->post('/ventes', [
            'boutique_id' => $boutique->id,
            'mode_paiement' => ModePaiement::Especes->value,
            'lignes' => [
                ['produit_id' => $produit->id, 'quantite' => 1],
            ],
        ])->assertRedirect();

        $this->assertDatabaseHas('ligne_ventes', ['produit_id' => $produit->id, 'prix_unitaire' => 4500]);
    }

    public function test_a_client_specific_promotion_does_not_apply_to_other_clients(): void
    {
        $tenant = Tenant::factory()->create();
        $boutique = Boutique::factory()->create(['tenant_id' => $tenant->id]);
        $vendeur = User::factory()->create(['tenant_id' => $tenant->id, 'role' => UserRole::Vendeur, 'boutique_id' => $boutique->id]);
        $clientFidele = Client::factory()->create(['tenant_id' => $tenant->id]);
        $autreClient = Client::factory()->create(['tenant_id' => $tenant->id]);
        $produit = Produit::factory()->create(['tenant_id' => $tenant->id, 'prix_vente' => 2000]);
        $lot = Lot::factory()->create(['tenant_id' => $tenant->id, 'produit_id' => $produit->id]);
        StockBoutique::factory()->create(['tenant_id' => $tenant->id, 'boutique_id' => $boutique->id, 'produit_id' => $produit->id, 'lot_id' => $lot->id, 'quantite' => 10]);

        Promotion::factory()->create([
            'tenant_id' => $tenant->id,
            'type' => TypePromotion::Pourcentage,
            'valeur' => 50,
            'portee' => PorteePromotion::Client,
            'client_id' => $clientFidele->id,
        ]);

        $this->actingAs($vendeur)->post('/ventes', [
            'boutique_id' => $boutique->id,
            'client_id' => $autreClient->id,
            'mode_paiement' => ModePaiement::Especes->value,
            'lignes' => [
                ['produit_id' => $produit->id, 'quantite' => 1],
            ],
        ])->assertRedirect();

        $this->assertDatabaseHas('ligne_ventes', ['produit_id' => $produit->id, 'prix_unitaire' => 2000]);
    }

    public function test_an_expired_promotion_does_not_apply(): void
    {
        $tenant = Tenant::factory()->create();
        $boutique = Boutique::factory()->create(['tenant_id' => $tenant->id]);
        $vendeur = User::factory()->create(['tenant_id' => $tenant->id, 'role' => UserRole::Vendeur, 'boutique_id' => $boutique->id]);
        $produit = Produit::factory()->create(['tenant_id' => $tenant->id, 'prix_vente' => 3000]);
        $lot = Lot::factory()->create(['tenant_id' => $tenant->id, 'produit_id' => $produit->id]);
        StockBoutique::factory()->create(['tenant_id' => $tenant->id, 'boutique_id' => $boutique->id, 'produit_id' => $produit->id, 'lot_id' => $lot->id, 'quantite' => 10]);

        Promotion::factory()->create([
            'tenant_id' => $tenant->id,
            'type' => TypePromotion::Pourcentage,
            'valeur' => 30,
            'portee' => PorteePromotion::Globale,
            'date_debut' => now()->subMonths(2),
            'date_fin' => now()->subMonth(),
        ]);

        $this->actingAs($vendeur)->post('/ventes', [
            'boutique_id' => $boutique->id,
            'mode_paiement' => ModePaiement::Especes->value,
            'lignes' => [
                ['produit_id' => $produit->id, 'quantite' => 1],
            ],
        ])->assertRedirect();

        $this->assertDatabaseHas('ligne_ventes', ['produit_id' => $produit->id, 'prix_unitaire' => 3000]);
    }

    public function test_the_best_applicable_discount_is_chosen_among_several_promotions(): void
    {
        $tenant = Tenant::factory()->create();
        $boutique = Boutique::factory()->create(['tenant_id' => $tenant->id]);
        $vendeur = User::factory()->create(['tenant_id' => $tenant->id, 'role' => UserRole::Vendeur, 'boutique_id' => $boutique->id]);
        $produit = Produit::factory()->create(['tenant_id' => $tenant->id, 'prix_vente' => 10000]);
        $lot = Lot::factory()->create(['tenant_id' => $tenant->id, 'produit_id' => $produit->id]);
        StockBoutique::factory()->create(['tenant_id' => $tenant->id, 'boutique_id' => $boutique->id, 'produit_id' => $produit->id, 'lot_id' => $lot->id, 'quantite' => 10]);

        Promotion::factory()->create([
            'tenant_id' => $tenant->id,
            'type' => TypePromotion::Pourcentage,
            'valeur' => 5,
            'portee' => PorteePromotion::Globale,
        ]);
        Promotion::factory()->create([
            'tenant_id' => $tenant->id,
            'type' => TypePromotion::Pourcentage,
            'valeur' => 25,
            'portee' => PorteePromotion::Produit,
            'produit_id' => $produit->id,
        ]);

        $this->actingAs($vendeur)->post('/ventes', [
            'boutique_id' => $boutique->id,
            'mode_paiement' => ModePaiement::Especes->value,
            'lignes' => [
                ['produit_id' => $produit->id, 'quantite' => 1],
            ],
        ])->assertRedirect();

        $this->assertDatabaseHas('ligne_ventes', ['produit_id' => $produit->id, 'prix_unitaire' => 7500]);
    }
}
