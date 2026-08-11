<?php

namespace App\Http\Controllers;

use App\Http\Requests\StorePromotionRequest;
use App\Models\Categorie;
use App\Models\Client;
use App\Models\Produit;
use App\Models\Promotion;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class PromotionController extends Controller
{
    public function index(): View
    {
        $this->authorize('viewAny', Promotion::class);

        $promotions = Promotion::with(['produit', 'categorie', 'client'])->latest()->get();

        return view('promotions.index', compact('promotions'));
    }

    public function create(): View
    {
        $this->authorize('create', Promotion::class);

        $produits = Produit::orderBy('nom')->get();
        $categories = Categorie::orderBy('nom')->get();
        $clients = Client::orderBy('nom')->get();

        return view('promotions.create', compact('produits', 'categories', 'clients'));
    }

    public function store(StorePromotionRequest $request): RedirectResponse
    {
        Promotion::create($request->validated());

        return redirect()->route('promotions.index')->with('status', __('Promotion créée avec succès.'));
    }
}
