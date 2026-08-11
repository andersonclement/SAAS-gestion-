<?php

namespace App\Support;

use App\Models\Boutique;
use App\Models\Produit;
use App\Models\StockBoutique;
use App\Models\Vente;
use Illuminate\Support\Collection;

/**
 * Centralise le calcul des alertes actives (§4.8 du cahier des charges) :
 * rupture de stock, stock faible, péremption proche/dépassée, échéances
 * de crédit en retard. Utilisé à la fois par la page "Alertes" et par le
 * badge de comptage dans la navigation, pour ne calculer la logique
 * qu'une seule fois.
 */
class CentreAlertes
{
    /**
     * @return array{ruptures: Collection, stockFaible: Collection, peremptionProche: Collection, perimes: Collection, creancesEnRetard: Collection}
     */
    public static function pour(Collection $boutiqueIds): array
    {
        $niveauxStock = StockBoutique::whereIn('boutique_id', $boutiqueIds)
            ->selectRaw('boutique_id, produit_id, SUM(quantite) as quantite_totale')
            ->groupBy('boutique_id', 'produit_id')
            ->get();

        $produits = Produit::whereIn('id', $niveauxStock->pluck('produit_id')->unique())->get()->keyBy('id');
        $boutiques = Boutique::whereIn('id', $boutiqueIds)->get()->keyBy('id');

        $ruptures = $niveauxStock
            ->filter(fn ($ligne) => (int) $ligne->quantite_totale === 0)
            ->map(fn ($ligne) => [
                'boutique' => $boutiques->get($ligne->boutique_id),
                'produit' => $produits->get($ligne->produit_id),
            ])
            ->values();

        $stockFaible = $niveauxStock
            ->filter(function ($ligne) use ($produits) {
                $produit = $produits->get($ligne->produit_id);

                return $produit && $ligne->quantite_totale > 0 && $ligne->quantite_totale <= $produit->seuil_alerte;
            })
            ->map(fn ($ligne) => [
                'boutique' => $boutiques->get($ligne->boutique_id),
                'produit' => $produits->get($ligne->produit_id),
                'quantite' => $ligne->quantite_totale,
            ])
            ->values();

        $stocksAvecLot = StockBoutique::with(['produit', 'lot', 'boutique'])
            ->whereIn('boutique_id', $boutiqueIds)
            ->where('quantite', '>', 0)
            ->whereHas('lot', fn ($query) => $query->whereNotNull('date_peremption'))
            ->get();

        $perimes = $stocksAvecLot->filter(fn (StockBoutique $stock) => $stock->lot->estPerime())->values();
        $peremptionProche = $stocksAvecLot->filter(fn (StockBoutique $stock) => $stock->lot->peremptionProche())->values();

        $creancesEnRetard = Vente::with(['client', 'boutique'])
            ->whereIn('boutique_id', $boutiqueIds)
            ->whereNotNull('date_echeance')
            ->where('date_echeance', '<', now())
            ->get()
            ->filter(fn (Vente $vente) => ! $vente->estSoldee())
            ->values();

        return [
            'ruptures' => $ruptures,
            'stockFaible' => $stockFaible,
            'peremptionProche' => $peremptionProche,
            'perimes' => $perimes,
            'creancesEnRetard' => $creancesEnRetard,
        ];
    }

    public static function compter(Collection $boutiqueIds): int
    {
        $alertes = static::pour($boutiqueIds);

        return array_sum(array_map(fn (Collection $liste) => $liste->count(), $alertes));
    }
}
