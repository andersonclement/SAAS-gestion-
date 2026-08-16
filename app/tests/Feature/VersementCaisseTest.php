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

class VersementCaisseTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_patron_can_record_a_versement_from_a_gerant(): void
    {
        $tenant = Tenant::factory()->create();
        $boutique = Boutique::factory()->create(['tenant_id' => $tenant->id]);
        $patron = User::factory()->create(['tenant_id' => $tenant->id, 'role' => UserRole::Patron]);
        $gerant = User::factory()->create(['tenant_id' => $tenant->id, 'role' => UserRole::Gerant, 'boutique_id' => $boutique->id]);

        $response = $this->actingAs($patron)->post('/versements', [
            'boutique_id' => $boutique->id,
            'remis_par_id' => $gerant->id,
            'montant' => 50000,
            'date' => now()->toDateString(),
        ]);

        $response->assertRedirect(route('versements.index'));
        $this->assertDatabaseHas('versements_caisse', [
            'tenant_id' => $tenant->id,
            'boutique_id' => $boutique->id,
            'remis_par_id' => $gerant->id,
            'remis_par_nom' => $gerant->name,
            'montant' => 50000,
            'cree_par_id' => $patron->id,
        ]);
    }

    public function test_a_gerant_cannot_record_a_versement(): void
    {
        $tenant = Tenant::factory()->create();
        $boutique = Boutique::factory()->create(['tenant_id' => $tenant->id]);
        $gerant = User::factory()->create(['tenant_id' => $tenant->id, 'role' => UserRole::Gerant, 'boutique_id' => $boutique->id]);

        $response = $this->actingAs($gerant)->post('/versements', [
            'boutique_id' => $boutique->id,
            'remis_par_id' => $gerant->id,
            'montant' => 10000,
            'date' => now()->toDateString(),
        ]);

        $response->assertForbidden();
    }

    public function test_the_person_handing_over_cash_must_belong_to_the_chosen_boutique(): void
    {
        $tenant = Tenant::factory()->create();
        $boutiqueA = Boutique::factory()->create(['tenant_id' => $tenant->id]);
        $boutiqueB = Boutique::factory()->create(['tenant_id' => $tenant->id]);
        $patron = User::factory()->create(['tenant_id' => $tenant->id, 'role' => UserRole::Patron]);
        $gerantB = User::factory()->create(['tenant_id' => $tenant->id, 'role' => UserRole::Gerant, 'boutique_id' => $boutiqueB->id]);

        $response = $this->actingAs($patron)->post('/versements', [
            'boutique_id' => $boutiqueA->id,
            'remis_par_id' => $gerantB->id,
            'montant' => 10000,
            'date' => now()->toDateString(),
        ]);

        $response->assertSessionHasErrors('remis_par_id');
    }

    public function test_the_daily_cash_balance_deducts_versements_from_takings(): void
    {
        $tenant = Tenant::factory()->create();
        $boutique = Boutique::factory()->create(['tenant_id' => $tenant->id]);
        $patron = User::factory()->create(['tenant_id' => $tenant->id, 'role' => UserRole::Patron]);
        $vendeur = User::factory()->create(['tenant_id' => $tenant->id, 'role' => UserRole::Vendeur, 'boutique_id' => $boutique->id]);
        $produit = Produit::factory()->create(['tenant_id' => $tenant->id, 'prix_vente' => 20000]);
        $lot = Lot::factory()->create(['tenant_id' => $tenant->id, 'produit_id' => $produit->id]);
        StockBoutique::factory()->create(['tenant_id' => $tenant->id, 'boutique_id' => $boutique->id, 'produit_id' => $produit->id, 'lot_id' => $lot->id, 'quantite' => 10]);

        $this->actingAs($vendeur)->post('/ventes', [
            'boutique_id' => $boutique->id,
            'mode_paiement' => ModePaiement::Especes->value,
            'lignes' => [
                ['produit_id' => $produit->id, 'quantite' => 1],
            ],
        ])->assertRedirect();

        $this->actingAs($patron)->post('/versements', [
            'boutique_id' => $boutique->id,
            'remis_par_id' => $vendeur->id,
            'montant' => 15000,
            'date' => now()->toDateString(),
        ])->assertRedirect();

        $response = $this->actingAs($patron)->get('/tresorerie');

        $response->assertOk();
        $response->assertSee('20 000 FCFA');
        $response->assertSee('15 000 FCFA');
        $response->assertSee('5 000 FCFA');
    }
}
