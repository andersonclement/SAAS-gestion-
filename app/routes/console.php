<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Récapitulatif des alertes de stock, envoyé en début de matinée pour que le
// responsable l'ait en main avant l'ouverture des boutiques.
// Nécessite le cron système : * * * * * php artisan schedule:run
Schedule::command('alertes:envoyer')
    ->dailyAt('06:30')
    ->withoutOverlapping()
    ->onOneServer();

// Centre de notifications : rafraîchi plusieurs fois par jour. Une rupture
// constatée le matin ne doit pas attendre le lendemain pour être signalée.
// La déduplication par situation rend ces passages sans effet tant que rien
// ne change — pas de risque de saturer la pastille.
Schedule::command('notifications:generer')
    ->everyThreeHours()
    ->withoutOverlapping()
    ->onOneServer();
