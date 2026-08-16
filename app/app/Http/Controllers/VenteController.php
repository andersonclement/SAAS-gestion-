<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreVenteRequest;
use App\Models\Boutique;
use App\Models\Client;
use App\Models\Paiement;
use App\Models\Produit;
use App\Models\Vente;
use App\Services\AllocationStock;
use App\Support\Journal;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class VenteController extends Controller
{
    public function __construct(private readonly AllocationStock $allocation) {}

    public function index(): View
    {
        $this->authorize('viewAny', Vente::class);

        $user = Auth::user();

        $recherche = trim((string) request()->query('q'));
        $du = request()->query('du');
        $au = request()->query('au');

        $ventes = Vente::with(['boutique', 'client', 'vendeur', 'lignes', 'paiements'])
            ->when($user->effectiveBoutiqueId(), fn ($query) => $query->where('boutique_id', $user->effectiveBoutiqueId()))
            ->when($recherche !== '', fn ($query) => $query->where(fn ($q) => $q
                ->where('numero', 'like', "%{$recherche}%")
                ->orWhereHas('client', fn ($c) => $c->where('nom', 'like', "%{$recherche}%"))))
            ->when($du, fn ($query) => $query->whereDate('created_at', '>=', $du))
            ->when($au, fn ($query) => $query->whereDate('created_at', '<=', $au))
            ->latest()
            ->paginate(30)
            ->withQueryString();

        return view('ventes.index', compact('ventes', 'recherche', 'du', 'au'));
    }

    public function create(): View
    {
        $this->authorize('create', Vente::class);

        $user = Auth::user();

        $boutiques = $user->effectiveBoutiqueId()
            ? Boutique::whereKey($user->effectiveBoutiqueId())->get()
            : Boutique::orderBy('nom')->get();

        $clients = Client::orderBy('nom')->get();
        $produits = Produit::where('actif', true)->orderBy('nom')->get();

        return view('ventes.create', compact('boutiques', 'clients', 'produits'));
    }

    public function store(StoreVenteRequest $request): RedirectResponse
    {
        $vente = DB::transaction(function () use ($request) {
            $vente = Vente::create($request->safe()->only(['boutique_id', 'client_id', 'date_echeance']));

            $client = $request->validated('client_id') ? Client::find($request->validated('client_id')) : null;

            foreach ($request->validated('lignes') as $ligne) {
                $this->allocation->allouer(
                    $vente,
                    (int) $ligne['produit_id'],
                    (int) $ligne['quantite'],
                    (int) $request->validated('boutique_id'),
                    $client
                );
            }

            $vente->load('lignes');

            $montantPaye = $request->filled('montant_paye')
                ? (int) $request->validated('montant_paye')
                : $vente->montantTotal();

            if ($montantPaye > 0) {
                Paiement::create([
                    'vente_id' => $vente->id,
                    'mode' => $request->validated('mode_paiement'),
                    'montant' => $montantPaye,
                ]);
            }

            return $vente;
        });

        Journal::enregistrer('vente.creee', __('Vente :numero enregistrée (:montant FCFA).', [
            'numero' => $vente->numero,
            'montant' => number_format($vente->montantTotal(), 0, ',', ' '),
        ]), $vente->boutique_id);

        return redirect()->route('ventes.show', $vente)->with('status', __('Vente enregistrée avec succès.'));
    }

    public function show(Vente $vente): View
    {
        $this->authorize('view', $vente);

        $vente->load(['boutique', 'client', 'vendeur', 'lignes.produit', 'lignes.lot', 'paiements']);

        return view('ventes.show', compact('vente'));
    }

    /**
     * Facture client imprimable/téléchargeable (format compact, adapté à
     * une mini-imprimante de caisse) : en-tête boutique, produits achetés
     * avec numéro de lot pour la traçabilité, et statut du paiement
     * (comptant ou reste à payer).
     */
    public function facture(Vente $vente): View
    {
        $this->authorize('view', $vente);

        $vente->load(['boutique', 'client', 'vendeur', 'lignes.produit', 'lignes.lot', 'paiements']);

        return view('ventes.facture', compact('vente'));
    }
}
