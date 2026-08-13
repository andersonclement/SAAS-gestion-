<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreProduitRequest;
use App\Http\Requests\UpdateProduitRequest;
use App\Models\Categorie;
use App\Models\Produit;
use App\Support\Journal;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class ProduitController extends Controller
{
    public function index(): View
    {
        $this->authorize('viewAny', Produit::class);

        $recherche = trim((string) request()->query('q'));

        $produits = Produit::with('categorie')
            ->when($recherche !== '', fn ($query) => $query->where(fn ($q) => $q
                ->where('nom', 'like', "%{$recherche}%")
                ->orWhere('code_barres', 'like', "%{$recherche}%")))
            ->orderBy('nom')
            ->paginate(20)
            ->withQueryString();

        return view('produits.index', compact('produits', 'recherche'));
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

    public function edit(Produit $produit): View
    {
        $this->authorize('update', $produit);

        $categories = Categorie::orderBy('nom')->get();

        return view('produits.edit', compact('produit', 'categories'));
    }

    public function update(UpdateProduitRequest $request, Produit $produit): RedirectResponse
    {
        $ancienPrix = $produit->prix_vente;

        $produit->update($request->validated());

        // Un changement de prix de vente se répercute sur toutes les ventes
        // futures : on le trace explicitement, c'est une décision commerciale
        // que le patron doit pouvoir retrouver.
        if ($ancienPrix !== $produit->prix_vente) {
            Journal::enregistrer('produit.prix_modifie', __(
                'Prix de vente de :produit modifié : :ancien → :nouveau FCFA.',
                [
                    'produit' => $produit->nom,
                    'ancien' => number_format($ancienPrix, 0, ',', ' '),
                    'nouveau' => number_format($produit->prix_vente, 0, ',', ' '),
                ]
            ));
        }

        return redirect()->route('produits.show', $produit)->with('status', __('Produit mis à jour.'));
    }
}
