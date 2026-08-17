<?php

namespace App\Services;

use App\Models\Client;
use App\Models\Conditionnement;
use App\Models\Produit;
use App\Models\Promotion;
use App\Models\StockBoutique;
use App\Models\Vente;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;

/**
 * Prélèvement du stock d'une boutique pour une vente, en épuisant d'abord les
 * lots dont la péremption est la plus proche (FEFO — first-expired, first-out).
 *
 * Ce service est partagé par la caisse en ligne (VenteController) et par la
 * synchronisation des ventes hors-ligne (SynchronisationController), pour que
 * les deux chemins appliquent exactement la même règle d'allocation et le même
 * calcul de prix promotionnel.
 *
 * Deux comportements se distinguent uniquement par `$tolererPenurie` :
 *
 * - **En ligne** (défaut) : si le stock est insuffisant, la vente est refusée.
 *   Le client est devant le comptoir, on ne lui remet pas une marchandise
 *   qu'on n'a pas.
 * - **Hors-ligne** : la marchandise est déjà partie et l'argent encaissé. On
 *   alloue ce qui reste et on renvoie le manquant à l'appelant, qui le
 *   consigne comme écart à traiter. Voir la migration des écarts.
 */
class AllocationStock
{
    /**
     * Alloue `$quantiteDemandee` sur les lots disponibles et crée les lignes
     * de vente correspondantes.
     *
     * Les lignes de stock sont verrouillées (SELECT … FOR UPDATE) et la
     * disponibilité est re-vérifiée à l'intérieur du verrou : la validation de
     * la requête a lieu avant la transaction, donc deux ventes simultanées du
     * même produit pourraient sinon passer toutes les deux ce contrôle et
     * rendre le stock négatif (survente).
     *
     * @param  int  $quantiteSaisie  Quantité telle que le vendeur l'a saisie :
     *                               en nombre de conditionnements si l'un est
     *                               retenu, en unités de base sinon.
     * @param  Carbon|null  $date  Date de référence pour les promotions. Pour
     *                             une vente hors-ligne, c'est l'heure réelle de
     *                             l'encaissement, pas celle de la synchronisation.
     * @return int Quantité réellement allouée, en unités de base (égale à la
     *             quantité demandée hors pénurie tolérée).
     *
     * @throws ValidationException Stock insuffisant, hors pénurie tolérée.
     */
    public function allouer(
        Vente $vente,
        int $produitId,
        int $quantiteSaisie,
        int $boutiqueId,
        ?Client $client,
        ?Carbon $date = null,
        bool $tolererPenurie = false,
        ?Conditionnement $conditionnement = null,
    ): int {
        $produit = Produit::findOrFail($produitId);

        // Tout le reste du système compte en unités de base : la conversion se
        // fait ici, une fois, plutôt que dans chaque appelant.
        $quantiteDemandee = static::quantiteBase($quantiteSaisie, $conditionnement);

        $prixUnitaire = $this->prixApresPromotion(
            $produit,
            $client,
            $date,
            $conditionnement?->prixUnitaire()
        );

        $stocks = StockBoutique::query()
            ->where('boutique_id', $boutiqueId)
            ->where('produit_id', $produitId)
            ->where('quantite', '>', 0)
            ->lockForUpdate()
            ->get()
            ->load('lot')
            ->sortBy(fn (StockBoutique $stock) => $stock->lot->date_peremption ?? '9999-12-31')
            ->values();

        $disponible = (int) $stocks->sum('quantite');

        if ($disponible < $quantiteDemandee && ! $tolererPenurie) {
            throw ValidationException::withMessages([
                'lignes' => __('Stock insuffisant dans la boutique source (:disponible disponible).', [
                    'disponible' => $disponible,
                ]),
            ]);
        }

        $restant = min($quantiteDemandee, $disponible);
        $alloue = 0;

        foreach ($stocks as $stock) {
            if ($restant <= 0) {
                break;
            }

            $prelevement = min($restant, $stock->quantite);
            $stock->decrement('quantite', $prelevement);

            $vente->lignes()->create([
                'produit_id' => $produitId,
                'lot_id' => $stock->lot_id,
                'conditionnement_id' => $conditionnement?->id,
                'quantite' => $prelevement,
                'prix_unitaire' => $prixUnitaire,
            ]);

            $restant -= $prelevement;
            $alloue += $prelevement;
        }

        return $alloue;
    }

    /**
     * Quantité en unités de base correspondant à une saisie.
     */
    public static function quantiteBase(int $quantiteSaisie, ?Conditionnement $conditionnement): int
    {
        return $conditionnement ? $quantiteSaisie * $conditionnement->facteur : $quantiteSaisie;
    }

    /**
     * Prix unitaire après la meilleure promotion applicable (§4.16).
     *
     * La date de référence est explicite : une vente hors-ligne du 3 doit être
     * facturée au tarif du 3, même si elle n'est remontée que le 5 et que la
     * promotion a expiré entre-temps.
     *
     * `$prixBase` permet de partir du prix unitaire d'un conditionnement plutôt
     * que du prix catalogue. La remise, elle, reste calculée sur le barème du
     * produit : une promotion de 10 % vaut le même montant par kilo, qu'on
     * achète au sac ou au détail.
     */
    public function prixApresPromotion(Produit $produit, ?Client $client, ?Carbon $date = null, ?int $prixBase = null): int
    {
        $jour = ($date ?? Carbon::today())->copy()->startOfDay();

        // Un produit non détaillable n'a pas de prix au détail : le barème de
        // remise se calcule alors sur le prix unitaire du format retenu.
        $reference = $produit->prix_vente ?? $prixBase ?? 0;

        $meilleureRemise = Promotion::where('actif', true)
            ->where('date_debut', '<=', $jour)
            ->where('date_fin', '>=', $jour)
            ->get()
            ->filter(fn (Promotion $promotion) => $promotion->sApplique($produit, $client))
            ->map(fn (Promotion $promotion) => $promotion->remisePour($reference))
            ->max() ?? 0;

        return max(0, ($prixBase ?? $produit->prix_vente ?? 0) - $meilleureRemise);
    }
}
