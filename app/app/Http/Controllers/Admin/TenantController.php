<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\BonCommande;
use App\Models\Depense;
use App\Models\JournalActivite;
use App\Models\LigneVente;
use App\Models\Tenant;
use App\Models\Vente;
use App\Models\VersementCaisse;
use App\Support\CentreAlertes;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\View\View;

class TenantController extends Controller
{
    public function index(Request $request): View
    {
        $recherche = trim((string) $request->query('q'));

        $tenants = Tenant::withCount(['boutiques', 'users'])
            ->when($recherche !== '', fn ($query) => $query->where(fn ($q) => $q
                ->where('nom', 'like', "%{$recherche}%")
                ->orWhere('email_contact', 'like', "%{$recherche}%")))
            ->orderBy('nom')
            ->get();

        return view('admin.tenants.index', compact('tenants', 'recherche'));
    }

    /**
     * Vue d'ensemble d'un client : au-delà de l'abonnement, un aperçu de
     * son activité réelle (ventes, stock, trésorerie) pour que le
     * superadmin puisse comprendre et diagnostiquer une situation sans
     * avoir à se connecter à sa place.
     */
    public function show(Tenant $tenant): View
    {
        $tenant->load(['boutiques', 'users', 'codesActivation' => fn ($query) => $query->latest()]);

        $boutiqueIds = $tenant->boutiques->pluck('id');
        $debutMois = Carbon::now()->startOfMonth();

        $lignesVendues = LigneVente::whereHas('vente', fn ($query) => $query->where('tenant_id', $tenant->id));

        $chiffreAffairesTotal = (clone $lignesVendues)->selectRaw('SUM(quantite * prix_unitaire) as total')->value('total') ?? 0;
        $chiffreAffairesDuMois = (clone $lignesVendues)
            ->whereHas('vente', fn ($query) => $query->where('created_at', '>=', $debutMois))
            ->selectRaw('SUM(quantite * prix_unitaire) as total')->value('total') ?? 0;
        $margeTotale = (clone $lignesVendues)
            ->join('produits', 'ligne_ventes.produit_id', '=', 'produits.id')
            ->selectRaw('SUM(ligne_ventes.quantite * (ligne_ventes.prix_unitaire - produits.prix_achat)) as marge')
            ->value('marge') ?? 0;

        $nombreVentes = Vente::where('tenant_id', $tenant->id)->count();

        $dettesClients = Vente::with('paiements', 'lignes')
            ->where('tenant_id', $tenant->id)
            ->whereNotNull('date_echeance')
            ->get()
            ->sum(fn (Vente $vente) => $vente->montantDu());

        $depensesDuMois = Depense::where('tenant_id', $tenant->id)->where('date', '>=', $debutMois)->sum('montant');
        $versementsDuMois = VersementCaisse::where('tenant_id', $tenant->id)->where('date', '>=', $debutMois)->sum('montant');
        $nombreBonsCommande = BonCommande::where('tenant_id', $tenant->id)->count();

        $nombreAlertes = $boutiqueIds->isEmpty() ? 0 : CentreAlertes::compter($boutiqueIds);

        $dernieresVentes = Vente::with(['boutique', 'client', 'vendeur', 'lignes'])
            ->where('tenant_id', $tenant->id)
            ->latest()
            ->limit(5)
            ->get();

        $dernieresActivites = JournalActivite::with(['user', 'boutique'])
            ->where('tenant_id', $tenant->id)
            ->latest()
            ->limit(8)
            ->get();

        return view('admin.tenants.show', compact(
            'tenant',
            'chiffreAffairesTotal',
            'chiffreAffairesDuMois',
            'margeTotale',
            'nombreVentes',
            'dettesClients',
            'depensesDuMois',
            'versementsDuMois',
            'nombreBonsCommande',
            'nombreAlertes',
            'dernieresVentes',
            'dernieresActivites',
        ));
    }

    /**
     * Journal d'activité complet du tenant, sans les restrictions
     * appliquées côté patron (boutique/utilisateur) : le superadmin voit
     * tout le flux, filtrable par type d'action.
     */
    public function journal(Tenant $tenant, Request $request): View
    {
        $action = $request->query('action');

        $activites = JournalActivite::with(['user', 'boutique'])
            ->where('tenant_id', $tenant->id)
            ->when($action, fn ($query) => $query->where('action', $action))
            ->latest()
            ->paginate(50)
            ->withQueryString();

        $actions = JournalActivite::where('tenant_id', $tenant->id)
            ->select('action')
            ->distinct()
            ->orderBy('action')
            ->pluck('action');

        return view('admin.tenants.journal', compact('tenant', 'activites', 'actions', 'action'));
    }

    public function ventes(Tenant $tenant): View
    {
        $ventes = Vente::with(['boutique', 'client', 'vendeur', 'lignes', 'paiements'])
            ->where('tenant_id', $tenant->id)
            ->latest()
            ->paginate(50);

        return view('admin.tenants.ventes', compact('tenant', 'ventes'));
    }
}
