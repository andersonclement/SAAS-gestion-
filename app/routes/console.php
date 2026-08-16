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
