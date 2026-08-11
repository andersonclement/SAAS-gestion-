<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Support\Journal;
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

        if (! Auth::attempt($credentials, $request->boolean('remember'))) {
            throw ValidationException::withMessages([
                'email' => 'Identifiants incorrects.',
            ]);
        }

        if (! Auth::user()->actif) {
            Auth::logout();

            throw ValidationException::withMessages([
                'email' => 'Ce compte a été désactivé.',
            ]);
        }

        if (! Auth::user()->tenant->actif) {
            Auth::logout();

            throw ValidationException::withMessages([
                'email' => __("Cet espace a été suspendu par l'administrateur de la plateforme."),
            ]);
        }

        $request->session()->regenerate();

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
