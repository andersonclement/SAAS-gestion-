<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreVenteRequest;
use App\Models\Boutique;
use App\Models\Client;
use App\Models\Paiement;
use App\Models\Produit;
use App\Models\Promotion;
use App\Models\StockBoutique;
use App\Models\Vente;
use App\Support\Journal;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class VenteController extends Controller
{
    public function index(): View
    {
        $this->authorize('viewAny', Vente::class);

        $user = Auth::user();

        $ventes = Vente::with(['boutique', 'client', 'vendeur', 'lignes'])
            ->when($user->effectiveBoutiqueId(), fn ($query) => $query->where('boutique_id', $user->effectiveBoutiqueId()))
            ->latest()
            ->get();

        return view('ventes.index', compact('ventes'));
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
                $this->allouerStock(
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

    /**
     * Alloue la quantité vendue sur les lots disponibles de la boutique en
     * épuisant en priorité ceux dont la péremption est la plus proche
     * (FEFO — first-expired, first-out), et décrémente le stock en
     * conséquence. Le prix appliqué tient compte de la meilleure
     * promotion active pour ce produit/client (§4.16).
     *
     * Les lignes de stock sont verrouillées (SELECT ... FOR UPDATE) et la
     * disponibilité est re-vérifiée à l'intérieur du verrou : la validation
     * de StoreVenteRequest a lieu avant la transaction, donc deux ventes
     * simultanées du même produit pourraient sinon passer toutes les deux
     * ce contrôle et rendre le stock négatif (survente).
     */
    private function allouerStock(Vente $vente, int $produitId, int $quantiteDemandee, int $boutiqueId, ?Client $client): void
    {
        $produit = Produit::findOrFail($produitId);
        $prixUnitaire = $this->prixApresPromotion($produit, $client);

        $stocks = StockBoutique::query()
            ->where('boutique_id', $boutiqueId)
            ->where('produit_id', $produitId)
            ->where('quantite', '>', 0)
            ->lockForUpdate()
            ->get()
            ->load('lot')
            ->sortBy(fn (StockBoutique $stock) => $stock->lot->date_peremption ?? '9999-12-31')
            ->values();

        if ($stocks->sum('quantite') < $quantiteDemandee) {
            throw ValidationException::withMessages([
                'lignes' => __('Stock insuffisant dans la boutique source (:disponible disponible).', [
                    'disponible' => $stocks->sum('quantite'),
                ]),
            ]);
        }

        $restant = $quantiteDemandee;

        foreach ($stocks as $stock) {
            if ($restant <= 0) {
                break;
            }

            $prelevement = min($restant, $stock->quantite);
            $stock->decrement('quantite', $prelevement);

            $vente->lignes()->create([
                'produit_id' => $produitId,
                'lot_id' => $stock->lot_id,
                'quantite' => $prelevement,
                'prix_unitaire' => $prixUnitaire,
            ]);

            $restant -= $prelevement;
        }
    }

    private function prixApresPromotion(Produit $produit, ?Client $client): int
    {
        $aujourdhui = Carbon::today();

        $meilleureRemise = Promotion::where('actif', true)
            ->where('date_debut', '<=', $aujourdhui)
            ->where('date_fin', '>=', $aujourdhui)
            ->get()
            ->filter(fn (Promotion $promotion) => $promotion->sApplique($produit, $client))
            ->map(fn (Promotion $promotion) => $promotion->remisePour($produit->prix_vente))
            ->max() ?? 0;

        return $produit->prix_vente - $meilleureRemise;
    }
}
