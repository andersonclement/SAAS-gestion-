<?php

namespace Tests\Feature;

use App\Enums\ModePaiement;
use App\Enums\PorteePromotion;
use App\Enums\TypePromotion;
use App\Enums\UserRole;
use App\Models\Boutique;
use App\Models\Client;
use App\Models\EcartSynchronisation;
use App\Models\Lot;
use App\Models\Produit;
use App\Models\Promotion;
use App\Models\StockBoutique;
use App\Models\Tenant;
use App\Models\User;
use App\Models\Vente;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Mode dégradé hors-ligne (§5) : remontée des ventes encaissées sans réseau.
 */
class SynchronisationHorsLigneTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array{tenant: Tenant, boutique: Boutique, vendeur: User, produit: Produit, lot: Lot, stock: StockBoutique}
     */
    private function contexte(int $quantiteEnStock = 20, int $prix = 5000): array
    {
        $tenant = Tenant::factory()->create();
        $boutique = Boutique::factory()->create(['tenant_id' => $tenant->id]);
        $vendeur = User::factory()->create([
            'tenant_id' => $tenant->id,
            'role' => UserRole::Vendeur,
            'boutique_id' => $boutique->id,
        ]);
        $produit = Produit::factory()->create(['tenant_id' => $tenant->id, 'prix_vente' => $prix]);
        $lot = Lot::factory()->create(['tenant_id' => $tenant->id, 'produit_id' => $produit->id]);
        $stock = StockBoutique::factory()->create([
            'tenant_id' => $tenant->id,
            'boutique_id' => $boutique->id,
            'produit_id' => $produit->id,
            'lot_id' => $lot->id,
            'quantite' => $quantiteEnStock,
        ]);

        return compact('tenant', 'boutique', 'vendeur', 'produit', 'lot', 'stock');
    }

    /**
     * @param  array<string, mixed>  $remplacements
     * @return array<string, mixed>
     */
    private function venteHorsLigne(array $contexte, array $remplacements = []): array
    {
        return array_merge([
            'uuid_client' => (string) Str::uuid(),
            'encaissee_le' => now()->subHours(3)->toIso8601String(),
            'boutique_id' => $contexte['boutique']->id,
            'client_id' => null,
            'mode_paiement' => ModePaiement::Especes->value,
            'montant_paye' => 25000,
            'date_echeance' => null,
            'lignes' => [
                ['produit_id' => $contexte['produit']->id, 'quantite' => 5],
            ],
        ], $remplacements);
    }

    public function test_une_vente_encaissee_hors_ligne_est_enregistree_et_decremente_le_stock(): void
    {
        $c = $this->contexte();
        $vente = $this->venteHorsLigne($c);

        $reponse = $this->actingAs($c['vendeur'])->postJson('/sync/ventes', ['ventes' => [$vente]]);

        $reponse->assertOk();
        $reponse->assertJsonPath('resultats.0.statut', 'enregistree');

        $this->assertDatabaseHas('ventes', [
            'tenant_id' => $c['tenant']->id,
            'boutique_id' => $c['boutique']->id,
            'vendeur_id' => $c['vendeur']->id,
            'uuid_client' => $vente['uuid_client'],
        ]);
        $this->assertDatabaseHas('ligne_ventes', ['produit_id' => $c['produit']->id, 'quantite' => 5, 'prix_unitaire' => 5000]);
        $this->assertDatabaseHas('stock_boutiques', ['id' => $c['stock']->id, 'quantite' => 15]);
        $this->assertDatabaseHas('paiements', ['mode' => 'especes', 'montant' => 25000]);
        $this->assertDatabaseCount('ecarts_synchronisation', 0);
    }

    public function test_l_heure_reelle_de_l_encaissement_est_conservee(): void
    {
        $c = $this->contexte();
        $encaissee = now()->subDays(2)->setTime(14, 30);

        $this->actingAs($c['vendeur'])->postJson('/sync/ventes', [
            'ventes' => [$this->venteHorsLigne($c, ['encaissee_le' => $encaissee->toIso8601String()])],
        ])->assertOk();

        $vente = Vente::firstOrFail();

        $this->assertNotNull($vente->encaissee_le);
        $this->assertSame($encaissee->format('Y-m-d H:i'), $vente->encaissee_le->format('Y-m-d H:i'));
        $this->assertTrue($vente->vientDuHorsLigne());
    }

    public function test_renvoyer_la_meme_vente_ne_la_duplique_pas(): void
    {
        $c = $this->contexte();
        $vente = $this->venteHorsLigne($c);

        $this->actingAs($c['vendeur'])->postJson('/sync/ventes', ['ventes' => [$vente]])->assertOk();

        // Le navigateur n'a pas reçu l'accusé de réception et retente.
        $reponse = $this->actingAs($c['vendeur'])->postJson('/sync/ventes', ['ventes' => [$vente]]);

        $reponse->assertOk();
        $reponse->assertJsonPath('resultats.0.statut', 'deja_enregistree');

        $this->assertDatabaseCount('ventes', 1);
        $this->assertDatabaseCount('paiements', 1);
        $this->assertDatabaseHas('stock_boutiques', ['id' => $c['stock']->id, 'quantite' => 15]);
    }

    public function test_un_lot_partiellement_deja_synchronise_enregistre_uniquement_les_nouvelles(): void
    {
        $c = $this->contexte(30);
        $premiere = $this->venteHorsLigne($c);
        $seconde = $this->venteHorsLigne($c);

        $this->actingAs($c['vendeur'])->postJson('/sync/ventes', ['ventes' => [$premiere]])->assertOk();

        $reponse = $this->actingAs($c['vendeur'])->postJson('/sync/ventes', ['ventes' => [$premiere, $seconde]]);

        $reponse->assertOk();
        $reponse->assertJsonPath('resultats.0.statut', 'deja_enregistree');
        $reponse->assertJsonPath('resultats.1.statut', 'enregistree');
        $this->assertDatabaseCount('ventes', 2);
        $this->assertDatabaseHas('stock_boutiques', ['id' => $c['stock']->id, 'quantite' => 20]);
    }

    public function test_un_stock_devenu_insuffisant_enregistre_la_vente_et_consigne_l_ecart(): void
    {
        // Le stock ne suffit plus : la marchandise est partie et l'argent est
        // encaissé, la vente ne peut pas être refusée.
        $c = $this->contexte(3);

        $reponse = $this->actingAs($c['vendeur'])->postJson('/sync/ventes', [
            'ventes' => [$this->venteHorsLigne($c)],
        ]);

        $reponse->assertOk();
        $reponse->assertJsonPath('resultats.0.statut', 'enregistree');
        $reponse->assertJsonPath('resultats.0.ecarts.0.quantite_manquante', 2);

        $this->assertDatabaseCount('ventes', 1);
        $this->assertDatabaseHas('ligne_ventes', ['produit_id' => $c['produit']->id, 'quantite' => 3]);
        // Le stock tombe à zéro, jamais en négatif.
        $this->assertDatabaseHas('stock_boutiques', ['id' => $c['stock']->id, 'quantite' => 0]);
        $this->assertDatabaseHas('ecarts_synchronisation', [
            'vente_id' => Vente::firstOrFail()->id,
            'produit_id' => $c['produit']->id,
            'quantite_demandee' => 5,
            'quantite_allouee' => 3,
            'quantite_manquante' => 2,
            'resolu_le' => null,
        ]);
    }

    public function test_le_prix_applique_est_celui_en_vigueur_au_moment_de_l_encaissement(): void
    {
        $c = $this->contexte(20, 5000);

        // Promotion terminée hier : une vente encaissée avant-hier doit en
        // bénéficier, même si elle ne remonte qu'aujourd'hui.
        Promotion::factory()->create([
            'tenant_id' => $c['tenant']->id,
            'portee' => PorteePromotion::Produit,
            'produit_id' => $c['produit']->id,
            'type' => TypePromotion::Pourcentage,
            'valeur' => 10,
            'actif' => true,
            'date_debut' => now()->subDays(10)->toDateString(),
            'date_fin' => now()->subDay()->toDateString(),
            'cree_par_id' => $c['vendeur']->id,
        ]);

        $this->actingAs($c['vendeur'])->postJson('/sync/ventes', [
            'ventes' => [$this->venteHorsLigne($c, ['encaissee_le' => now()->subDays(2)->toIso8601String()])],
        ])->assertOk();

        $this->assertDatabaseHas('ligne_ventes', ['produit_id' => $c['produit']->id, 'prix_unitaire' => 4500]);
    }

    public function test_un_vendeur_ne_peut_pas_synchroniser_pour_une_autre_boutique(): void
    {
        $c = $this->contexte();
        $autreBoutique = Boutique::factory()->create(['tenant_id' => $c['tenant']->id]);

        $reponse = $this->actingAs($c['vendeur'])->postJson('/sync/ventes', [
            'ventes' => [$this->venteHorsLigne($c, ['boutique_id' => $autreBoutique->id])],
        ]);

        $reponse->assertOk();
        $reponse->assertJsonPath('resultats.0.statut', 'refusee');
        $this->assertDatabaseCount('ventes', 0);
    }

    public function test_une_vente_ne_peut_pas_viser_la_boutique_d_un_autre_tenant(): void
    {
        $c = $this->contexte();
        $autreTenant = Tenant::factory()->create();
        $boutiqueEtrangere = Boutique::factory()->create(['tenant_id' => $autreTenant->id]);

        $reponse = $this->actingAs($c['vendeur'])->postJson('/sync/ventes', [
            'ventes' => [$this->venteHorsLigne($c, ['boutique_id' => $boutiqueEtrangere->id])],
        ]);

        $reponse->assertStatus(422);
        $this->assertDatabaseCount('ventes', 0);
    }

    public function test_un_produit_d_un_autre_tenant_est_rejete(): void
    {
        $c = $this->contexte();
        $autreTenant = Tenant::factory()->create();
        $produitEtranger = Produit::factory()->create(['tenant_id' => $autreTenant->id]);

        $reponse = $this->actingAs($c['vendeur'])->postJson('/sync/ventes', [
            'ventes' => [$this->venteHorsLigne($c, [
                'lignes' => [['produit_id' => $produitEtranger->id, 'quantite' => 1]],
            ])],
        ]);

        $reponse->assertStatus(422);
        $this->assertDatabaseCount('ventes', 0);
    }

    public function test_un_comptable_ne_peut_pas_synchroniser_de_ventes(): void
    {
        $c = $this->contexte();
        $comptable = User::factory()->create([
            'tenant_id' => $c['tenant']->id,
            'role' => UserRole::Comptable,
            'boutique_id' => $c['boutique']->id,
        ]);

        $this->actingAs($comptable)
            ->postJson('/sync/ventes', ['ventes' => [$this->venteHorsLigne($c)]])
            ->assertStatus(403);

        $this->assertDatabaseCount('ventes', 0);
    }

    public function test_la_synchronisation_exige_une_authentification(): void
    {
        $c = $this->contexte();

        $this->postJson('/sync/ventes', ['ventes' => [$this->venteHorsLigne($c)]])
            ->assertStatus(401);
    }

    public function test_une_vente_a_credit_conserve_son_echeance_et_son_reste_a_payer(): void
    {
        $c = $this->contexte();
        $client = Client::factory()->create(['tenant_id' => $c['tenant']->id, 'plafond_credit' => 100000]);
        $echeance = now()->addDays(30)->toDateString();

        $this->actingAs($c['vendeur'])->postJson('/sync/ventes', [
            'ventes' => [$this->venteHorsLigne($c, [
                'client_id' => $client->id,
                'montant_paye' => 10000,
                'date_echeance' => $echeance,
            ])],
        ])->assertOk();

        $vente = Vente::with(['lignes', 'paiements'])->firstOrFail();

        $this->assertSame($client->id, $vente->client_id);
        $this->assertTrue($vente->estACredit());
        $this->assertSame(25000, $vente->montantTotal());
        $this->assertSame(15000, $vente->montantDu());
    }

    public function test_le_catalogue_hors_ligne_expose_prix_et_stock_de_la_boutique(): void
    {
        $c = $this->contexte(12, 750);

        $reponse = $this->actingAs($c['vendeur'])->getJson('/sync/catalogue');

        $reponse->assertOk();
        $reponse->assertJsonPath('disponible', true);
        $reponse->assertJsonPath('boutique.id', $c['boutique']->id);
        $reponse->assertJsonPath('produits.0.id', $c['produit']->id);
        $reponse->assertJsonPath('produits.0.prix', 750);
        $reponse->assertJsonPath('produits.0.stock', 12);
    }

    public function test_le_catalogue_hors_ligne_porte_le_code_barres(): void
    {
        $c = $this->contexte();
        $c['produit']->update(['code_barres' => '3760091721234']);

        // Sans lui, le scan de la caisse retombe sur la liste déroulante
        // pendant une coupure — au moment où il sert le plus.
        $this->actingAs($c['vendeur'])->getJson('/sync/catalogue')
            ->assertOk()
            ->assertJsonPath('produits.0.code_barres', '3760091721234');
    }

    public function test_le_catalogue_ne_fuit_pas_les_produits_d_un_autre_tenant(): void
    {
        $c = $this->contexte();
        $autreTenant = Tenant::factory()->create();
        Produit::factory()->create(['tenant_id' => $autreTenant->id, 'nom' => 'Produit du voisin']);

        $reponse = $this->actingAs($c['vendeur'])->getJson('/sync/catalogue');

        $reponse->assertOk();
        $reponse->assertJsonCount(1, 'produits');
        $reponse->assertJsonMissing(['nom' => 'Produit du voisin']);
    }

    public function test_un_gerant_peut_marquer_un_ecart_comme_traite(): void
    {
        $c = $this->contexte(3);
        $gerant = User::factory()->create([
            'tenant_id' => $c['tenant']->id,
            'role' => UserRole::Gerant,
            'boutique_id' => $c['boutique']->id,
        ]);

        $this->actingAs($c['vendeur'])->postJson('/sync/ventes', ['ventes' => [$this->venteHorsLigne($c)]])->assertOk();

        $ecart = EcartSynchronisation::firstOrFail();

        $this->actingAs($gerant)->post("/ecarts-synchronisation/{$ecart->id}/resoudre")->assertRedirect();

        $this->assertNotNull($ecart->fresh()->resolu_le);
        $this->assertSame($gerant->id, $ecart->fresh()->resolu_par);
    }

    public function test_un_vendeur_ne_peut_pas_marquer_un_ecart_comme_traite(): void
    {
        $c = $this->contexte(3);

        $this->actingAs($c['vendeur'])->postJson('/sync/ventes', ['ventes' => [$this->venteHorsLigne($c)]])->assertOk();

        $ecart = EcartSynchronisation::firstOrFail();

        $this->actingAs($c['vendeur'])->post("/ecarts-synchronisation/{$ecart->id}/resoudre")->assertStatus(403);
        $this->assertNull($ecart->fresh()->resolu_le);
    }

    public function test_un_ecart_d_un_autre_tenant_est_invisible(): void
    {
        $c = $this->contexte(3);
        $this->actingAs($c['vendeur'])->postJson('/sync/ventes', ['ventes' => [$this->venteHorsLigne($c)]])->assertOk();

        $ecart = EcartSynchronisation::firstOrFail();

        $autreTenant = Tenant::factory()->create();
        $intrus = User::factory()->create(['tenant_id' => $autreTenant->id, 'role' => UserRole::Patron]);

        $this->actingAs($intrus)->post("/ecarts-synchronisation/{$ecart->id}/resoudre")->assertStatus(404);
        $this->assertNull($ecart->fresh()->resolu_le);
    }

    public function test_les_ecarts_non_resolus_apparaissent_dans_les_alertes(): void
    {
        $c = $this->contexte(3);
        $patron = User::factory()->create(['tenant_id' => $c['tenant']->id, 'role' => UserRole::Patron]);

        $this->actingAs($c['vendeur'])->postJson('/sync/ventes', ['ventes' => [$this->venteHorsLigne($c)]])->assertOk();

        $this->actingAs($patron)->get('/alertes')
            ->assertOk()
            ->assertSee(__('Écarts de stock à la synchronisation'), false);
    }
}
