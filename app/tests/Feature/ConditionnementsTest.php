<?php

namespace Tests\Feature;

use App\Enums\ModePaiement;
use App\Enums\PorteePromotion;
use App\Enums\TypeProduit;
use App\Enums\TypePromotion;
use App\Enums\UniteMesure;
use App\Enums\UserRole;
use App\Models\Boutique;
use App\Models\Conditionnement;
use App\Models\Fournisseur;
use App\Models\LigneBonCommande;
use App\Models\LigneVente;
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
 * Conditionnements multiples : vendre le même produit au sac et au détail
 * (lacune n°2 de l'analyse du cahier des charges).
 */
class ConditionnementsTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array{tenant: Tenant, boutique: Boutique, patron: User, vendeur: User, produit: Produit, lot: Lot, stock: StockBoutique}
     */
    private function contexte(int $quantiteEnStock = 1000, int $prixVente = 600): array
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
            // Unité fixée : les libellés attendus par les assertions en dépendent.
            'unite_mesure' => UniteMesure::Kilogramme,
            'prix_vente' => $prixVente,
            'prix_achat' => 450,
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

    private function sacDe50(array $contexte, int $prix = 28000): Conditionnement
    {
        return Conditionnement::factory()->create([
            'tenant_id' => $contexte['tenant']->id,
            'produit_id' => $contexte['produit']->id,
            'libelle' => 'Sac de 50 kg',
            'facteur' => 50,
            'prix_vente' => $prix,
            'par_defaut' => true,
        ]);
    }

    // --- Catalogue --------------------------------------------------------

    public function test_un_patron_peut_ajouter_un_format_de_vente(): void
    {
        $c = $this->contexte();

        $this->actingAs($c['patron'])
            ->post("/produits/{$c['produit']->id}/conditionnements", [
                'libelle' => 'Sac de 50 kg',
                'facteur' => 50,
                'prix_vente' => 28000,
                'par_defaut' => 1,
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('conditionnements', [
            'tenant_id' => $c['tenant']->id,
            'produit_id' => $c['produit']->id,
            'libelle' => 'Sac de 50 kg',
            'facteur' => 50,
            'prix_vente' => 28000,
            'par_defaut' => true,
            'actif' => true,
        ]);
    }

    public function test_un_prix_non_divisible_par_le_contenu_est_refuse(): void
    {
        $c = $this->contexte();

        // 27 510 / 50 = 550,20 F le kilo : impossible en francs entiers sans
        // perdre quelques francs à chaque ligne de vente.
        $this->actingAs($c['patron'])
            ->post("/produits/{$c['produit']->id}/conditionnements", [
                'libelle' => 'Sac de 50 kg',
                'facteur' => 50,
                'prix_vente' => 27510,
            ])
            ->assertSessionHasErrors('prix_vente');

        $this->assertDatabaseCount('conditionnements', 0);
    }

    public function test_un_seul_format_reste_par_defaut(): void
    {
        $c = $this->contexte();
        $premier = $this->sacDe50($c);

        $this->actingAs($c['patron'])
            ->post("/produits/{$c['produit']->id}/conditionnements", [
                'libelle' => 'Bidon de 5 L',
                'facteur' => 5,
                'prix_vente' => 3500,
                'par_defaut' => 1,
            ])
            ->assertRedirect();

        $this->assertFalse($premier->fresh()->par_defaut);
        $this->assertSame(1, Conditionnement::where('par_defaut', true)->count());
    }

    public function test_un_vendeur_ne_peut_pas_ajouter_de_format(): void
    {
        $c = $this->contexte();

        $this->actingAs($c['vendeur'])
            ->post("/produits/{$c['produit']->id}/conditionnements", [
                'libelle' => 'Sac de 50 kg',
                'facteur' => 50,
                'prix_vente' => 28000,
            ])
            ->assertStatus(403);
    }

    public function test_retirer_un_format_le_desactive_sans_effacer_l_historique(): void
    {
        $c = $this->contexte();
        $sac = $this->sacDe50($c);

        $this->actingAs($c['vendeur'])->post('/ventes', [
            'boutique_id' => $c['boutique']->id,
            'mode_paiement' => ModePaiement::Especes->value,
            'lignes' => [['produit_id' => $c['produit']->id, 'quantite' => 1, 'conditionnement_id' => $sac->id]],
        ])->assertRedirect();

        $this->actingAs($c['patron'])->delete("/conditionnements/{$sac->id}")->assertRedirect();

        $this->assertFalse($sac->fresh()->actif);
        // La ligne de vente pointe toujours dessus : la facture reste lisible.
        $this->assertDatabaseHas('ligne_ventes', ['conditionnement_id' => $sac->id]);
    }

    // --- Vente ------------------------------------------------------------

    public function test_vendre_au_sac_decremente_le_stock_du_contenu_du_sac(): void
    {
        $c = $this->contexte(1000);
        $sac = $this->sacDe50($c, 28000);

        $this->actingAs($c['vendeur'])->post('/ventes', [
            'boutique_id' => $c['boutique']->id,
            'mode_paiement' => ModePaiement::Especes->value,
            'lignes' => [['produit_id' => $c['produit']->id, 'quantite' => 2, 'conditionnement_id' => $sac->id]],
        ])->assertRedirect();

        // 2 sacs = 100 kg prélevés, à 560 F le kilo.
        $this->assertDatabaseHas('stock_boutiques', ['id' => $c['stock']->id, 'quantite' => 900]);
        $this->assertDatabaseHas('ligne_ventes', [
            'produit_id' => $c['produit']->id,
            'conditionnement_id' => $sac->id,
            'quantite' => 100,
            'prix_unitaire' => 560,
        ]);

        // Le total facturé est bien celui des sacs, sans perte à l'arrondi.
        $this->assertSame(56000, Vente::with('lignes')->firstOrFail()->montantTotal());
        $this->assertDatabaseHas('paiements', ['montant' => 56000]);
    }

    public function test_le_meme_produit_se_vend_au_detail_au_prix_catalogue(): void
    {
        $c = $this->contexte(1000, 600);
        $this->sacDe50($c, 28000);

        $this->actingAs($c['vendeur'])->post('/ventes', [
            'boutique_id' => $c['boutique']->id,
            'mode_paiement' => ModePaiement::Especes->value,
            'lignes' => [['produit_id' => $c['produit']->id, 'quantite' => 3]],
        ])->assertRedirect();

        // Sans format : 3 kg à 600 F, le tarif détail — plus cher au kilo que
        // le sac, ce qui est précisément l'intérêt du conditionnement.
        $this->assertDatabaseHas('stock_boutiques', ['id' => $c['stock']->id, 'quantite' => 997]);
        $this->assertDatabaseHas('ligne_ventes', ['quantite' => 3, 'prix_unitaire' => 600, 'conditionnement_id' => null]);
    }

    public function test_le_stock_insuffisant_se_juge_en_unites_de_base(): void
    {
        // 60 kg en stock : deux sacs de 50 kg en demandent 100.
        $c = $this->contexte(60);
        $sac = $this->sacDe50($c);

        $this->actingAs($c['vendeur'])->post('/ventes', [
            'boutique_id' => $c['boutique']->id,
            'mode_paiement' => ModePaiement::Especes->value,
            'lignes' => [['produit_id' => $c['produit']->id, 'quantite' => 2, 'conditionnement_id' => $sac->id]],
        ])->assertSessionHasErrors('lignes.0.quantite');

        $this->assertDatabaseCount('ventes', 0);
        $this->assertDatabaseHas('stock_boutiques', ['id' => $c['stock']->id, 'quantite' => 60]);
    }

    public function test_un_format_d_un_autre_produit_est_refuse(): void
    {
        $c = $this->contexte();
        $autreProduit = Produit::factory()->create(['tenant_id' => $c['tenant']->id]);
        $formatEtranger = Conditionnement::factory()->create([
            'tenant_id' => $c['tenant']->id,
            'produit_id' => $autreProduit->id,
            'facteur' => 50,
            'prix_vente' => 5000,
        ]);

        $this->actingAs($c['vendeur'])->post('/ventes', [
            'boutique_id' => $c['boutique']->id,
            'mode_paiement' => ModePaiement::Especes->value,
            'lignes' => [['produit_id' => $c['produit']->id, 'quantite' => 1, 'conditionnement_id' => $formatEtranger->id]],
        ])->assertSessionHasErrors('lignes.0.conditionnement_id');

        $this->assertDatabaseCount('ventes', 0);
    }

    public function test_un_format_d_un_autre_tenant_est_refuse(): void
    {
        $c = $this->contexte();
        $autreTenant = Tenant::factory()->create();
        $produitVoisin = Produit::factory()->create(['tenant_id' => $autreTenant->id]);
        $formatVoisin = Conditionnement::factory()->create([
            'tenant_id' => $autreTenant->id,
            'produit_id' => $produitVoisin->id,
        ]);

        $this->actingAs($c['vendeur'])->post('/ventes', [
            'boutique_id' => $c['boutique']->id,
            'mode_paiement' => ModePaiement::Especes->value,
            'lignes' => [['produit_id' => $c['produit']->id, 'quantite' => 1, 'conditionnement_id' => $formatVoisin->id]],
        ])->assertSessionHasErrors('lignes.0.conditionnement_id');

        $this->assertDatabaseCount('ventes', 0);
    }

    public function test_une_promotion_s_applique_par_unite_de_base_sur_un_format(): void
    {
        $c = $this->contexte(1000, 600);
        $sac = $this->sacDe50($c, 28000);   // 560 F le kilo

        Promotion::factory()->create([
            'tenant_id' => $c['tenant']->id,
            'portee' => PorteePromotion::Produit,
            'produit_id' => $c['produit']->id,
            'type' => TypePromotion::Pourcentage,
            'valeur' => 10,                  // 10 % de 600 = 60 F par kilo
            'actif' => true,
            'date_debut' => now()->subDay()->toDateString(),
            'date_fin' => now()->addDay()->toDateString(),
            'cree_par_id' => $c['patron']->id,
        ]);

        $this->actingAs($c['vendeur'])->post('/ventes', [
            'boutique_id' => $c['boutique']->id,
            'mode_paiement' => ModePaiement::Especes->value,
            'lignes' => [['produit_id' => $c['produit']->id, 'quantite' => 1, 'conditionnement_id' => $sac->id]],
        ])->assertRedirect();

        // La remise vaut le même montant par kilo, qu'on achète au sac ou au
        // détail : 560 - 60 = 500 F.
        $this->assertDatabaseHas('ligne_ventes', ['quantite' => 50, 'prix_unitaire' => 500]);
    }

    public function test_la_quantite_est_affichee_dans_le_format_du_client(): void
    {
        $c = $this->contexte();
        $sac = $this->sacDe50($c);

        $this->actingAs($c['vendeur'])->post('/ventes', [
            'boutique_id' => $c['boutique']->id,
            'mode_paiement' => ModePaiement::Especes->value,
            'lignes' => [['produit_id' => $c['produit']->id, 'quantite' => 2, 'conditionnement_id' => $sac->id]],
        ])->assertRedirect();

        $ligne = LigneVente::with(['produit', 'conditionnement'])->firstOrFail();

        $this->assertSame('2 × Sac de 50 kg = 100 kg', $ligne->quantiteLisible());
    }

    // --- Achat ------------------------------------------------------------

    public function test_commander_au_sac_convertit_quantite_et_prix_en_unites_de_base(): void
    {
        $c = $this->contexte();
        $sac = $this->sacDe50($c);
        $fournisseur = Fournisseur::factory()->create(['tenant_id' => $c['tenant']->id]);
        $gerant = User::factory()->create([
            'tenant_id' => $c['tenant']->id,
            'role' => UserRole::Gerant,
            'boutique_id' => $c['boutique']->id,
        ]);

        $this->actingAs($gerant)->post('/achats', [
            'boutique_id' => $c['boutique']->id,
            'fournisseur_id' => $fournisseur->id,
            'lignes' => [[
                'produit_id' => $c['produit']->id,
                'conditionnement_id' => $sac->id,
                'quantite' => 20,            // 20 sacs
                'prix_unitaire' => 22500,    // prix d'un sac
            ]],
        ])->assertRedirect();

        // 20 sacs = 1000 kg, à 450 F le kilo.
        $this->assertDatabaseHas('ligne_bon_commandes', [
            'produit_id' => $c['produit']->id,
            'conditionnement_id' => $sac->id,
            'quantite' => 1000,
            'prix_unitaire' => 450,
        ]);

        $ligne = LigneBonCommande::with(['produit', 'conditionnement'])->firstOrFail();
        $this->assertSame(450000, $ligne->sousTotal());
        $this->assertSame('20 × Sac de 50 kg = 1000 kg', $ligne->quantiteLisible());
    }

    public function test_un_prix_d_achat_non_divisible_par_le_format_est_refuse(): void
    {
        $c = $this->contexte();
        $sac = $this->sacDe50($c);
        $fournisseur = Fournisseur::factory()->create(['tenant_id' => $c['tenant']->id]);
        $gerant = User::factory()->create([
            'tenant_id' => $c['tenant']->id,
            'role' => UserRole::Gerant,
            'boutique_id' => $c['boutique']->id,
        ]);

        $this->actingAs($gerant)->post('/achats', [
            'boutique_id' => $c['boutique']->id,
            'fournisseur_id' => $fournisseur->id,
            'lignes' => [[
                'produit_id' => $c['produit']->id,
                'conditionnement_id' => $sac->id,
                'quantite' => 20,
                'prix_unitaire' => 22510,
            ]],
        ])->assertSessionHasErrors('lignes.0.prix_unitaire');

        $this->assertDatabaseCount('ligne_bon_commandes', 0);
    }

    // --- Produits non détaillables ----------------------------------------

    public function test_un_produit_peut_etre_cree_sans_prix_au_detail(): void
    {
        $c = $this->contexte();

        $this->actingAs($c['patron'])->post('/produits', [
            'nom' => 'Semence de maïs traitée',
            'type' => TypeProduit::IntrantAgricole->value,
            'unite_mesure' => UniteMesure::Kilogramme->value,
            'prix_achat' => 1200,
            // Sachet scellé : il ne s'ouvre pas au comptoir.
            'prix_vente' => null,
            'stock_min' => 5,
            'stock_max' => 500,
            'boutique_id' => $c['boutique']->id,
            'quantite_initiale' => 100,
            'numero_lot' => 'LOT-SEM-1',
            'date_peremption' => now()->addYear()->toDateString(),
        ])->assertRedirect();

        $produit = Produit::where('nom', 'Semence de maïs traitée')->firstOrFail();

        $this->assertNull($produit->prix_vente);
        $this->assertFalse($produit->estDetaillable());
    }

    public function test_un_produit_non_detaillable_ne_peut_pas_etre_vendu_a_la_mesure(): void
    {
        $c = $this->contexte();
        $c['produit']->update(['prix_vente' => null]);
        $this->sacDe50($c);

        $this->actingAs($c['vendeur'])->post('/ventes', [
            'boutique_id' => $c['boutique']->id,
            'mode_paiement' => ModePaiement::Especes->value,
            // Aucun format retenu : c'est une vente au kilo.
            'lignes' => [['produit_id' => $c['produit']->id, 'quantite' => 3]],
        ])->assertSessionHasErrors('lignes.0.conditionnement_id');

        $this->assertDatabaseCount('ventes', 0);
        $this->assertDatabaseHas('stock_boutiques', ['id' => $c['stock']->id, 'quantite' => 1000]);
    }

    public function test_un_produit_non_detaillable_se_vend_normalement_au_format(): void
    {
        $c = $this->contexte();
        $c['produit']->update(['prix_vente' => null]);
        $sac = $this->sacDe50($c, 28000);

        $this->actingAs($c['vendeur'])->post('/ventes', [
            'boutique_id' => $c['boutique']->id,
            'mode_paiement' => ModePaiement::Especes->value,
            'lignes' => [['produit_id' => $c['produit']->id, 'quantite' => 2, 'conditionnement_id' => $sac->id]],
        ])->assertRedirect();

        $this->assertDatabaseHas('ligne_ventes', ['quantite' => 100, 'prix_unitaire' => 560]);
        $this->assertSame(56000, Vente::with('lignes')->firstOrFail()->montantTotal());
    }

    public function test_une_promotion_s_applique_au_prix_du_format_quand_il_n_y_a_pas_de_prix_au_detail(): void
    {
        $c = $this->contexte();
        $c['produit']->update(['prix_vente' => null]);
        $sac = $this->sacDe50($c, 28000);   // 560 F le kilo

        Promotion::factory()->create([
            'tenant_id' => $c['tenant']->id,
            'portee' => PorteePromotion::Produit,
            'produit_id' => $c['produit']->id,
            'type' => TypePromotion::Pourcentage,
            'valeur' => 10,
            'actif' => true,
            'date_debut' => now()->subDay()->toDateString(),
            'date_fin' => now()->addDay()->toDateString(),
            'cree_par_id' => $c['patron']->id,
        ]);

        $this->actingAs($c['vendeur'])->post('/ventes', [
            'boutique_id' => $c['boutique']->id,
            'mode_paiement' => ModePaiement::Especes->value,
            'lignes' => [['produit_id' => $c['produit']->id, 'quantite' => 1, 'conditionnement_id' => $sac->id]],
        ])->assertRedirect();

        // Faute de prix au détail, le barème part du prix unitaire du format :
        // 10 % de 560 = 56, soit 504 F le kilo.
        $this->assertDatabaseHas('ligne_ventes', ['quantite' => 50, 'prix_unitaire' => 504]);
    }

    public function test_la_fiche_signale_un_produit_ni_detaillable_ni_conditionne(): void
    {
        $c = $this->contexte();
        $c['produit']->update(['prix_vente' => null]);

        $this->assertFalse($c['produit']->fresh()->estVendable());

        $this->actingAs($c['patron'])
            ->get("/produits/{$c['produit']->id}")
            ->assertOk()
            // Fragment sans apostrophe : Blade les échappe en &#039; dans le HTML.
            ->assertSee('ni prix au détail, ni format', false);
    }

    public function test_l_instantane_hors_ligne_indique_les_produits_non_detaillables(): void
    {
        $c = $this->contexte();
        $c['produit']->update(['prix_vente' => null]);
        $sac = $this->sacDe50($c, 28000);

        $reponse = $this->actingAs($c['vendeur'])->getJson('/sync/catalogue');

        $reponse->assertOk();
        $reponse->assertJsonPath('produits.0.detaillable', false);
        $reponse->assertJsonPath('produits.0.prix', null);
        $reponse->assertJsonPath('produits.0.conditionnements.0.id', $sac->id);
    }

    public function test_la_synchronisation_refuse_une_vente_a_la_mesure_sans_prix_au_detail(): void
    {
        $c = $this->contexte();
        $c['produit']->update(['prix_vente' => null]);

        $reponse = $this->actingAs($c['vendeur'])->postJson('/sync/ventes', [
            'ventes' => [[
                'uuid_client' => (string) Str::uuid(),
                'encaissee_le' => now()->subHour()->toIso8601String(),
                'boutique_id' => $c['boutique']->id,
                'client_id' => null,
                'mode_paiement' => ModePaiement::Especes->value,
                'montant_paye' => 1800,
                'date_echeance' => null,
                'lignes' => [['produit_id' => $c['produit']->id, 'quantite' => 3]],
            ]],
        ]);

        // Sans prix, aucun montant véridique n'est établissable : la vente
        // reste dans la file du vendeur plutôt que d'entrer à zéro franc.
        $reponse->assertOk();
        $reponse->assertJsonPath('resultats.0.statut', 'refusee');
        $this->assertDatabaseCount('ventes', 0);
    }
}
