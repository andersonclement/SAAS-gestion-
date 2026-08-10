<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Symfony\Component\HttpFoundation\Response;

class SetLocale
{
    /**
     * Applique la langue choisie par l'utilisateur (stockée en session via
     * LocaleController) ; retombe sur la locale par défaut de l'application
     * si aucun choix n'a été fait ou si la valeur stockée est invalide.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $locale = $request->session()->get('locale');

        if (is_string($locale) && array_key_exists($locale, config('app.available_locales'))) {
            App::setLocale($locale);
        }

        return $next($request);
    }
}
