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

            $view->with('nombreAlertes', $user
                ? CentreAlertes::compter($user->boutique_id ? collect([$user->boutique_id]) : Boutique::pluck('id'))
                : 0);
        });
    }
}
