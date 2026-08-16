<?php

namespace Tests\Feature;

use App\Enums\UniteMesure;
use App\Enums\UserRole;
use App\Models\Boutique;
use App\Models\Conditionnement;
use App\Models\LigneVente;
use App\Models\Lot;
use App\Models\Produit;
use App\Models\StockBoutique;
use App\Models\Tenant;
use App\Models\User;
use App\Models\Vente;
use App\Services\PrevisionReapprovisionnement;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * Prévisions de réapprovisionnement (§4.6).
 */
class PrevisionsTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array{tenant: Tenant, boutique: Boutique, patron: User, vendeur: User, produit: Produit, lot: Lot, stock: StockBoutique}
     */
    private function contexte(int $quantiteEnStock = 100): array
    {
        $tenant = Tenant::factory()->create();
        $boutique = Boutique::factory()->create(['tenant_id' => $tenant->id]);
        $patron = User::factory()->create(['tenant_id' => $tenant->id, 'role' => UserRole::Patron]);
        $vendeur = User::factory()->create([
            'tenant_id' => $tenant->id,
            'role' => UserRole::Vendeur,
            'boutique_id' => $boutique->id,
        ]);
        $produit = Produit::factory()->create([
            'tenant_id' => $tenant->id,
            'nom' => 'Engrais NPK',
            'unite_mesure' => UniteMesure::Kilogramme,
            'prix_vente' => 600,
            'stock_min' => 0,
        ]);
        $lot = Lot::factory()->create(['tenant_id' => $tenant->id, 'produit_id' => $produit->id]);
        $stock = StockBoutique::factory()->create([
            'tenant_id' => $tenant->id,
            'boutique_id' => $boutique->id,
            'produit_id' => $produit->id,
            'lot_id' => $lot->id,
            'quantite' => $quantiteEnStock,
        ]);

        return compact('tenant', 'boutique', 'patron', 'vendeur', 'produit', 'lot', 'stock');
    }

    /**
     * Enregistre une vente passée sans passer par la caisse : on écrit
     * directement la ligne, pour maîtriser la date et ne pas toucher au stock
     * (dont la valeur courante est fixée par le contexte du test).
     */
    private function vendre(array $c, int $quantite, Carbon $date): void
    {
        $vente = Vente::withoutEvents(fn () => Vente::create([
            'tenant_id' => $c['tenant']->id,
            'boutique_id' => $c['boutique']->id,
            'vendeur_id' => $c['vendeur']->id,
            'numero' => 'VT-'.fake()->unique()->numerify('######'),
            'encaissee_le' => $date,
        ]));

        $vente->created_at = $date;
        $vente->saveQuietly();

        LigneVente::create([
            'vente_id' => $vente->id,
            'produit_id' => $c['produit']->id,
            'lot_id' => $c['lot']->id,
            'quantite' => $quantite,
            'prix_unitaire' => 600,
        ]);
    }

    private function previsions(): PrevisionReapprovisionnement
    {
        return app(PrevisionReapprovisionnement::class);
    }

    // --- Calcul -----------------------------------------------------------

    public function test_une_demande_reguliere_donne_une_moyenne_journaliere_fidele(): void
    {
        $c = $this->contexte(100);
        $aujourdhui = Carbon::parse('2026-06-15');

        // 14 kg par semaine pendant douze semaines, soit 2 kg par jour.
        for ($semaine = 0; $semaine < 12; $semaine++) {
            $this->vendre($c, 14, $aujourdhui->copy()->subDays($semaine * 7 + 1));
        }

        $ligne = $this->previsions()->pour($c['boutique']->id, 7, 30, $aujourdhui)->firstWhere('produit.id', $c['produit']->id);

        $this->assertEqualsWithDelta(2.0, $ligne['demande_journaliere'], 0.05);
        // 100 kg en stock, 2 kg par jour : rupture dans 50 jours.
        $this->assertSame(50, $ligne['jours_avant_rupture']);
        // Horizon 37 jours → 74 kg nécessaires, couverts par les 100 en stock.
        $this->assertSame(0, $ligne['besoin']);
    }

    public function test_les_semaines_recentes_pesent_plus_que_les_anciennes(): void
    {
        $c = $this->contexte(100);
        $aujourdhui = Carbon::parse('2026-06-15');

        // Ventes en forte baisse : 70/semaine il y a trois mois, 7 aujourd'hui.
        for ($semaine = 0; $semaine < 12; $semaine++) {
            $this->vendre($c, $semaine >= 6 ? 70 : 7, $aujourdhui->copy()->subDays($semaine * 7 + 1));
        }

        $ligne = $this->previsions()->pour($c['boutique']->id, 7, 30, $aujourdhui)->firstWhere('produit.id', $c['produit']->id);

        // Moyenne simple : (70+7)/2 = 38,5 par semaine, soit 5,5 par jour.
        // La pondération tire l'estimation vers la période récente.
        $this->assertLessThan(5.5, $ligne['demande_journaliere']);
        $this->assertGreaterThan(1.0, $ligne['demande_journaliere']);
    }

    public function test_un_stock_insuffisant_declenche_une_suggestion_de_commande(): void
    {
        $c = $this->contexte(20);
        $aujourdhui = Carbon::parse('2026-06-15');

        for ($semaine = 0; $semaine < 12; $semaine++) {
            $this->vendre($c, 14, $aujourdhui->copy()->subDays($semaine * 7 + 1));
        }

        $ligne = $this->previsions()->pour($c['boutique']->id, 7, 30, $aujourdhui)->firstWhere('produit.id', $c['produit']->id);

        // 2 kg/jour sur 37 jours = 74 kg ; 20 en stock → 54 à commander.
        $this->assertSame(54, $ligne['besoin']);
        $this->assertSame(10, $ligne['jours_avant_rupture']);
        // Délai fournisseur de 7 jours : il faut commander 3 jours avant la rupture.
        $this->assertSame('2026-06-18', $ligne['commander_avant']->toDateString());
    }

    public function test_le_stock_de_securite_du_produit_est_ajoute_au_besoin(): void
    {
        $c = $this->contexte(20);
        $c['produit']->update(['stock_min' => 30]);
        $aujourdhui = Carbon::parse('2026-06-15');

        for ($semaine = 0; $semaine < 12; $semaine++) {
            $this->vendre($c, 14, $aujourdhui->copy()->subDays($semaine * 7 + 1));
        }

        $ligne = $this->previsions()->pour($c['boutique']->id, 7, 30, $aujourdhui)->firstWhere('produit.id', $c['produit']->id);

        // 74 kg de demande + 30 de sécurité − 20 en stock.
        $this->assertSame(84, $ligne['besoin']);
    }

    public function test_la_suggestion_est_arrondie_au_format_de_commande(): void
    {
        $c = $this->contexte(20);
        $aujourdhui = Carbon::parse('2026-06-15');

        Conditionnement::factory()->create([
            'tenant_id' => $c['tenant']->id,
            'produit_id' => $c['produit']->id,
            'libelle' => 'Sac de 50 kg',
            'facteur' => 50,
            'prix_vente' => 28000,
            'par_defaut' => true,
        ]);

        for ($semaine = 0; $semaine < 12; $semaine++) {
            $this->vendre($c, 14, $aujourdhui->copy()->subDays($semaine * 7 + 1));
        }

        $ligne = $this->previsions()->pour($c['boutique']->id, 7, 30, $aujourdhui)->firstWhere('produit.id', $c['produit']->id);

        // 54 kg nécessaires : on ne commande pas 54 kg à qui livre par sacs de 50.
        $this->assertSame(54, $ligne['besoin']);
        $this->assertSame(100, $ligne['suggestion']);
        $this->assertSame('Sac de 50 kg', $ligne['conditionnement']->libelle);
    }

    public function test_un_historique_trop_court_est_signale_comme_peu_fiable(): void
    {
        $c = $this->contexte(100);
        $aujourdhui = Carbon::parse('2026-06-15');

        // Deux semaines de ventes seulement : ce n'est pas une tendance.
        $this->vendre($c, 14, $aujourdhui->copy()->subDays(2));
        $this->vendre($c, 14, $aujourdhui->copy()->subDays(9));

        $ligne = $this->previsions()->pour($c['boutique']->id, 7, 30, $aujourdhui)->firstWhere('produit.id', $c['produit']->id);

        $this->assertFalse($ligne['fiable']);
        $this->assertSame(2, $ligne['semaines_observees']);
    }

    public function test_sans_une_annee_d_historique_aucune_saisonnalite_n_est_inventee(): void
    {
        $c = $this->contexte(100);
        $aujourdhui = Carbon::parse('2026-06-15');

        for ($semaine = 0; $semaine < 12; $semaine++) {
            $this->vendre($c, 14, $aujourdhui->copy()->subDays($semaine * 7 + 1));
        }

        $ligne = $this->previsions()->pour($c['boutique']->id, 7, 30, $aujourdhui)->firstWhere('produit.id', $c['produit']->id);

        $this->assertFalse($ligne['saisonnalite_disponible']);
        $this->assertSame(1.0, $ligne['coefficient_saisonnier']);
    }

    public function test_un_mois_de_forte_saison_l_an_dernier_releve_la_prevision(): void
    {
        $c = $this->contexte(100);
        $aujourdhui = Carbon::parse('2026-06-15');

        // Une année complète de ventes faibles...
        for ($jour = 30; $jour <= 400; $jour += 7) {
            $this->vendre($c, 7, $aujourdhui->copy()->subDays($jour));
        }

        // ...sauf en juin de l'an dernier, mois de semis, très chargé.
        for ($jour = 1; $jour <= 28; $jour++) {
            $this->vendre($c, 40, Carbon::parse('2025-06-'.str_pad((string) $jour, 2, '0', STR_PAD_LEFT)));
        }

        $ligne = $this->previsions()->pour($c['boutique']->id, 7, 30, $aujourdhui)->firstWhere('produit.id', $c['produit']->id);

        $this->assertTrue($ligne['saisonnalite_disponible']);
        $this->assertGreaterThan(1.0, $ligne['coefficient_saisonnier']);
        // Le coefficient reste borné : une donnée mince ne doit pas produire
        // une commande absurde.
        $this->assertLessThanOrEqual(2.0, $ligne['coefficient_saisonnier']);
    }

    public function test_un_produit_sans_vente_ne_declenche_pas_de_commande(): void
    {
        $c = $this->contexte(100);
        $aujourdhui = Carbon::parse('2026-06-15');

        $ligne = $this->previsions()->pour($c['boutique']->id, 7, 30, $aujourdhui)->firstWhere('produit.id', $c['produit']->id);

        $this->assertSame(0.0, $ligne['demande_journaliere']);
        $this->assertNull($ligne['jours_avant_rupture']);
        $this->assertSame(0, $ligne['besoin']);
    }

    public function test_les_ventes_d_une_autre_boutique_ne_comptent_pas(): void
    {
        $c = $this->contexte(100);
        $aujourdhui = Carbon::parse('2026-06-15');
        $autreBoutique = Boutique::factory()->create(['tenant_id' => $c['tenant']->id]);

        for ($semaine = 0; $semaine < 12; $semaine++) {
            $vente = Vente::withoutEvents(fn () => Vente::create([
                'tenant_id' => $c['tenant']->id,
                'boutique_id' => $autreBoutique->id,
                'vendeur_id' => $c['vendeur']->id,
                'numero' => 'VT-A'.$semaine,
                'encaissee_le' => $aujourdhui->copy()->subDays($semaine * 7 + 1),
            ]));

            LigneVente::create([
                'vente_id' => $vente->id,
                'produit_id' => $c['produit']->id,
                'lot_id' => $c['lot']->id,
                'quantite' => 140,
                'prix_unitaire' => 600,
            ]);
        }

        $ligne = $this->previsions()->pour($c['boutique']->id, 7, 30, $aujourdhui)->firstWhere('produit.id', $c['produit']->id);

        $this->assertSame(0.0, $ligne['demande_journaliere']);
    }

    // --- Écran ------------------------------------------------------------

    public function test_le_patron_accede_a_l_ecran_des_previsions(): void
    {
        $c = $this->contexte(10);

        for ($semaine = 0; $semaine < 12; $semaine++) {
            $this->vendre($c, 14, now()->copy()->subDays($semaine * 7 + 1));
        }

        $this->actingAs($c['patron'])
            ->get('/previsions?boutique_id='.$c['boutique']->id)
            ->assertOk()
            ->assertSee('Engrais NPK');
    }

    public function test_un_vendeur_n_accede_pas_aux_previsions(): void
    {
        $c = $this->contexte();

        $this->actingAs($c['vendeur'])->get('/previsions')->assertStatus(403);
    }

    public function test_les_previsions_d_une_boutique_d_un_autre_tenant_sont_invisibles(): void
    {
        $c = $this->contexte();
        $autreTenant = Tenant::factory()->create();
        $boutiqueVoisine = Boutique::factory()->create(['tenant_id' => $autreTenant->id]);

        $this->actingAs($c['patron'])
            ->get('/previsions?boutique_id='.$boutiqueVoisine->id)
            ->assertStatus(404);
    }

    public function test_la_liste_de_commande_s_exporte_en_csv(): void
    {
        $c = $this->contexte(10);

        for ($semaine = 0; $semaine < 12; $semaine++) {
            $this->vendre($c, 14, now()->copy()->subDays($semaine * 7 + 1));
        }

        $reponse = $this->actingAs($c['patron'])->get('/previsions.csv?boutique_id='.$c['boutique']->id);

        $reponse->assertOk();
        $reponse->assertHeader('content-type', 'text/csv; charset=UTF-8');
        $this->assertStringContainsString('Engrais NPK', $reponse->streamedContent());
    }
}
