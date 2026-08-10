<?php

namespace App\Http\Controllers;

use App\Models\Boutique;
use App\Models\LigneVente;
use App\Models\Produit;
use App\Models\StockBoutique;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class DashboardController extends Controller
{
    /**
     * Tableau de bord (§4.7 du cahier des charges) : vue consolidée pour le
     * patron/comptable, vue restreinte à sa boutique pour gérant/vendeur.
     * Indicateurs : chiffre d'affaires, marge, meilleures ventes, stock dormant.
     */
    public function index(): View
    {
        $user = Auth::user();

        $boutiques = $user->isPatron() || $user->isComptable()
            ? Boutique::withCount('utilisateurs')->orderBy('nom')->get()
            : Boutique::withCount('utilisateurs')->whereKey($user->boutique_id)->get();

        $boutiqueIds = $boutiques->pluck('id');

        $lignesVendues = LigneVente::whereHas('vente', fn ($query) => $query->whereIn('boutique_id', $boutiqueIds));

        $chiffreAffairesTotal = (clone $lignesVendues)->selectRaw('SUM(quantite * prix_unitaire) as total')->value('total') ?? 0;

        $chiffreAffairesDuMois = (clone $lignesVendues)
            ->whereHas('vente', fn ($query) => $query->where('created_at', '>=', Carbon::now()->startOfMonth()))
            ->selectRaw('SUM(quantite * prix_unitaire) as total')
            ->value('total') ?? 0;

        $margeTotale = (clone $lignesVendues)
            ->join('produits', 'ligne_ventes.produit_id', '=', 'produits.id')
            ->selectRaw('SUM(ligne_ventes.quantite * (ligne_ventes.prix_unitaire - produits.prix_achat)) as marge')
            ->value('marge') ?? 0;

        $meilleuresVentes = (clone $lignesVendues)
            ->selectRaw('produit_id, SUM(quantite) as quantite_vendue, SUM(quantite * prix_unitaire) as chiffre_affaires')
            ->groupBy('produit_id')
            ->orderByDesc('quantite_vendue')
            ->with('produit')
            ->limit(5)
            ->get();

        $stockDormant = $this->produitsEnStockDormant($boutiqueIds);

        return view('dashboard', [
            'boutiques' => $boutiques,
            'nombreBoutiques' => $boutiques->count(),
            'chiffreAffairesTotal' => $chiffreAffairesTotal,
            'chiffreAffairesDuMois' => $chiffreAffairesDuMois,
            'margeTotale' => $margeTotale,
            'meilleuresVentes' => $meilleuresVentes,
            'stockDormant' => $stockDormant,
        ]);
    }

    /**
     * Produits encore en stock mais sans vente depuis 60 jours (ou jamais
     * vendus) : le "stock dormant" à écouler en priorité.
     */
    private function produitsEnStockDormant($boutiqueIds): Collection
    {
        $quantitesEnStock = StockBoutique::whereIn('boutique_id', $boutiqueIds)
            ->selectRaw('produit_id, SUM(quantite) as quantite_stock')
            ->groupBy('produit_id')
            ->havingRaw('SUM(quantite) > 0')
            ->pluck('quantite_stock', 'produit_id');

        if ($quantitesEnStock->isEmpty()) {
            return collect();
        }

        $dernieresVentes = LigneVente::whereHas('vente', fn ($query) => $query->whereIn('boutique_id', $boutiqueIds))
            ->whereIn('produit_id', $quantitesEnStock->keys())
            ->selectRaw('produit_id, MAX(ventes.created_at) as derniere_vente')
            ->join('ventes', 'ligne_ventes.vente_id', '=', 'ventes.id')
            ->groupBy('produit_id')
            ->pluck('derniere_vente', 'produit_id');

        $seuil = Carbon::now()->subDays(60);
        $produits = Produit::whereIn('id', $quantitesEnStock->keys())->get()->keyBy('id');

        return $quantitesEnStock
            ->filter(function ($quantite, $produitId) use ($dernieresVentes, $seuil) {
                $derniereVente = $dernieresVentes->get($produitId);

                return $derniereVente === null || Carbon::parse($derniereVente)->lt($seuil);
            })
            ->map(fn ($quantite, $produitId) => [
                'produit' => $produits->get($produitId),
                'quantite' => $quantite,
                'derniere_vente' => $dernieresVentes->get($produitId),
            ])
            ->values();
    }
}
