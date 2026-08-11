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
    })
    ->withExceptions(function (Exceptions $exceptions) {
        //
    })->create();
