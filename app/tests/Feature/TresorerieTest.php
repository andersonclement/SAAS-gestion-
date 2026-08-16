<?php

namespace Tests\Feature;

use App\Enums\CategorieDepense;
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

class TresorerieTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_patron_can_record_an_expense(): void
    {
        $tenant = Tenant::factory()->create();
        $boutique = Boutique::factory()->create(['tenant_id' => $tenant->id]);
        $patron = User::factory()->create(['tenant_id' => $tenant->id, 'role' => UserRole::Patron]);

        $response = $this->actingAs($patron)->post('/depenses', [
            'boutique_id' => $boutique->id,
            'categorie' => CategorieDepense::Loyer->value,
            'montant' => 50000,
            'date' => now()->toDateString(),
            'description' => 'Loyer du mois',
        ]);

        $response->assertRedirect(route('depenses.index'));
        $this->assertDatabaseHas('depenses', ['tenant_id' => $tenant->id, 'boutique_id' => $boutique->id, 'montant' => 50000]);
    }

    public function test_a_vendeur_cannot_record_an_expense(): void
    {
        $tenant = Tenant::factory()->create();
        $boutique = Boutique::factory()->create(['tenant_id' => $tenant->id]);
        $vendeur = User::factory()->create(['tenant_id' => $tenant->id, 'role' => UserRole::Vendeur, 'boutique_id' => $boutique->id]);

        $response = $this->actingAs($vendeur)->post('/depenses', [
            'boutique_id' => $boutique->id,
            'categorie' => CategorieDepense::Transport->value,
            'montant' => 5000,
            'date' => now()->toDateString(),
        ]);

        $response->assertForbidden();
    }

    public function test_a_gerant_cannot_record_an_expense_for_another_boutique(): void
    {
        $tenant = Tenant::factory()->create();
        $boutiqueA = Boutique::factory()->create(['tenant_id' => $tenant->id]);
        $boutiqueB = Boutique::factory()->create(['tenant_id' => $tenant->id]);
        $gerant = User::factory()->create(['tenant_id' => $tenant->id, 'role' => UserRole::Gerant, 'boutique_id' => $boutiqueA->id]);

        $response = $this->actingAs($gerant)->post('/depenses', [
            'boutique_id' => $boutiqueB->id,
            'categorie' => CategorieDepense::Transport->value,
            'montant' => 5000,
            'date' => now()->toDateString(),
        ]);

        $response->assertSessionHasErrors('boutique_id');
    }

    public function test_the_daily_cash_balance_deducts_expenses_from_takings(): void
    {
        $tenant = Tenant::factory()->create();
        $boutique = Boutique::factory()->create(['tenant_id' => $tenant->id]);
        $patron = User::factory()->create(['tenant_id' => $tenant->id, 'role' => UserRole::Patron]);
        $vendeur = User::factory()->create(['tenant_id' => $tenant->id, 'role' => UserRole::Vendeur, 'boutique_id' => $boutique->id]);
        $produit = Produit::factory()->create(['tenant_id' => $tenant->id, 'prix_vente' => 10000]);
        $lot = Lot::factory()->create(['tenant_id' => $tenant->id, 'produit_id' => $produit->id]);
        StockBoutique::factory()->create(['tenant_id' => $tenant->id, 'boutique_id' => $boutique->id, 'produit_id' => $produit->id, 'lot_id' => $lot->id, 'quantite' => 20]);

        $this->actingAs($vendeur)->post('/ventes', [
            'boutique_id' => $boutique->id,
            'mode_paiement' => ModePaiement::Especes->value,
            'lignes' => [
                ['produit_id' => $produit->id, 'quantite' => 3],
            ],
        ])->assertRedirect();

        $this->actingAs($patron)->post('/depenses', [
            'boutique_id' => $boutique->id,
            'categorie' => CategorieDepense::Transport->value,
            'montant' => 8000,
            'date' => now()->toDateString(),
        ])->assertRedirect();

        $response = $this->actingAs($patron)->get('/tresorerie');

        $response->assertOk();
        $response->assertSee('30 000 FCFA');
        $response->assertSee('8 000 FCFA');
        $response->assertSee('22 000 FCFA');
    }

    public function test_net_profit_subtracts_purchases_and_expenses_from_sales(): void
    {
        $tenant = Tenant::factory()->create();
        $boutique = Boutique::factory()->create(['tenant_id' => $tenant->id]);
        $patron = User::factory()->create(['tenant_id' => $tenant->id, 'role' => UserRole::Patron]);
        $vendeur = User::factory()->create(['tenant_id' => $tenant->id, 'role' => UserRole::Vendeur, 'boutique_id' => $boutique->id]);
        $produit = Produit::factory()->create(['tenant_id' => $tenant->id, 'prix_vente' => 20000]);
        $lot = Lot::factory()->create(['tenant_id' => $tenant->id, 'produit_id' => $produit->id]);
        StockBoutique::factory()->create(['tenant_id' => $tenant->id, 'boutique_id' => $boutique->id, 'produit_id' => $produit->id, 'lot_id' => $lot->id, 'quantite' => 20]);

        $this->actingAs($vendeur)->post('/ventes', [
            'boutique_id' => $boutique->id,
            'mode_paiement' => ModePaiement::Especes->value,
            'lignes' => [
                ['produit_id' => $produit->id, 'quantite' => 5],
            ],
        ])->assertRedirect();

        $this->actingAs($patron)->post('/depenses', [
            'boutique_id' => $boutique->id,
            'categorie' => CategorieDepense::Salaires->value,
            'montant' => 30000,
            'date' => now()->toDateString(),
        ])->assertRedirect();

        $fournisseur = Fournisseur::factory()->create(['tenant_id' => $tenant->id]);
        $bonCommande = BonCommande::factory()->create(['tenant_id' => $tenant->id, 'boutique_id' => $boutique->id, 'fournisseur_id' => $fournisseur->id, 'statut' => 'recu', 'recu_le' => now()]);
        $bonCommande->lignes()->create(['produit_id' => $produit->id, 'quantite' => 10, 'prix_unitaire' => 12000]);

        $response = $this->actingAs($patron)->get('/tresorerie');

        $response->assertOk();
        // ventes 100 000, achats 120 000, dépenses 30 000 => bénéfice net = -50 000
        $response->assertSee('-50 000 FCFA');
    }

    public function test_a_gerant_only_sees_treasury_for_his_own_boutique(): void
    {
        $tenant = Tenant::factory()->create();
        $boutiqueA = Boutique::factory()->create(['tenant_id' => $tenant->id]);
        $boutiqueB = Boutique::factory()->create(['tenant_id' => $tenant->id]);
        $gerantA = User::factory()->create(['tenant_id' => $tenant->id, 'role' => UserRole::Gerant, 'boutique_id' => $boutiqueA->id]);
        $patron = User::factory()->create(['tenant_id' => $tenant->id, 'role' => UserRole::Patron]);

        $this->actingAs($patron)->post('/depenses', [
            'boutique_id' => $boutiqueB->id,
            'categorie' => CategorieDepense::Loyer->value,
            'montant' => 15000,
            'date' => now()->toDateString(),
        ])->assertRedirect();

        $response = $this->actingAs($gerantA)->get('/depenses');

        $response->assertOk();
        $response->assertDontSee('15 000 FCFA');
    }
}
