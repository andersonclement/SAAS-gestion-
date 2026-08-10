<?php

namespace App\Http\Controllers;

use App\Models\Boutique;
use App\Models\StockBoutique;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class StockController extends Controller
{
    /**
     * Suivi du stock en temps réel par boutique, avec alertes de seuil
     * minimum et de péremption proche (§4.4 et §4.8 du cahier des charges).
     */
    public function index(): View
    {
        $user = Auth::user();

        $boutiques = $user->boutique_id
            ? Boutique::whereKey($user->boutique_id)->get()
            : Boutique::orderBy('nom')->get();

        $stocks = StockBoutique::with(['boutique', 'produit', 'lot'])
            ->whereIn('boutique_id', $boutiques->pluck('id'))
            ->get()
            ->sortBy([['boutique.nom', 'asc'], ['produit.nom', 'asc']]);

        return view('stock.index', compact('boutiques', 'stocks'));
    }
}
