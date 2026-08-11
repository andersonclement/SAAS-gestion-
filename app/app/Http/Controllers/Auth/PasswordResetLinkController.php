<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Password;
use Illuminate\View\View;

/**
 * Demande de réinitialisation : envoie par e-mail un lien signé et
 * temporaire (60 minutes, voir config/auth.php) permettant de choisir un
 * nouveau mot de passe sans passer par le superadmin.
 */
class PasswordResetLinkController extends Controller
{
    public function create(): View
    {
        return view('auth.mot-de-passe-oublie');
    }

    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'email' => ['required', 'email'],
        ]);

        $statut = Password::sendResetLink($request->only('email'));

        // On renvoie toujours le même message, y compris quand l'adresse est
        // inconnue : indiquer « cet e-mail n'existe pas » permettrait
        // d'énumérer les comptes de la plateforme.
        if ($statut === Password::RESET_THROTTLED) {
            return back()->withInput()->withErrors(['email' => __(Password::RESET_THROTTLED)]);
        }

        return back()->with('status', __('Si un compte existe pour cette adresse, un lien de réinitialisation vient de lui être envoyé.'));
    }
}
