<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreProduitRequest;
use App\Models\Categorie;
use App\Models\Produit;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class ProduitController extends Controller
{
    public function index(): View
    {
        $this->authorize('viewAny', Produit::class);

        $produits = Produit::with('categorie')->orderBy('nom')->paginate(20);

        return view('produits.index', compact('produits'));
    }

    public function create(): View
    {
        $this->authorize('create', Produit::class);

        $categories = Categorie::orderBy('nom')->get();

        return view('produits.create', compact('categories'));
    }

    public function store(StoreProduitRequest $request): RedirectResponse
    {
        Produit::create($request->validated());

        return redirect()->route('produits.index')->with('status', __('Produit créé avec succès.'));
    }

    public function show(Produit $produit): View
    {
        $this->authorize('view', $produit);

        return view('produits.show', compact('produit'));
    }
}
