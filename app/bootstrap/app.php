<?php

use App\Http\Middleware\EnTetesSecurite;
use App\Http\Middleware\SetLocale;
use App\Http\Middleware\VerifierAbonnement;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->web(append: [
            SetLocale::class,
            EnTetesSecurite::class,
        ]);

        $middleware->alias([
            'abonnement.actif' => VerifierAbonnement::class,
        ]);

        $middleware->redirectGuestsTo(fn (Request $request) => $request->is('admin', 'admin/*') ? route('admin.login') : route('login'));

        // Les URL absolues (dont le lien de réinitialisation de mot de passe
        // envoyé par e-mail) sont construites à partir de l'en-tête Host de
        // la requête. Sans liste blanche, un attaquant peut falsifier cet
        // en-tête pour faire pointer le lien vers son propre domaine et
        // récupérer le jeton de la victime. On n'accepte donc que le domaine
        // déclaré dans APP_URL, et ses sous-domaines.
        $middleware->trustHosts(at: fn () => [parse_url(config('app.url'), PHP_URL_HOST)]);

        // Derrière un reverse proxy ou un CDN, l'IP vue par l'application est
        // celle du proxy : sans cette configuration, le verrouillage
        // anti-force-brute regrouperait tous les utilisateurs sous une même
        // clé et le journal des connexions enregistrerait une IP inutile.
        // C'est aussi ce qui permet de détecter le HTTPS terminé par le proxy
        // (et donc d'émettre l'en-tête HSTS).
        $middleware->trustProxies(
            at: env('TRUSTED_PROXIES') === '*' ? '*' : array_filter(explode(',', (string) env('TRUSTED_PROXIES'))),
            headers: Request::HEADER_X_FORWARDED_FOR
                | Request::HEADER_X_FORWARDED_HOST
                | Request::HEADER_X_FORWARDED_PORT
                | Request::HEADER_X_FORWARDED_PROTO,
        );
    })
    ->withExceptions(function (Exceptions $exceptions) {
        //
    })->create();
