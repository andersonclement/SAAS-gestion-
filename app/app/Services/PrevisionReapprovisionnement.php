<?php

namespace App\Services;

use App\Models\Conditionnement;
use App\Models\Produit;
use App\Models\StockBoutique;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Prévision des besoins de réapprovisionnement (§4.6 du cahier des charges).
 *
 * L'objectif n'est pas de deviner l'avenir, mais de répondre à une question
 * concrète que le gérant se pose chaque semaine : **qu'est-ce que je dois
 * commander, et combien ?**
 *
 * La méthode est délibérément simple et explicable au commerçant :
 *
 * 1. **Moyenne mobile pondérée** sur les douze dernières semaines. Les semaines
 *    récentes pèsent plus lourd que les anciennes — une baisse installée depuis
 *    un mois compte davantage qu'un pic d'il y a trois mois.
 * 2. **Coefficient saisonnier** : les intrants agricoles suivent le calendrier
 *    cultural, pas la moyenne annuelle. On compare le même mois l'an dernier à
 *    la moyenne de l'année. Sans une année d'historique, le coefficient reste à
 *    1 et l'écran le dit — mieux vaut afficher « pas encore de saisonnalité »
 *    qu'un chiffre inventé.
 * 3. **Besoin** = demande prévue sur l'horizon + stock de sécurité − stock réel.
 *
 * Aucun apprentissage automatique : sur des séries courtes et bruitées comme
 * celles d'une boutique, une moyenne pondérée bien posée fait aussi bien qu'un
 * modèle, et le gérant peut vérifier le calcul de tête.
 *
 * Limite assumée : les retours ne sont pas déduits de la demande. Ils sont
 * marginaux en volume, et les rattacher à la date de vente d'origine
 * introduirait une approximation de plus.
 */
class PrevisionReapprovisionnement
{
    /** Fenêtre d'observation, en semaines. */
    private const SEMAINES_OBSERVEES = 12;

    /** Historique minimal pour que la prévision soit jugée exploitable. */
    private const SEMAINES_MINIMALES = 4;

    /** Historique nécessaire pour calculer une saisonnalité. */
    private const JOURS_POUR_SAISONNALITE = 365;

    /**
     * Un coefficient saisonnier issu d'un mois creux peut être aberrant :
     * on le borne pour qu'une donnée mince ne produise pas une commande absurde.
     */
    private const COEFFICIENT_MIN = 0.5;

    private const COEFFICIENT_MAX = 2.0;

    /**
     * Prévisions pour une boutique, produit par produit.
     *
     * @param  int  $delaiLivraison  Jours entre la commande et la réception.
     * @param  int  $couverture  Jours de vente que la commande doit couvrir
     *                           une fois livrée.
     * @return Collection<int, array<string, mixed>>
     */
    public function pour(int $boutiqueId, int $delaiLivraison = 7, int $couverture = 30, ?Carbon $aujourdhui = null): Collection
    {
        $aujourdhui = ($aujourdhui ?? Carbon::today())->copy()->startOfDay();
        $horizon = $delaiLivraison + $couverture;

        $ventesParJour = $this->ventesParJour($boutiqueId, $aujourdhui);
        $stocks = $this->stocksParProduit($boutiqueId);

        $produits = Produit::with(['conditionnements' => fn ($q) => $q->where('actif', true)])
            ->whereIn('id', $ventesParJour->keys()->merge($stocks->keys())->unique())
            ->where('actif', true)
            ->orderBy('nom')
            ->get();

        return $produits
            ->map(function (Produit $produit) use ($ventesParJour, $stocks, $aujourdhui, $horizon, $delaiLivraison) {
                $historique = $ventesParJour->get($produit->id, collect());

                $demandeJournaliere = $this->demandeJournaliere($historique, $aujourdhui);
                $coefficient = $this->coefficientSaisonnier($historique, $aujourdhui);
                $demandeAjustee = $demandeJournaliere * $coefficient;

                $stock = (int) $stocks->get($produit->id, 0);
                $besoin = (int) ceil(max(0, ($demandeAjustee * $horizon) + $produit->stock_min - $stock));

                $conditionnement = $produit->conditionnements->firstWhere('par_defaut', true)
                    ?? $produit->conditionnements->first();

                return [
                    'produit' => $produit,
                    'stock' => $stock,
                    'semaines_observees' => $this->semainesAvecVentes($historique, $aujourdhui),
                    'demande_journaliere' => round($demandeAjustee, 2),
                    'coefficient_saisonnier' => round($coefficient, 2),
                    'saisonnalite_disponible' => $this->historiqueSuffisantPourSaisonnalite($historique, $aujourdhui),
                    'jours_avant_rupture' => $demandeAjustee > 0 ? (int) floor($stock / $demandeAjustee) : null,
                    'date_rupture' => $demandeAjustee > 0
                        ? $aujourdhui->copy()->addDays((int) floor($stock / $demandeAjustee))
                        : null,
                    'commander_avant' => $demandeAjustee > 0
                        ? $aujourdhui->copy()->addDays(max(0, (int) floor($stock / $demandeAjustee) - $delaiLivraison))
                        : null,
                    'besoin' => $besoin,
                    'suggestion' => $this->arrondirAuConditionnement($besoin, $conditionnement),
                    'conditionnement' => $besoin > 0 ? $conditionnement : null,
                    'fiable' => $this->semainesAvecVentes($historique, $aujourdhui) >= self::SEMAINES_MINIMALES,
                ];
            })
            ->values();
    }

    /**
     * Quantités vendues par produit et par jour sur la fenêtre d'observation.
     *
     * La date retenue est celle de l'encaissement réel : une vente hors-ligne
     * du 3 remontée le 5 appartient à la demande du 3, sinon la série serait
     * creusée un jour et bosselée le suivant.
     *
     * @return Collection<int, Collection<string, int>>
     */
    private function ventesParJour(int $boutiqueId, Carbon $aujourdhui): Collection
    {
        // On remonte au-delà de la fenêtre d'observation : l'année écoulée sert
        // au calcul de la saisonnalité.
        $debut = $aujourdhui->copy()->subDays(self::JOURS_POUR_SAISONNALITE + 31);

        return DB::table('ligne_ventes')
            ->join('ventes', 'ventes.id', '=', 'ligne_ventes.vente_id')
            ->where('ventes.boutique_id', $boutiqueId)
            ->whereRaw('COALESCE(ventes.encaissee_le, ventes.created_at) >= ?', [$debut])
            ->selectRaw('ligne_ventes.produit_id, DATE(COALESCE(ventes.encaissee_le, ventes.created_at)) as jour, SUM(ligne_ventes.quantite) as quantite')
            ->groupBy('ligne_ventes.produit_id', 'jour')
            ->get()
            ->groupBy('produit_id')
            ->map(fn (Collection $lignes) => $lignes->mapWithKeys(
                fn ($ligne) => [(string) $ligne->jour => (int) $ligne->quantite]
            ));
    }

    /**
     * @return Collection<int, int>
     */
    private function stocksParProduit(int $boutiqueId): Collection
    {
        return StockBoutique::where('boutique_id', $boutiqueId)
            ->selectRaw('produit_id, SUM(quantite) as quantite_totale')
            ->groupBy('produit_id')
            ->pluck('quantite_totale', 'produit_id')
            ->map(fn ($quantite) => (int) $quantite);
    }

    /**
     * Moyenne mobile pondérée : la semaine la plus récente pèse
     * SEMAINES_OBSERVEES, la plus ancienne 1.
     *
     * @param  Collection<string, int>  $historique
     */
    private function demandeJournaliere(Collection $historique, Carbon $aujourdhui): float
    {
        $numerateur = 0.0;
        $denominateur = 0.0;

        for ($semaine = 0; $semaine < self::SEMAINES_OBSERVEES; $semaine++) {
            $poids = self::SEMAINES_OBSERVEES - $semaine;
            $fin = $aujourdhui->copy()->subDays($semaine * 7);
            $debut = $fin->copy()->subDays(6);

            $numerateur += $poids * $this->totalEntre($historique, $debut, $fin);
            $denominateur += $poids * 7;
        }

        return $denominateur > 0 ? $numerateur / $denominateur : 0.0;
    }

    /**
     * Rapport entre la demande du mois en cours l'an dernier et la demande
     * moyenne de cette année-là. 1,4 signifie « ce mois-ci se vend 40 % de plus
     * que la moyenne » — le pic des semis, par exemple.
     *
     * @param  Collection<string, int>  $historique
     */
    private function coefficientSaisonnier(Collection $historique, Carbon $aujourdhui): float
    {
        if (! $this->historiqueSuffisantPourSaisonnalite($historique, $aujourdhui)) {
            return 1.0;
        }

        $finAnnee = $aujourdhui->copy()->subYear();
        $debutAnnee = $finAnnee->copy()->subDays(364);
        $totalAnnee = $this->totalEntre($historique, $debutAnnee, $finAnnee);

        if ($totalAnnee <= 0) {
            return 1.0;
        }

        $moyenneJournaliereAnnee = $totalAnnee / 365;

        $debutMois = $aujourdhui->copy()->subYear()->startOfMonth();
        $finMois = $debutMois->copy()->endOfMonth();
        $joursDuMois = $debutMois->daysInMonth;
        $moyenneJournaliereMois = $this->totalEntre($historique, $debutMois, $finMois) / $joursDuMois;

        $coefficient = $moyenneJournaliereMois / $moyenneJournaliereAnnee;

        return min(self::COEFFICIENT_MAX, max(self::COEFFICIENT_MIN, $coefficient));
    }

    /**
     * @param  Collection<string, int>  $historique
     */
    private function historiqueSuffisantPourSaisonnalite(Collection $historique, Carbon $aujourdhui): bool
    {
        $premierJour = $historique->keys()->min();

        return $premierJour !== null
            && Carbon::parse($premierJour)->diffInDays($aujourdhui) >= self::JOURS_POUR_SAISONNALITE;
    }

    /**
     * Nombre de semaines de la fenêtre d'observation où au moins une vente a eu
     * lieu. C'est l'indicateur de confiance : une prévision bâtie sur deux
     * semaines de données n'en est pas une.
     *
     * @param  Collection<string, int>  $historique
     */
    private function semainesAvecVentes(Collection $historique, Carbon $aujourdhui): int
    {
        $semaines = 0;

        for ($semaine = 0; $semaine < self::SEMAINES_OBSERVEES; $semaine++) {
            $fin = $aujourdhui->copy()->subDays($semaine * 7);
            $debut = $fin->copy()->subDays(6);

            if ($this->totalEntre($historique, $debut, $fin) > 0) {
                $semaines++;
            }
        }

        return $semaines;
    }

    /**
     * @param  Collection<string, int>  $historique
     */
    private function totalEntre(Collection $historique, Carbon $debut, Carbon $fin): int
    {
        $total = 0;

        foreach ($historique as $jour => $quantite) {
            if ($jour >= $debut->toDateString() && $jour <= $fin->toDateString()) {
                $total += $quantite;
            }
        }

        return $total;
    }

    /**
     * Arrondit le besoin au conditionnement supérieur : on ne commande pas
     * 37 kg à un fournisseur qui livre par sacs de 50.
     */
    private function arrondirAuConditionnement(int $besoin, ?Conditionnement $conditionnement): int
    {
        if ($besoin <= 0 || ! $conditionnement || $conditionnement->facteur <= 1) {
            return $besoin;
        }

        return (int) ceil($besoin / $conditionnement->facteur) * $conditionnement->facteur;
    }
}
