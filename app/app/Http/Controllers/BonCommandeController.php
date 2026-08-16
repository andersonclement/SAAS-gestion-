<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreBonCommandeRequest;
use App\Models\BonCommande;
use App\Models\Boutique;
use App\Models\Conditionnement;
use App\Models\Fournisseur;
use App\Models\Produit;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class BonCommandeController extends Controller
{
    public function index(): View
    {
        $this->authorize('viewAny', BonCommande::class);

        $user = Auth::user();

        $bonsCommande = BonCommande::with(['boutique', 'fournisseur'])
            ->when($user->effectiveBoutiqueId(), fn ($query) => $query->where('boutique_id', $user->effectiveBoutiqueId()))
            ->latest()
            ->get();

        return view('achats.index', compact('bonsCommande'));
    }

    public function create(): View
    {
        $this->authorize('create', BonCommande::class);

        $user = Auth::user();

        $boutiques = $user->effectiveBoutiqueId()
            ? Boutique::whereKey($user->effectiveBoutiqueId())->get()
            : Boutique::orderBy('nom')->get();

        $fournisseurs = Fournisseur::orderBy('nom')->get();
        $produits = Produit::with(['conditionnements' => fn ($q) => $q->where('actif', true)])
            ->where('actif', true)->orderBy('nom')->get();

        return view('achats.create', compact('boutiques', 'fournisseurs', 'produits'));
    }

    public function store(StoreBonCommandeRequest $request): RedirectResponse
    {
        $bonCommande = DB::transaction(function () use ($request) {
            $bonCommande = BonCommande::create($request->safe()->only(['boutique_id', 'fournisseur_id']));

            foreach ($request->validated('lignes') as $ligne) {
                $conditionnement = ! empty($ligne['conditionnement_id'])
                    ? Conditionnement::find($ligne['conditionnement_id'])
                    : null;

                // Le bon de commande est saisi en formats ; tout le reste du
                // système — stock, réception, marges — compte en unités de base.
                if ($conditionnement) {
                    $ligne['prix_unitaire'] = intdiv((int) $ligne['prix_unitaire'], $conditionnement->facteur);
                    $ligne['quantite'] = (int) $ligne['quantite'] * $conditionnement->facteur;
                }

                $bonCommande->lignes()->create($ligne);
            }

            return $bonCommande;
        });

        return redirect()->route('achats.show', $bonCommande)->with('status', __('Bon de commande créé avec succès.'));
    }

    public function show(BonCommande $bonCommande): View
    {
        $this->authorize('view', $bonCommande);

        $bonCommande->load(['boutique', 'fournisseur', 'creePar', 'lignes.produit', 'lignes.lot']);

        return view('achats.show', compact('bonCommande'));
    }
}
