<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Bloque l'accès à l'application tenant dès que l'abonnement du patron a
 * expiré, en renvoyant vers la page d'abonnement où un nouveau code
 * d'activation (fourni par le superadmin) permet de le renouveler.
 */
class VerifierAbonnement
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = Auth::user();

        if ($user && ! $user->tenant->abonnementActif()) {
            return redirect()->route('abonnement.index');
        }

        return $next($request);
    }
}
