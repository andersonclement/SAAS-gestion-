<?php

namespace App\Http\Controllers\Admin;

use App\Enums\Plan;
use App\Enums\StatutCodeActivation;
use App\Http\Controllers\Controller;
use App\Models\CodeActivation;
use App\Models\Tenant;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class CodeActivationController extends Controller
{
    public function index(Request $request): View
    {
        $statut = $request->query('statut');

        $codes = CodeActivation::with(['tenant', 'generePar', 'utiliseParUser'])
            ->when($statut, fn ($query) => $query->where('statut', $statut))
            ->latest()
            ->paginate(30)
            ->withQueryString();

        return view('admin.codes.index', compact('codes', 'statut'));
    }

    public function create(): View
    {
        $tenants = Tenant::orderBy('nom')->get();

        return view('admin.codes.create', compact('tenants'));
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'plan' => ['required', Rule::enum(Plan::class)],
            'duree_mois' => ['required', 'integer', 'min:1', 'max:24'],
            'tenant_id' => ['nullable', 'exists:tenants,id'],
            'notes' => ['nullable', 'string', 'max:255'],
        ]);

        $code = CodeActivation::create([
            'plan' => $validated['plan'],
            'duree_mois' => $validated['duree_mois'],
            'statut' => StatutCodeActivation::EnAttente,
            'tenant_id' => $validated['tenant_id'] ?? null,
            'genere_par_admin_id' => Auth::guard('admin')->id(),
            'notes' => $validated['notes'] ?? null,
        ]);

        return redirect()->route('admin.codes.index')
            ->with('status', __('Code généré : :code — communiquez-le au patron.', ['code' => $code->code]));
    }

    public function revoquer(CodeActivation $code): RedirectResponse
    {
        abort_unless($code->estUtilisable(), 422, __('Seul un code en attente peut être révoqué.'));

        $code->update(['statut' => StatutCodeActivation::Revoque]);

        return back()->with('status', __('Code révoqué.'));
    }
}
