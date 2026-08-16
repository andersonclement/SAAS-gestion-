<?php

namespace App\Services;

use App\Models\Client;
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
     * @param  Carbon|null  $date  Date de référence pour les promotions. Pour
     *                             une vente hors-ligne, c'est l'heure réelle de
     *                             l'encaissement, pas celle de la synchronisation.
     * @return int Quantité réellement allouée (égale à la quantité demandée
     *             hors pénurie tolérée).
     *
     * @throws ValidationException Stock insuffisant, hors pénurie tolérée.
     */
    public function allouer(
        Vente $vente,
        int $produitId,
        int $quantiteDemandee,
        int $boutiqueId,
        ?Client $client,
        ?Carbon $date = null,
        bool $tolererPenurie = false,
    ): int {
        $produit = Produit::findOrFail($produitId);
        $prixUnitaire = $this->prixApresPromotion($produit, $client, $date);

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
                'quantite' => $prelevement,
                'prix_unitaire' => $prixUnitaire,
            ]);

            $restant -= $prelevement;
            $alloue += $prelevement;
        }

        return $alloue;
    }

    /**
     * Prix unitaire après la meilleure promotion applicable (§4.16).
     *
     * La date de référence est explicite : une vente hors-ligne du 3 doit être
     * facturée au tarif du 3, même si elle n'est remontée que le 5 et que la
     * promotion a expiré entre-temps.
     */
    public function prixApresPromotion(Produit $produit, ?Client $client, ?Carbon $date = null): int
    {
        $jour = ($date ?? Carbon::today())->copy()->startOfDay();

        $meilleureRemise = Promotion::where('actif', true)
            ->where('date_debut', '<=', $jour)
            ->where('date_fin', '>=', $jour)
            ->get()
            ->filter(fn (Promotion $promotion) => $promotion->sApplique($produit, $client))
            ->map(fn (Promotion $promotion) => $promotion->remisePour($produit->prix_vente))
            ->max() ?? 0;

        return $produit->prix_vente - $meilleureRemise;
    }
}
