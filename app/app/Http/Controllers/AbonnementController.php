<?php

namespace App\Http\Controllers;

use App\Models\CodeActivation;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

/**
 * Page d'abonnement du tenant : état du plan en cours et formulaire pour
 * activer/renouveler via un code fourni par le superadmin (§ demande
 * abonnement). Consultable par tous les rôles (utile quand l'abonnement a
 * expiré et que la navigation normale est bloquée), mais seul le patron
 * peut y saisir un code.
 */
class AbonnementController extends Controller
{
    public function index(): View
    {
        $tenant = Auth::user()->tenant;

        return view('abonnement.index', [
            'tenant' => $tenant,
            'nombreBoutiques' => $tenant->boutiques()->count(),
        ]);
    }

    public function activer(Request $request): RedirectResponse
    {
        $user = Auth::user();
        abort_unless($user->isPatron(), 403);

        $validated = $request->validate([
            'code_activation' => ['required', 'string'],
        ]);

        $code = CodeActivation::where('code', trim($validated['code_activation']))->first();

        $reserveAUnAutreTenant = $code?->tenant_id !== null && $code->tenant_id !== $user->tenant_id;

        if (! $code || ! $code->estUtilisable() || $reserveAUnAutreTenant) {
            return back()->withErrors([
                'code_activation' => __("Ce code d'activation est invalide, déjà utilisé, ou réservé à un autre compte."),
            ]);
        }

        $code->activerPour($user->tenant, $user);

        return redirect()->route('abonnement.index')->with('status', __('Abonnement activé avec succès.'));
    }
}
