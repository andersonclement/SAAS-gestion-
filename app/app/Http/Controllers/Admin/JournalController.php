<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\JournalActivite;
use App\Models\Tenant;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Vue plateforme de l'activité : au-delà du journal par client (voir
 * TenantController::journal), une supervision transversale pour repérer
 * une anomalie sans savoir à l'avance de quel client elle vient.
 */
class JournalController extends Controller
{
    public function index(Request $request): View
    {
        $action = $request->query('action');
        $tenantId = $request->query('tenant_id');

        $activites = JournalActivite::with(['user', 'boutique', 'tenant'])
            ->when($action, fn ($query) => $query->where('action', $action))
            ->when($tenantId, fn ($query) => $query->where('tenant_id', $tenantId))
            ->latest()
            ->paginate(50)
            ->withQueryString();

        $actions = JournalActivite::select('action')->distinct()->orderBy('action')->pluck('action');
        $tenants = Tenant::orderBy('nom')->get();

        return view('admin.journal.index', compact('activites', 'actions', 'action', 'tenants', 'tenantId'));
    }
}
