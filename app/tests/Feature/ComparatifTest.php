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

class ComparatifTest extends TestCase
{
    use RefreshDatabase;

    private function vendre(Tenant $tenant, Boutique $boutique, User $vendeur, Produit $produit, int $quantite): void
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

    public function test_a_patron_sees_boutiques_ranked_by_revenue(): void
    {
        $tenant = Tenant::factory()->create();
        $patron = User::factory()->create(['tenant_id' => $tenant->id, 'role' => UserRole::Patron]);

        $boutiqueForte = Boutique::factory()->create(['tenant_id' => $tenant->id, 'nom' => 'Boutique Forte']);
        $vendeurForte = User::factory()->create(['tenant_id' => $tenant->id, 'role' => UserRole::Vendeur, 'boutique_id' => $boutiqueForte->id]);
        $produit = Produit::factory()->create(['tenant_id' => $tenant->id, 'prix_vente' => 10000, 'prix_achat' => 6000]);
        $this->vendre($tenant, $boutiqueForte, $vendeurForte, $produit, 10);

        $boutiqueFaible = Boutique::factory()->create(['tenant_id' => $tenant->id, 'nom' => 'Boutique Faible']);
        $vendeurFaible = User::factory()->create(['tenant_id' => $tenant->id, 'role' => UserRole::Vendeur, 'boutique_id' => $boutiqueFaible->id]);
        $this->vendre($tenant, $boutiqueFaible, $vendeurFaible, $produit, 1);

        $response = $this->actingAs($patron)->get('/comparatif');

        $response->assertOk();
        $response->assertSeeInOrder(['Boutique Forte', 'Boutique Faible']);
        $response->assertSee('100 000 FCFA');
        $response->assertSee('40 000 FCFA');
    }

    public function test_a_gerant_cannot_access_the_comparison(): void
    {
        $tenant = Tenant::factory()->create();
        $boutique = Boutique::factory()->create(['tenant_id' => $tenant->id]);
        $gerant = User::factory()->create(['tenant_id' => $tenant->id, 'role' => UserRole::Gerant, 'boutique_id' => $boutique->id]);

        $response = $this->actingAs($gerant)->get('/comparatif');

        $response->assertForbidden();
    }

    public function test_a_comptable_can_access_the_comparison(): void
    {
        $tenant = Tenant::factory()->create();
        $comptable = User::factory()->create(['tenant_id' => $tenant->id, 'role' => UserRole::Comptable]);

        $response = $this->actingAs($comptable)->get('/comparatif');

        $response->assertOk();
    }
}
