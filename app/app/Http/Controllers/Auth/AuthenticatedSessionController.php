<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Support\Journal;
use App\Support\LimiteurConnexions;
use App\Support\SuiviConnexions;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class AuthenticatedSessionController extends Controller
{
    public function create(): View
    {
        return view('auth.login');
    }

    public function store(Request $request): RedirectResponse
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        LimiteurConnexions::verifier($credentials['email'], 'tenant');

        if (! Auth::attempt($credentials, $request->boolean('remember'))) {
            LimiteurConnexions::enregistrerEchec($credentials['email'], 'tenant');
            SuiviConnexions::enregistrer($credentials['email'], false, 'tenant');

            throw ValidationException::withMessages([
                'email' => 'Identifiants incorrects.',
            ]);
        }

        if (! Auth::user()->actif) {
            LimiteurConnexions::enregistrerEchec($credentials['email'], 'tenant');
            SuiviConnexions::enregistrer($credentials['email'], false, 'tenant', Auth::user());
            Auth::logout();

            throw ValidationException::withMessages([
                'email' => 'Ce compte a été désactivé.',
            ]);
        }

        if (! Auth::user()->tenant->actif) {
            LimiteurConnexions::enregistrerEchec($credentials['email'], 'tenant');
            SuiviConnexions::enregistrer($credentials['email'], false, 'tenant', Auth::user());
            Auth::logout();

            throw ValidationException::withMessages([
                'email' => __("Cet espace a été suspendu par l'administrateur de la plateforme."),
            ]);
        }

        LimiteurConnexions::reinitialiser($credentials['email'], 'tenant');
        $request->session()->regenerate();

        SuiviConnexions::enregistrer($credentials['email'], true, 'tenant', Auth::user());
        Journal::enregistrer('connexion', __('Connexion à la plateforme.'));

        return redirect()->intended(route('dashboard'));
    }

    public function destroy(Request $request): RedirectResponse
    {
        Auth::guard('web')->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect('/');
    }
}
