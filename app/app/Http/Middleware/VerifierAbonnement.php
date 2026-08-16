<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Bloque l'accès à l'application tenant dès que l'abonnement du patron a
 * expiré (renvoi vers la page d'abonnement, où un nouveau code d'activation
 * permet de le renouveler), ou dès que le superadmin a suspendu le tenant
 * (déconnexion immédiate : contrairement à un abonnement expiré, aucun code
 * ne peut lever une suspension — seul le superadmin le peut).
 */
class VerifierAbonnement
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = Auth::user();

        if ($user && ! $user->tenant->actif) {
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->route('login')->withErrors([
                'email' => __("Cet espace a été suspendu par l'administrateur de la plateforme."),
            ]);
        }

        if ($user && ! $user->tenant->abonnementActif()) {
            return redirect()->route('abonnement.index');
        }

        return $next($request);
    }
}
