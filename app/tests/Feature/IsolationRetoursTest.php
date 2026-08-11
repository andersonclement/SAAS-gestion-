<?php

namespace Tests\Feature;

use App\Enums\ModePaiement;
use App\Enums\ModeRemboursement;
use App\Enums\MotifRetour;
use App\Enums\UserRole;
use App\Models\Boutique;
use App\Models\LigneVente;
use App\Models\Lot;
use App\Models\Produit;
use App\Models\Retour;
use App\Models\StockBoutique;
use App\Models\Tenant;
use App\Models\User;
use App\Models\Vente;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * LigneVente n'a pas de colonne tenant_id : elle échappe au scope global
 * multi-tenant, et le liage de route l'expose donc à n'importe quel
 * identifiant deviné. Ces tests verrouillent l'autorisation explicite
 * ajoutée dans RetourPolicy::create.
 */
class IsolationRetoursTest extends TestCase
{
    use RefreshDatabase;

    private function ligneVenteDeLaVictime(): LigneVente
    {
        $tenant = Tenant::factory()->create();
        $boutique = Boutique::factory()->create(['tenant_id' => $tenant->id]);
        $vendeur = User::factory()->create([
            'tenant_id' => $tenant->id, 'role' => UserRole::Vendeur, 'boutique_id' => $boutique->id,
        ]);
        $produit = Produit::factory()->create(['tenant_id' => $tenant->id, 'nom' => 'Produit confidentiel', 'prix_vente' => 9000]);
        $lot = Lot::factory()->create(['tenant_id' => $tenant->id, 'produit_id' => $produit->id]);
        StockBoutique::factory()->create([
            'tenant_id' => $tenant->id, 'boutique_id' => $boutique->id,
            'produit_id' => $produit->id, 'lot_id' => $lot->id, 'quantite' => 10,
        ]);

        $this->actingAs($vendeur)->post('/ventes', [
            'boutique_id' => $boutique->id,
            'mode_paiement' => ModePaiement::Especes->value,
            'lignes' => [['produit_id' => $produit->id, 'quantite' => 5]],
        ])->assertRedirect();

        $this->app['auth']->forgetGuards();

        return Vente::withoutGlobalScopes()
            ->where('tenant_id', $tenant->id)
            ->firstOrFail()
            ->lignes()
            ->firstOrFail();
    }

    public function test_a_patron_cannot_open_a_return_form_for_another_tenants_sale_line(): void
    {
        $ligneVictime = $this->ligneVenteDeLaVictime();

        $tenantAttaquant = Tenant::factory()->create();
        $patronAttaquant = User::factory()->create(['tenant_id' => $tenantAttaquant->id, 'role' => UserRole::Patron]);

        $response = $this->actingAs($patronAttaquant)->get("/lignes-vente/{$ligneVictime->id}/retour");

        $response->assertForbidden();
        $response->assertDontSee('Produit confidentiel');
    }

    public function test_a_patron_cannot_record_a_return_on_another_tenants_sale_line(): void
    {
        $ligneVictime = $this->ligneVenteDeLaVictime();
        $stockAvant = StockBoutique::withoutGlobalScopes()->sum('quantite');

        $tenantAttaquant = Tenant::factory()->create();
        $patronAttaquant = User::factory()->create(['tenant_id' => $tenantAttaquant->id, 'role' => UserRole::Patron]);

        $response = $this->actingAs($patronAttaquant)->post("/lignes-vente/{$ligneVictime->id}/retour", [
            'quantite' => 5,
            'motif' => MotifRetour::cases()[0]->value,
            'mode_remboursement' => ModeRemboursement::cases()[0]->value,
            'remis_en_stock' => true,
        ]);

        $response->assertForbidden();
        $this->assertSame(0, Retour::withoutGlobalScopes()->count());
        $this->assertSame(
            $stockAvant,
            StockBoutique::withoutGlobalScopes()->sum('quantite'),
            'Le stock de la victime ne doit pas avoir été modifié.'
        );
    }

    public function test_a_gerant_cannot_record_a_return_for_another_boutique_of_the_same_tenant(): void
    {
        $ligneVictime = $this->ligneVenteDeLaVictime();
        $tenantVictime = Vente::withoutGlobalScopes()->firstOrFail()->tenant_id;

        $autreBoutique = Boutique::factory()->create(['tenant_id' => $tenantVictime]);
        $gerantAutreBoutique = User::factory()->create([
            'tenant_id' => $tenantVictime, 'role' => UserRole::Gerant, 'boutique_id' => $autreBoutique->id,
        ]);

        $this->actingAs($gerantAutreBoutique)
            ->get("/lignes-vente/{$ligneVictime->id}/retour")
            ->assertForbidden();
    }

    public function test_the_legitimate_seller_can_still_record_a_return(): void
    {
        $ligneVictime = $this->ligneVenteDeLaVictime();
        $vente = Vente::withoutGlobalScopes()->firstOrFail();
        $vendeur = User::withoutGlobalScopes()->findOrFail($vente->vendeur_id);

        $this->actingAs($vendeur)
            ->get("/lignes-vente/{$ligneVictime->id}/retour")
            ->assertOk();

        $this->actingAs($vendeur)->post("/lignes-vente/{$ligneVictime->id}/retour", [
            'quantite' => 2,
            'motif' => MotifRetour::cases()[0]->value,
            'mode_remboursement' => ModeRemboursement::cases()[0]->value,
            'remis_en_stock' => true,
        ])->assertRedirect();

        $this->assertSame(1, Retour::withoutGlobalScopes()->count());
    }
}
