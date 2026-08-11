<?php

namespace App\Http\Controllers;

use App\Models\Boutique;
use App\Models\LigneVente;
use App\Models\StockBoutique;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class ComparatifController extends Controller
{
    /**
     * Comparatif entre boutiques (§4.17) : chiffre d'affaires, marge et
     * rotation de stock du mois en cours, classées par performance.
     * Réservé au patron/comptable qui ont seuls une vision multi-boutiques.
     */
    public function index(): View
    {
        $user = Auth::user();

        abort_unless($user->isPatron() || $user->isComptable(), 403);

        $debutMois = Carbon::now()->startOfMonth();
        $boutiques = Boutique::orderBy('nom')->get();

        $statistiques = $boutiques->map(function (Boutique $boutique) use ($debutMois) {
            $lignesDuMois = LigneVente::whereHas('vente', fn ($query) => $query
                ->where('boutique_id', $boutique->id)
                ->where('created_at', '>=', $debutMois));

            $chiffreAffaires = (clone $lignesDuMois)->selectRaw('SUM(quantite * prix_unitaire) as total')->value('total') ?? 0;

            $marge = (clone $lignesDuMois)
                ->join('produits', 'ligne_ventes.produit_id', '=', 'produits.id')
                ->selectRaw('SUM(ligne_ventes.quantite * (ligne_ventes.prix_unitaire - produits.prix_achat)) as marge')
                ->value('marge') ?? 0;

            $quantiteVendue = (clone $lignesDuMois)->sum('quantite');

            $quantiteEnStock = StockBoutique::where('boutique_id', $boutique->id)->sum('quantite');

            return [
                'boutique' => $boutique,
                'chiffre_affaires' => $chiffreAffaires,
                'marge' => $marge,
                'quantite_vendue' => $quantiteVendue,
                'quantite_en_stock' => $quantiteEnStock,
                'rotation_stock' => $quantiteEnStock > 0 ? round($quantiteVendue / $quantiteEnStock, 2) : null,
            ];
        })->sortByDesc('chiffre_affaires')->values();

        return view('comparatif.index', compact('statistiques'));
    }
}
