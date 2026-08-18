<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreProduitRequest;
use App\Http\Requests\UpdateProduitRequest;
use App\Models\Boutique;
use App\Models\Categorie;
use App\Models\Conditionnement;
use App\Models\Lot;
use App\Models\Produit;
use App\Models\StockBoutique;
use App\Support\Journal;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
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

        $user = Auth::user();
        $boutiques = $user->boutique_id
            ? Boutique::whereKey($user->boutique_id)->get()
            : Boutique::orderBy('nom')->get();

        return view('produits.create', compact('categories', 'boutiques'));
    }

    /**
     * Crée le produit et son premier lot en stock d'un seul tenant : bornes de
     * stock, quantité, numéro de lot et date de péremption sont saisis
     * ensemble, si bien qu'aucun produit ne peut exister au catalogue sans
     * péremption connue ni seuils d'alerte.
     */
    public function store(StoreProduitRequest $request): RedirectResponse
    {
        $validated = $request->validated();

        $produit = DB::transaction(function () use ($validated) {
            $produit = Produit::create($validated);

            $lot = Lot::create([
                'produit_id' => $produit->id,
                'numero_lot' => $validated['numero_lot'],
                'date_fabrication' => $validated['date_fabrication'] ?? null,
                'date_peremption' => $validated['date_peremption'] ?? null,
            ]);

            StockBoutique::create([
                'boutique_id' => $validated['boutique_id'],
                'produit_id' => $produit->id,
                'lot_id' => $lot->id,
                'quantite' => $validated['quantite_initiale'],
            ]);

            // Formats de vente déclarés dans le même formulaire : le produit
            // est vendable dès sa création, sans passage obligé par sa fiche.
            $parDefaut = $validated['conditionnement_par_defaut'] ?? null;

            foreach ($validated['conditionnements'] ?? [] as $index => $format) {
                Conditionnement::create([
                    'produit_id' => $produit->id,
                    'libelle' => $format['libelle'],
                    'facteur' => $format['facteur'],
                    'prix_vente' => $format['prix_vente'],
                    'par_defaut' => (int) $parDefaut === $index,
                ]);
            }

            Journal::enregistrer('produit.cree', __(
                ':produit ajouté au catalogue — stock initial :quantite (lot :lot, péremption :peremption, :formats).',
                [
                    'produit' => $produit->nom,
                    'quantite' => $validated['quantite_initiale'],
                    'lot' => $lot->numero_lot,
                    'peremption' => $lot->date_peremption?->format('d/m/Y') ?? __('sans objet'),
                    'formats' => trans_choice(
                        '{0}aucun format de vente|{1}1 format de vente|[2,*]:count formats de vente',
                        count($validated['conditionnements'] ?? [])
                    ),
                ]
            ), (int) $validated['boutique_id']);

            return $produit;
        });

        return redirect()->route('produits.show', $produit)->with('status', __('Produit créé et stock initial enregistré.'));
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
