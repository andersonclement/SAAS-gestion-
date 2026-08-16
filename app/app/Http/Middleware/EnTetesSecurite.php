<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\View;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

/**
 * En-têtes de sécurité appliqués à toutes les réponses HTML.
 *
 * L'application manipule des données commerciales et financières : ces
 * en-têtes limitent le détournement de l'interface (clickjacking),
 * l'interprétation hasardeuse des types de contenu, et la fuite d'URL
 * internes vers des sites tiers.
 */
class EnTetesSecurite
{
    public function handle(Request $request, Closure $next): Response
    {
        // Un nonce par requête, partagé avec les vues : il autorise nos
        // propres blocs <script> tout en bloquant ceux qu'une injection
        // parviendrait à faire rendre dans la page.
        $nonce = Str::random(32);
        View::share('cspNonce', $nonce);

        $response = $next($request);

        $response->headers->set('X-Frame-Options', 'SAMEORIGIN');
        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('Referrer-Policy', 'same-origin');
        $response->headers->set('X-Permitted-Cross-Domain-Policies', 'none');
        $response->headers->set('Permissions-Policy', 'camera=(), microphone=(), geolocation=(), payment=()');

        $response->headers->set('Content-Security-Policy', implode('; ', [
            "default-src 'self'",
            // Aucun 'unsafe-inline' ici : c'est la directive qui neutralise
            // réellement les charges utiles XSS.
            "script-src 'self' 'nonce-{$nonce}'",
            // 'unsafe-inline' reste nécessaire pour les styles : la mise en
            // forme utilise des attributs style="…", auxquels un nonce ne
            // peut pas s'appliquer (limitation de la spécification CSP).
            "style-src 'self' 'unsafe-inline'",
            "img-src 'self' data:",
            // Le service worker de la caisse hors-ligne (§5) et le manifeste
            // d'installation. connect-src borne les appels fetch de
            // synchronisation à notre propre domaine.
            "worker-src 'self'",
            "manifest-src 'self'",
            "connect-src 'self'",
            "font-src 'self'",
            "object-src 'none'",
            "base-uri 'self'",
            "form-action 'self'",
            "frame-ancestors 'self'",
        ]));

        if ($request->secure()) {
            $response->headers->set('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
        }

        return $response;
    }
}
