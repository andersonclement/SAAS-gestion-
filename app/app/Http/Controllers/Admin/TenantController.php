<?php

namespace App\Http\Controllers\Admin;

use App\Enums\ImportanceNotification;
use App\Http\Controllers\Controller;
use App\Models\BonCommande;
use App\Models\Depense;
use App\Models\JournalActivite;
use App\Models\LigneVente;
use App\Models\Tenant;
use App\Models\TentativeConnexion;
use App\Models\Vente;
use App\Models\VersementCaisse;
use App\Support\CalculMarge;
use App\Support\CentreAlertes;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class TenantController extends Controller
{
    public function index(Request $request): View
    {
        $recherche = trim((string) $request->query('q'));
        $statut = $request->query('statut');
        $aujourdhui = Carbon::today()->toDateString();

        // Nombre d'alertes non traitées : c'est le meilleur signal avancé dont
        // dispose le support. Un client qui laisse s'accumuler des ruptures et
        // des créances en retard est un client qui décroche, bien avant que son
        // abonnement n'arrive à échéance.
        $tenants = Tenant::withCount([
            'boutiques',
            'users',
            'notifications as alertes_non_traitees_count' => fn ($query) => $query
                ->whereNull('resolue_le')
                ->whereNull('lu_le'),
            'notifications as alertes_critiques_count' => fn ($query) => $query
                ->whereNull('resolue_le')
                ->where('importance', ImportanceNotification::Critique->value),
        ])
            ->when($recherche !== '', fn ($query) => $query->where(fn ($q) => $q
                ->where('nom', 'like', "%{$recherche}%")
                ->orWhere('email_contact', 'like', "%{$recherche}%")))
            ->when($statut === 'suspendu', fn ($query) => $query->where('actif', false))
            ->when($statut === 'actif', fn ($query) => $query->where('actif', true)
                ->whereNotNull('abonnement_expire_le')->where('abonnement_expire_le', '>=', $aujourdhui))
            ->when($statut === 'expire', fn ($query) => $query->where(fn ($q) => $q
                ->whereNull('abonnement_expire_le')->orWhere('abonnement_expire_le', '<', $aujourdhui)))
            ->when($statut === 'expire_bientot', fn ($query) => $query
                ->whereNotNull('abonnement_expire_le')
                ->whereBetween('abonnement_expire_le', [$aujourdhui, Carbon::today()->addDays(7)->toDateString()]))
            ->orderBy('nom')
            ->get();

        return view('admin.tenants.index', compact('tenants', 'recherche', 'statut'));
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
            ->selectRaw(CalculMarge::expression())
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

        $dernieresConnexionsParUser = TentativeConnexion::where('tenant_id', $tenant->id)
            ->where('reussie', true)
            ->selectRaw('user_id, MAX(created_at) as derniere_connexion')
            ->groupBy('user_id')
            ->pluck('derniere_connexion', 'user_id');

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
            'dernieresConnexionsParUser',
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

    /**
     * Suspend ou réactive un tenant : contrairement à l'expiration
     * d'abonnement (que le patron peut lever lui-même avec un code), seul
     * le superadmin peut lever une suspension. Un tenant suspendu est
     * immédiatement déconnecté (VerifierAbonnement) et ne peut plus se
     * reconnecter tant qu'il n'est pas réactivé.
     */
    public function toggleActif(Tenant $tenant): RedirectResponse
    {
        $tenant->update(['actif' => ! $tenant->actif]);

        $message = $tenant->actif
            ? __('Client réactivé.')
            : __('Client suspendu.');

        return back()->with('status', $message);
    }

    /**
     * Notes internes du superadmin sur ce client. Elles ne sont jamais
     * exposées côté tenant : le patron ne doit pas les voir.
     */
    public function enregistrerNotes(Tenant $tenant, Request $request): RedirectResponse
    {
        $valide = $request->validate([
            'notes_internes' => ['nullable', 'string', 'max:5000'],
        ]);

        $tenant->update($valide);

        return back()->with('status', __('Notes enregistrées.'));
    }

    /**
     * Export du portefeuille clients pour le suivi commercial hors ligne
     * (relances d'abonnement, prévisionnel de revenus).
     */
    public function exporter(): StreamedResponse
    {
        // Nombre d'alertes non traitées : c'est le meilleur signal avancé dont
        // dispose le support. Un client qui laisse s'accumuler des ruptures et
        // des créances en retard est un client qui décroche, bien avant que son
        // abonnement n'arrive à échéance.
        $tenants = Tenant::withCount([
            'boutiques',
            'users',
            'notifications as alertes_non_traitees_count' => fn ($query) => $query
                ->whereNull('resolue_le')
                ->whereNull('lu_le'),
            'notifications as alertes_critiques_count' => fn ($query) => $query
                ->whereNull('resolue_le')
                ->where('importance', ImportanceNotification::Critique->value),
        ])->orderBy('nom')->get();

        return response()->streamDownload(function () use ($tenants) {
            $flux = fopen('php://output', 'w');
            fputcsv($flux, [
                __('Nom'), __('E-mail'), __('Téléphone'), __('Formule'),
                __('Prix mensuel'), __('Expiration'), __('Compte'),
                __('Boutiques'), __('Équipe'), __('Inscrit le'),
            ]);

            foreach ($tenants as $tenant) {
                fputcsv($flux, [
                    $tenant->nom,
                    $tenant->email_contact,
                    $tenant->telephone,
                    $tenant->plan?->label() ?? __('Aucun'),
                    $tenant->plan?->prixMensuel() ?? 0,
                    $tenant->abonnement_expire_le?->format('Y-m-d') ?? '',
                    $tenant->actif ? __('Actif') : __('Suspendu'),
                    $tenant->boutiques_count,
                    $tenant->users_count,
                    $tenant->created_at->format('Y-m-d'),
                ]);
            }

            fclose($flux);
        }, 'clients.csv', ['Content-Type' => 'text/csv']);
    }
}
