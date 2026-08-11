<?php

namespace App\Http\Controllers\Admin;

use App\Enums\StatutCodeActivation;
use App\Http\Controllers\Controller;
use App\Models\CodeActivation;
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
            'derniersTenants' => $derniersTenants,
            'derniersCodes' => $derniersCodes,
            'dernieresConnexions' => $dernieresConnexions,
            'aujourdhui' => Carbon::today(),
        ]);
    }
}
