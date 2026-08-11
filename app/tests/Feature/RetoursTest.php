<?php

namespace Tests\Feature;

use App\Enums\ModePaiement;
use App\Enums\ModeRemboursement;
use App\Enums\MotifRetour;
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

class RetoursTest extends TestCase
{
    use RefreshDatabase;

    private function creerVente(Tenant $tenant, Boutique $boutique, User $vendeur, Produit $produit, int $quantite): Vente
    {
        $lot = Lot::factory()->create(['tenant_id' => $tenant->id, 'produit_id' => $produit->id]);
        StockBoutique::factory()->create([
            'tenant_id' => $tenant->id,
            'boutique_id' => $boutique->id,
            'produit_id' => $produit->id,
            'lot_id' => $lot->id,
            'quantite' => $quantite + 20,
        ]);

        $this->actingAs($vendeur)->post('/ventes', [
            'boutique_id' => $boutique->id,
            'mode_paiement' => ModePaiement::Especes->value,
            'lignes' => [
                ['produit_id' => $produit->id, 'quantite' => $quantite],
            ],
        ])->assertRedirect();

        return Vente::where('boutique_id', $boutique->id)->latest()->firstOrFail();
    }

    public function test_a_return_with_restocking_increases_stock_again(): void
    {
        $tenant = Tenant::factory()->create();
        $boutique = Boutique::factory()->create(['tenant_id' => $tenant->id]);
        $vendeur = User::factory()->create(['tenant_id' => $tenant->id, 'role' => UserRole::Vendeur, 'boutique_id' => $boutique->id]);
        $produit = Produit::factory()->create(['tenant_id' => $tenant->id, 'prix_vente' => 3000]);
        $vente = $this->creerVente($tenant, $boutique, $vendeur, $produit, 5);
        $ligne = $vente->lignes()->first();

        $stockAvant = StockBoutique::where('lot_id', $ligne->lot_id)->first()->quantite;

        $response = $this->actingAs($vendeur)->post("/lignes-vente/{$ligne->id}/retour", [
            'quantite' => 2,
            'motif' => MotifRetour::ErreurCommande->value,
            'remis_en_stock' => 1,
            'mode_remboursement' => ModeRemboursement::Especes->value,
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('retours', [
            'ligne_vente_id' => $ligne->id,
            'quantite' => 2,
            'remis_en_stock' => 1,
            'montant_avoir' => 6000,
        ]);
        $this->assertSame($stockAvant + 2, StockBoutique::where('lot_id', $ligne->lot_id)->first()->quantite);
    }

    public function test_a_defective_return_without_restocking_does_not_change_stock(): void
    {
        $tenant = Tenant::factory()->create();
        $boutique = Boutique::factory()->create(['tenant_id' => $tenant->id]);
        $vendeur = User::factory()->create(['tenant_id' => $tenant->id, 'role' => UserRole::Vendeur, 'boutique_id' => $boutique->id]);
        $produit = Produit::factory()->create(['tenant_id' => $tenant->id, 'prix_vente' => 2000]);
        $vente = $this->creerVente($tenant, $boutique, $vendeur, $produit, 4);
        $ligne = $vente->lignes()->first();

        $stockAvant = StockBoutique::where('lot_id', $ligne->lot_id)->first()->quantite;

        $this->actingAs($vendeur)->post("/lignes-vente/{$ligne->id}/retour", [
            'quantite' => 1,
            'motif' => MotifRetour::Defectueux->value,
            'mode_remboursement' => ModeRemboursement::AvoirClient->value,
        ])->assertRedirect();

        $this->assertSame($stockAvant, StockBoutique::where('lot_id', $ligne->lot_id)->first()->quantite);
    }

    public function test_a_return_cannot_exceed_the_sold_quantity(): void
    {
        $tenant = Tenant::factory()->create();
        $boutique = Boutique::factory()->create(['tenant_id' => $tenant->id]);
        $vendeur = User::factory()->create(['tenant_id' => $tenant->id, 'role' => UserRole::Vendeur, 'boutique_id' => $boutique->id]);
        $produit = Produit::factory()->create(['tenant_id' => $tenant->id]);
        $vente = $this->creerVente($tenant, $boutique, $vendeur, $produit, 3);
        $ligne = $vente->lignes()->first();

        $response = $this->actingAs($vendeur)->post("/lignes-vente/{$ligne->id}/retour", [
            'quantite' => 10,
            'motif' => MotifRetour::Autre->value,
            'mode_remboursement' => ModeRemboursement::Especes->value,
        ]);

        $response->assertSessionHasErrors('quantite');
    }

    public function test_returns_cannot_be_double_counted_beyond_what_was_sold(): void
    {
        $tenant = Tenant::factory()->create();
        $boutique = Boutique::factory()->create(['tenant_id' => $tenant->id]);
        $vendeur = User::factory()->create(['tenant_id' => $tenant->id, 'role' => UserRole::Vendeur, 'boutique_id' => $boutique->id]);
        $produit = Produit::factory()->create(['tenant_id' => $tenant->id]);
        $vente = $this->creerVente($tenant, $boutique, $vendeur, $produit, 3);
        $ligne = $vente->lignes()->first();

        $this->actingAs($vendeur)->post("/lignes-vente/{$ligne->id}/retour", [
            'quantite' => 3,
            'motif' => MotifRetour::Autre->value,
            'mode_remboursement' => ModeRemboursement::Especes->value,
        ])->assertRedirect();

        $response = $this->actingAs($vendeur)->post("/lignes-vente/{$ligne->id}/retour", [
            'quantite' => 1,
            'motif' => MotifRetour::Autre->value,
            'mode_remboursement' => ModeRemboursement::Especes->value,
        ]);

        $response->assertSessionHasErrors('quantite');
    }

    public function test_a_comptable_cannot_record_a_return(): void
    {
        $tenant = Tenant::factory()->create();
        $boutique = Boutique::factory()->create(['tenant_id' => $tenant->id]);
        $vendeur = User::factory()->create(['tenant_id' => $tenant->id, 'role' => UserRole::Vendeur, 'boutique_id' => $boutique->id]);
        $comptable = User::factory()->create(['tenant_id' => $tenant->id, 'role' => UserRole::Comptable]);
        $produit = Produit::factory()->create(['tenant_id' => $tenant->id]);
        $vente = $this->creerVente($tenant, $boutique, $vendeur, $produit, 2);
        $ligne = $vente->lignes()->first();

        $response = $this->actingAs($comptable)->post("/lignes-vente/{$ligne->id}/retour", [
            'quantite' => 1,
            'motif' => MotifRetour::Autre->value,
            'mode_remboursement' => ModeRemboursement::Especes->value,
        ]);

        $response->assertForbidden();
    }
}
