<?php

namespace Tests\Feature;

use App\Enums\ModePaiement;
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

class CreditClientsTest extends TestCase
{
    use RefreshDatabase;

    private function preparerStock(Tenant $tenant, Boutique $boutique, Produit $produit, int $quantite): void
    {
        $lot = Lot::factory()->create(['tenant_id' => $tenant->id, 'produit_id' => $produit->id]);
        StockBoutique::factory()->create([
            'tenant_id' => $tenant->id,
            'boutique_id' => $boutique->id,
            'produit_id' => $produit->id,
            'lot_id' => $lot->id,
            'quantite' => $quantite,
        ]);
    }

    public function test_a_credit_sale_requires_a_client_and_a_due_date(): void
    {
        $tenant = Tenant::factory()->create();
        $boutique = Boutique::factory()->create(['tenant_id' => $tenant->id]);
        $vendeur = User::factory()->create(['tenant_id' => $tenant->id, 'role' => UserRole::Vendeur, 'boutique_id' => $boutique->id]);
        $produit = Produit::factory()->create(['tenant_id' => $tenant->id, 'prix_vente' => 5000]);
        $this->preparerStock($tenant, $boutique, $produit, 20);

        $response = $this->actingAs($vendeur)->post('/ventes', [
            'boutique_id' => $boutique->id,
            'mode_paiement' => ModePaiement::Especes->value,
            'montant_paye' => 0,
            'lignes' => [
                ['produit_id' => $produit->id, 'quantite' => 2],
            ],
        ]);

        $response->assertSessionHasErrors(['client_id', 'date_echeance']);
    }

    public function test_a_credit_sale_within_the_credit_limit_succeeds(): void
    {
        $tenant = Tenant::factory()->create();
        $boutique = Boutique::factory()->create(['tenant_id' => $tenant->id]);
        $vendeur = User::factory()->create(['tenant_id' => $tenant->id, 'role' => UserRole::Vendeur, 'boutique_id' => $boutique->id]);
        $client = Client::factory()->create(['tenant_id' => $tenant->id, 'plafond_credit' => 50000]);
        $produit = Produit::factory()->create(['tenant_id' => $tenant->id, 'prix_vente' => 5000]);
        $this->preparerStock($tenant, $boutique, $produit, 20);

        $response = $this->actingAs($vendeur)->post('/ventes', [
            'boutique_id' => $boutique->id,
            'client_id' => $client->id,
            'mode_paiement' => ModePaiement::Especes->value,
            'montant_paye' => 4000,
            'date_echeance' => now()->addDays(15)->toDateString(),
            'lignes' => [
                ['produit_id' => $produit->id, 'quantite' => 2],
            ],
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('paiements', ['montant' => 4000]);
        $this->assertSame(6000, $client->fresh()->soldeDette());
    }

    public function test_a_credit_sale_exceeding_the_credit_limit_is_rejected(): void
    {
        $tenant = Tenant::factory()->create();
        $boutique = Boutique::factory()->create(['tenant_id' => $tenant->id]);
        $vendeur = User::factory()->create(['tenant_id' => $tenant->id, 'role' => UserRole::Vendeur, 'boutique_id' => $boutique->id]);
        $client = Client::factory()->create(['tenant_id' => $tenant->id, 'plafond_credit' => 5000]);
        $produit = Produit::factory()->create(['tenant_id' => $tenant->id, 'prix_vente' => 5000]);
        $this->preparerStock($tenant, $boutique, $produit, 20);

        $response = $this->actingAs($vendeur)->post('/ventes', [
            'boutique_id' => $boutique->id,
            'client_id' => $client->id,
            'mode_paiement' => ModePaiement::Especes->value,
            'montant_paye' => 0,
            'date_echeance' => now()->addDays(15)->toDateString(),
            'lignes' => [
                ['produit_id' => $produit->id, 'quantite' => 2],
            ],
        ]);

        $response->assertSessionHasErrors('montant_paye');
    }

    public function test_a_client_without_a_credit_limit_cannot_buy_on_credit(): void
    {
        $tenant = Tenant::factory()->create();
        $boutique = Boutique::factory()->create(['tenant_id' => $tenant->id]);
        $vendeur = User::factory()->create(['tenant_id' => $tenant->id, 'role' => UserRole::Vendeur, 'boutique_id' => $boutique->id]);
        $client = Client::factory()->create(['tenant_id' => $tenant->id, 'plafond_credit' => null]);
        $produit = Produit::factory()->create(['tenant_id' => $tenant->id, 'prix_vente' => 1000]);
        $this->preparerStock($tenant, $boutique, $produit, 20);

        $response = $this->actingAs($vendeur)->post('/ventes', [
            'boutique_id' => $boutique->id,
            'client_id' => $client->id,
            'mode_paiement' => ModePaiement::Especes->value,
            'montant_paye' => 0,
            'date_echeance' => now()->addDays(15)->toDateString(),
            'lignes' => [
                ['produit_id' => $produit->id, 'quantite' => 1],
            ],
        ]);

        $response->assertSessionHasErrors('client_id');
    }

    public function test_a_partial_payment_reduces_the_outstanding_balance(): void
    {
        $tenant = Tenant::factory()->create();
        $boutique = Boutique::factory()->create(['tenant_id' => $tenant->id]);
        $vendeur = User::factory()->create(['tenant_id' => $tenant->id, 'role' => UserRole::Vendeur, 'boutique_id' => $boutique->id]);
        $client = Client::factory()->create(['tenant_id' => $tenant->id, 'plafond_credit' => 50000]);
        $produit = Produit::factory()->create(['tenant_id' => $tenant->id, 'prix_vente' => 10000]);
        $this->preparerStock($tenant, $boutique, $produit, 20);

        $this->actingAs($vendeur)->post('/ventes', [
            'boutique_id' => $boutique->id,
            'client_id' => $client->id,
            'mode_paiement' => ModePaiement::Especes->value,
            'montant_paye' => 0,
            'date_echeance' => now()->addDays(15)->toDateString(),
            'lignes' => [
                ['produit_id' => $produit->id, 'quantite' => 1],
            ],
        ]);

        $vente = $client->fresh()->ventes()->first();
        $this->assertSame(10000, $vente->montantDu());

        $response = $this->actingAs($vendeur)->post("/ventes/{$vente->id}/reglements", [
            'mode' => ModePaiement::MobileMoney->value,
            'montant' => 6000,
        ]);

        $response->assertRedirect(route('ventes.show', $vente));
        $this->assertSame(4000, $vente->fresh()->montantDu());
        $this->assertSame(4000, $client->fresh()->soldeDette());
    }

    public function test_a_payment_exceeding_the_balance_due_is_rejected(): void
    {
        $tenant = Tenant::factory()->create();
        $boutique = Boutique::factory()->create(['tenant_id' => $tenant->id]);
        $vendeur = User::factory()->create(['tenant_id' => $tenant->id, 'role' => UserRole::Vendeur, 'boutique_id' => $boutique->id]);
        $client = Client::factory()->create(['tenant_id' => $tenant->id, 'plafond_credit' => 50000]);
        $produit = Produit::factory()->create(['tenant_id' => $tenant->id, 'prix_vente' => 1000]);
        $this->preparerStock($tenant, $boutique, $produit, 20);

        $this->actingAs($vendeur)->post('/ventes', [
            'boutique_id' => $boutique->id,
            'client_id' => $client->id,
            'mode_paiement' => ModePaiement::Especes->value,
            'montant_paye' => 0,
            'date_echeance' => now()->addDays(15)->toDateString(),
            'lignes' => [
                ['produit_id' => $produit->id, 'quantite' => 1],
            ],
        ]);

        $vente = $client->fresh()->ventes()->first();

        $response = $this->actingAs($vendeur)->post("/ventes/{$vente->id}/reglements", [
            'mode' => ModePaiement::Especes->value,
            'montant' => 5000,
        ]);

        $response->assertSessionHasErrors('montant');
    }

    public function test_an_overdue_credit_sale_is_flagged(): void
    {
        $tenant = Tenant::factory()->create();
        $boutique = Boutique::factory()->create(['tenant_id' => $tenant->id]);
        $patron = User::factory()->create(['tenant_id' => $tenant->id, 'role' => UserRole::Patron]);
        $client = Client::factory()->create(['tenant_id' => $tenant->id, 'plafond_credit' => 50000]);
        $vente = Vente::factory()->create([
            'tenant_id' => $tenant->id,
            'boutique_id' => $boutique->id,
            'client_id' => $client->id,
            'date_echeance' => now()->subDays(5),
        ]);
        $produit = Produit::factory()->create(['tenant_id' => $tenant->id]);
        $lot = Lot::factory()->create(['tenant_id' => $tenant->id, 'produit_id' => $produit->id]);
        $vente->lignes()->create(['produit_id' => $produit->id, 'lot_id' => $lot->id, 'quantite' => 1, 'prix_unitaire' => 1000]);

        $response = $this->actingAs($patron)->get(route('clients.show', $client));

        $response->assertOk();
        $response->assertSee(__('En retard'));
    }
}
