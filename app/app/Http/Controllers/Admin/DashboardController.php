<?php

namespace App\Http\Controllers\Admin;

use App\Enums\ImportanceNotification;
use App\Enums\StatutCodeActivation;
use App\Http\Controllers\Controller;
use App\Models\CodeActivation;
use App\Models\NotificationInterne;
use App\Models\Tenant;
use App\Models\TentativeConnexion;
use Illuminate\Support\Carbon;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function index(): View
    {
        $tenants = Tenant::all();

        $tenantsActifs = $tenants->filter(fn (Tenant $tenant) => $tenant->abonnementActif());
        $tenantsExpires = $tenants->filter(fn (Tenant $tenant) => $tenant->plan !== null && ! $tenant->abonnementActif());
        $tenantsExpirentBientot = $tenantsActifs->filter(fn (Tenant $tenant) => $tenant->joursAvantExpiration() <= 7);
        $tenantsSuspendus = $tenants->filter(fn (Tenant $tenant) => ! $tenant->actif);

        $revenuMensuelRecurrent = $tenantsActifs->sum(fn (Tenant $tenant) => $tenant->plan->prixMensuel());

        $codesEnAttente = CodeActivation::where('statut', StatutCodeActivation::EnAttente)->count();

        $depuis24h = Carbon::now()->subDay();
        $connexionsReussies24h = TentativeConnexion::where('reussie', true)->where('created_at', '>=', $depuis24h)->count();
        $connexionsEchouees24h = TentativeConnexion::where('reussie', false)->where('created_at', '>=', $depuis24h)->count();

        // Clients qui laissent s'accumuler des alertes critiques sans y
        // toucher. C'est le signal avancé du décrochage : il arrive bien avant
        // le non-renouvellement, et permet d'appeler avant de perdre le client.
        $clientsEnDifficulte = Tenant::query()
            ->withCount([
                'notifications as alertes_critiques_count' => fn ($query) => $query
                    ->whereNull('resolue_le')
                    ->where('importance', ImportanceNotification::Critique->value),
                'notifications as rappels_ignores_count' => fn ($query) => $query
                    ->whereNull('resolue_le')
                    ->where('nombre_rappels', '>=', 2),
            ])
            ->where('actif', true)
            // whereHas plutôt que HAVING : les compteurs sont des
            // sous-requêtes, sans GROUP BY, et SQLite comme MySQL refusent une
            // clause HAVING dans ce cas.
            ->whereHas('notifications', fn ($query) => $query
                ->whereNull('resolue_le')
                ->where('importance', ImportanceNotification::Critique->value))
            ->orderByDesc('rappels_ignores_count')
            ->orderByDesc('alertes_critiques_count')
            ->limit(8)
            ->get();

        $alertesOuvertes = NotificationInterne::whereNull('resolue_le')->count();

        $derniersTenants = Tenant::withCount('boutiques')->latest()->limit(8)->get();
        $derniersCodes = CodeActivation::with(['tenant', 'generePar'])->latest()->limit(8)->get();
        $dernieresConnexions = TentativeConnexion::with('tenant')->latest()->limit(8)->get();

        return view('admin.dashboard', [
            'nombreTenants' => $tenants->count(),
            'nombreTenantsActifs' => $tenantsActifs->count(),
            'nombreTenantsExpires' => $tenantsExpires->count(),
            'nombreTenantsExpirentBientot' => $tenantsExpirentBientot->count(),
            'nombreTenantsSuspendus' => $tenantsSuspendus->count(),
            'revenuMensuelRecurrent' => $revenuMensuelRecurrent,
            'codesEnAttente' => $codesEnAttente,
            'connexionsReussies24h' => $connexionsReussies24h,
            'connexionsEchouees24h' => $connexionsEchouees24h,
            'clientsEnDifficulte' => $clientsEnDifficulte,
            'alertesOuvertes' => $alertesOuvertes,
            'derniersTenants' => $derniersTenants,
            'derniersCodes' => $derniersCodes,
            'dernieresConnexions' => $dernieresConnexions,
            'aujourdhui' => Carbon::today(),
        ]);
    }
}
