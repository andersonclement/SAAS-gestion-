<?php

namespace App\Http\Controllers\Admin;

use App\Enums\StatutCodeActivation;
use App\Http\Controllers\Controller;
use App\Models\CodeActivation;
use App\Models\Tenant;
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

        $revenuMensuelRecurrent = $tenantsActifs->sum(fn (Tenant $tenant) => $tenant->plan->prixMensuel());

        $codesEnAttente = CodeActivation::where('statut', StatutCodeActivation::EnAttente)->count();

        $derniersTenants = Tenant::withCount('boutiques')->latest()->limit(8)->get();
        $derniersCodes = CodeActivation::with(['tenant', 'generePar'])->latest()->limit(8)->get();

        return view('admin.dashboard', [
            'nombreTenants' => $tenants->count(),
            'nombreTenantsActifs' => $tenantsActifs->count(),
            'nombreTenantsExpires' => $tenantsExpires->count(),
            'nombreTenantsExpirentBientot' => $tenantsExpirentBientot->count(),
            'revenuMensuelRecurrent' => $revenuMensuelRecurrent,
            'codesEnAttente' => $codesEnAttente,
            'derniersTenants' => $derniersTenants,
            'derniersCodes' => $derniersCodes,
            'aujourdhui' => Carbon::today(),
        ]);
    }
}
