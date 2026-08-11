<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Tenant;
use Illuminate\Http\Request;
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

    public function show(Tenant $tenant): View
    {
        $tenant->load(['boutiques', 'users', 'codesActivation' => fn ($query) => $query->latest()]);

        return view('admin.tenants.show', compact('tenant'));
    }
}
