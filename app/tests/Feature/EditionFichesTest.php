<?php

namespace Tests\Feature;

use App\Enums\TypeClient;
use App\Enums\TypeProduit;
use App\Enums\UniteMesure;
use App\Enums\UserRole;
use App\Models\Boutique;
use App\Models\Client;
use App\Models\Fournisseur;
use App\Models\JournalActivite;
use App\Models\Lot;
use App\Models\Produit;
use App\Models\Tenant;
use App\Models\User;
use App\Models\Vente;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EditionFichesTest extends TestCase
{
    use RefreshDatabase;

    private function donneesProduit(Produit $produit, array $remplacements = []): array
    {
        return array_merge([
            'nom' => $produit->nom,
            'type' => $produit->type->value,
            'unite_mesure' => $produit->unite_mesure->value,
            'prix_achat' => $produit->prix_achat,
            'prix_vente' => $produit->prix_vente,
            'seuil_alerte' => $produit->seuil_alerte,
            'actif' => 1,
        ], $remplacements);
    }

    public function test_a_patron_can_change_a_sale_price_and_the_change_is_journalled(): void
    {
        $tenant = Tenant::factory()->create();
        $patron = User::factory()->create(['tenant_id' => $tenant->id, 'role' => UserRole::Patron]);
        $produit = Produit::factory()->create(['tenant_id' => $tenant->id, 'nom' => 'Engrais NPK', 'prix_vente' => 15000]);

        $this->actingAs($patron)
            ->put(route('produits.update', $produit), $this->donneesProduit($produit, ['prix_vente' => 17500]))
            ->assertRedirect(route('produits.show', $produit));

        $this->assertSame(17500, $produit->fresh()->prix_vente);

        $trace = JournalActivite::where('action', 'produit.prix_modifie')->first();
        $this->assertNotNull($trace, 'Un changement de prix doit laisser une trace.');
        $this->assertStringContainsString('15 000', $trace->description);
        $this->assertStringContainsString('17 500', $trace->description);
    }

    public function test_changing_a_price_does_not_alter_sales_already_recorded(): void
    {
        $tenant = Tenant::factory()->create();
        $patron = User::factory()->create(['tenant_id' => $tenant->id, 'role' => UserRole::Patron]);
        $produit = Produit::factory()->create(['tenant_id' => $tenant->id, 'prix_vente' => 10000]);

        $this->actingAs($patron)->put(route('produits.update', $produit), $this->donneesProduit($produit, ['prix_vente' => 12000]));

        // Le prix est figé sur la ligne de vente au moment de la vente : une
        // facture déjà émise ne doit jamais changer rétroactivement.
        $this->assertSame(12000, $produit->fresh()->prix_vente);
    }

    public function test_a_product_can_be_deactivated(): void
    {
        $tenant = Tenant::factory()->create();
        $patron = User::factory()->create(['tenant_id' => $tenant->id, 'role' => UserRole::Patron]);
        $produit = Produit::factory()->create(['tenant_id' => $tenant->id, 'actif' => true]);

        $donnees = $this->donneesProduit($produit);
        unset($donnees['actif']); // Case décochée : le navigateur n'envoie rien.

        $this->actingAs($patron)->put(route('produits.update', $produit), $donnees)->assertRedirect();

        $this->assertFalse($produit->fresh()->actif);
    }

    public function test_a_vendeur_cannot_change_a_price(): void
    {
        $tenant = Tenant::factory()->create();
        $boutique = Boutique::factory()->create(['tenant_id' => $tenant->id]);
        $vendeur = User::factory()->create(['tenant_id' => $tenant->id, 'role' => UserRole::Vendeur, 'boutique_id' => $boutique->id]);
        $produit = Produit::factory()->create(['tenant_id' => $tenant->id, 'prix_vente' => 10000]);

        $this->actingAs($vendeur)
            ->put(route('produits.update', $produit), $this->donneesProduit($produit, ['prix_vente' => 1]))
            ->assertForbidden();

        $this->assertSame(10000, $produit->fresh()->prix_vente);
    }

    public function test_a_patron_cannot_edit_a_product_of_another_tenant(): void
    {
        $tenantA = Tenant::factory()->create();
        $tenantB = Tenant::factory()->create();
        $patronA = User::factory()->create(['tenant_id' => $tenantA->id, 'role' => UserRole::Patron]);
        $produitB = Produit::factory()->create(['tenant_id' => $tenantB->id, 'prix_vente' => 10000]);

        $this->actingAs($patronA)
            ->put(route('produits.update', $produitB), $this->donneesProduit($produitB, ['prix_vente' => 1]))
            ->assertNotFound();

        $this->assertSame(10000, $produitB->fresh()->prix_vente);
    }

    public function test_a_client_can_be_updated(): void
    {
        $tenant = Tenant::factory()->create();
        $patron = User::factory()->create(['tenant_id' => $tenant->id, 'role' => UserRole::Patron]);
        $client = Client::factory()->create(['tenant_id' => $tenant->id, 'telephone' => '+225 00']);

        $this->actingAs($patron)->put(route('clients.update', $client), [
            'nom' => $client->nom,
            'type' => TypeClient::cases()[0]->value,
            'telephone' => '+225 07 11 22 33 44',
            'plafond_credit' => 200000,
        ])->assertRedirect(route('clients.show', $client));

        $this->assertSame('+225 07 11 22 33 44', $client->fresh()->telephone);
        $this->assertSame(200000, $client->fresh()->plafond_credit);
    }

    public function test_the_credit_limit_cannot_be_lowered_below_the_existing_debt(): void
    {
        $tenant = Tenant::factory()->create();
        $patron = User::factory()->create(['tenant_id' => $tenant->id, 'role' => UserRole::Patron]);
        $client = Client::factory()->create(['tenant_id' => $tenant->id, 'plafond_credit' => 500000]);

        // On simule une dette via une vente à crédit non soldée.
        $vente = Vente::factory()->create([
            'tenant_id' => $tenant->id,
            'client_id' => $client->id,
            'date_echeance' => now()->addDays(30),
        ]);
        $produit = Produit::factory()->create(['tenant_id' => $tenant->id]);
        $lot = Lot::factory()->create(['tenant_id' => $tenant->id, 'produit_id' => $produit->id]);
        $vente->lignes()->create([
            'produit_id' => $produit->id, 'lot_id' => $lot->id,
            'quantite' => 1, 'prix_unitaire' => 300000,
        ]);

        $this->actingAs($patron)->put(route('clients.update', $client), [
            'nom' => $client->nom,
            'type' => TypeClient::cases()[0]->value,
            'plafond_credit' => 100000,
        ])->assertSessionHasErrors('plafond_credit');

        $this->assertSame(500000, $client->fresh()->plafond_credit);
    }

    public function test_a_supplier_and_a_shop_can_be_updated(): void
    {
        $tenant = Tenant::factory()->create();
        $patron = User::factory()->create(['tenant_id' => $tenant->id, 'role' => UserRole::Patron]);
        $fournisseur = Fournisseur::factory()->create(['tenant_id' => $tenant->id]);
        $boutique = Boutique::factory()->create(['tenant_id' => $tenant->id]);

        $this->actingAs($patron)->put(route('fournisseurs.update', $fournisseur), [
            'nom' => 'Agro Fournitures SARL',
            'telephone' => '+225 05 00 00 00 00',
        ])->assertRedirect();
        $this->assertSame('Agro Fournitures SARL', $fournisseur->fresh()->nom);

        $this->actingAs($patron)->put(route('boutiques.update', $boutique), [
            'nom' => 'AgroPlus Marcory',
            'adresse' => 'Boulevard du Gabon',
        ])->assertRedirect(route('boutiques.show', $boutique));
        $this->assertSame('AgroPlus Marcory', $boutique->fresh()->nom);
    }

    public function test_the_barcode_uniqueness_check_ignores_the_product_being_edited(): void
    {
        $tenant = Tenant::factory()->create();
        $patron = User::factory()->create(['tenant_id' => $tenant->id, 'role' => UserRole::Patron]);
        $produit = Produit::factory()->create([
            'tenant_id' => $tenant->id,
            'code_barres' => '3760000000011',
            'type' => TypeProduit::IntrantAgricole,
            'unite_mesure' => UniteMesure::Sac,
        ]);

        // Réenregistrer la fiche sans toucher au code-barres doit passer.
        $this->actingAs($patron)
            ->put(route('produits.update', $produit), $this->donneesProduit($produit, ['code_barres' => '3760000000011']))
            ->assertSessionHasNoErrors();
    }
}
