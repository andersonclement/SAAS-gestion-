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

class JournalActiviteTest extends TestCase
{
    use RefreshDatabase;

    public function test_logging_in_is_recorded_in_the_journal(): void
    {
        $tenant = Tenant::factory()->create();
        $patron = User::factory()->create(['tenant_id' => $tenant->id, 'role' => UserRole::Patron, 'password' => bcrypt('password')]);

        $this->post('/login', ['email' => $patron->email, 'password' => 'password'])->assertRedirect();

        $this->assertDatabaseHas('journal_activites', [
            'tenant_id' => $tenant->id,
            'user_id' => $patron->id,
            'action' => 'connexion',
        ]);
    }

    public function test_recording_a_sale_appears_in_the_journal(): void
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
                ['produit_id' => $produit->id, 'quantite' => 2],
            ],
        ])->assertRedirect();

        $this->assertDatabaseHas('journal_activites', [
            'tenant_id' => $tenant->id,
            'user_id' => $vendeur->id,
            'boutique_id' => $boutique->id,
            'action' => 'vente.creee',
        ]);
    }

    public function test_a_deleted_users_own_journal_entries_keep_their_name_snapshot(): void
    {
        $tenant = Tenant::factory()->create();
        $patron = User::factory()->create(['tenant_id' => $tenant->id, 'role' => UserRole::Patron]);
        $boutique = Boutique::factory()->create(['tenant_id' => $tenant->id]);
        $gerant = User::factory()->create([
            'tenant_id' => $tenant->id,
            'role' => UserRole::Gerant,
            'boutique_id' => $boutique->id,
            'name' => 'Fatou Diabaté',
            'password' => bcrypt('password'),
        ]);

        $this->post('/login', ['email' => $gerant->email, 'password' => 'password'])->assertRedirect();
        $this->post('/logout')->assertRedirect();

        $this->assertDatabaseHas('journal_activites', [
            'user_id' => $gerant->id,
            'utilisateur_nom' => 'Fatou Diabaté',
            'action' => 'connexion',
        ]);

        $this->actingAs($patron)->delete("/users/{$gerant->id}")->assertRedirect();

        $this->assertDatabaseHas('journal_activites', [
            'tenant_id' => $tenant->id,
            'user_id' => null,
            'utilisateur_nom' => 'Fatou Diabaté',
            'action' => 'connexion',
        ]);
    }

    public function test_a_gerant_only_sees_journal_entries_for_his_own_boutique_or_himself(): void
    {
        $tenant = Tenant::factory()->create();
        $boutiqueA = Boutique::factory()->create(['tenant_id' => $tenant->id]);
        $boutiqueB = Boutique::factory()->create(['tenant_id' => $tenant->id]);
        $gerantA = User::factory()->create(['tenant_id' => $tenant->id, 'role' => UserRole::Gerant, 'boutique_id' => $boutiqueA->id]);
        $vendeurB = User::factory()->create(['tenant_id' => $tenant->id, 'role' => UserRole::Vendeur, 'boutique_id' => $boutiqueB->id]);
        $produit = Produit::factory()->create(['tenant_id' => $tenant->id, 'prix_vente' => 1000]);
        $lot = Lot::factory()->create(['tenant_id' => $tenant->id, 'produit_id' => $produit->id]);
        StockBoutique::factory()->create(['tenant_id' => $tenant->id, 'boutique_id' => $boutiqueB->id, 'produit_id' => $produit->id, 'lot_id' => $lot->id, 'quantite' => 10]);

        $this->actingAs($vendeurB)->post('/ventes', [
            'boutique_id' => $boutiqueB->id,
            'mode_paiement' => ModePaiement::Especes->value,
            'lignes' => [
                ['produit_id' => $produit->id, 'quantite' => 1],
            ],
        ])->assertRedirect();

        $response = $this->actingAs($gerantA)->get('/journal');

        $response->assertOk();
        $response->assertSee(__('Aucune activité enregistrée pour le moment.'));
    }
}
