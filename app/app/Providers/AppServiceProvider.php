<?php

namespace App\Providers;

use App\Models\Boutique;
use App\Support\CentreAlertes;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        View::composer('layouts.app', function ($view): void {
            $user = Auth::user();

            $boutiqueId = $user?->effectiveBoutiqueId();

            $view->with('nombreAlertes', $user
                ? CentreAlertes::compter($boutiqueId ? collect([$boutiqueId]) : Boutique::pluck('id'))
                : 0);

            $view->with('boutiqueContexteId', $boutiqueId);

            $view->with('boutiquesPourSelecteur', $user?->isPatron()
                ? Boutique::orderBy('nom')->get()
                : collect());
        });
    }
}
