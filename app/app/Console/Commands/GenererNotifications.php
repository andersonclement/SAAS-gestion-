<?php

namespace App\Console\Commands;

use App\Support\GenerateurNotifications;
use Illuminate\Console\Command;

/**
 * Met à jour le centre de notifications de chaque client.
 *
 * Prévue plusieurs fois par jour : une rupture constatée le matin ne doit pas
 * attendre le lendemain pour être signalée. La déduplication par situation
 * rend ces passages répétés sans effet tant que rien ne change.
 */
class GenererNotifications extends Command
{
    protected $signature = 'notifications:generer
                            {--tenant= : Limiter la génération à un identifiant de tenant}';

    protected $description = 'Crée, rappelle et referme les notifications internes à partir des alertes';

    public function handle(GenerateurNotifications $generateur): int
    {
        $bilan = $generateur->pourTousLesTenants(
            $this->option('tenant') ? (int) $this->option('tenant') : null
        );

        $this->info(__(
            ':creees nouvelle(s), :rappels rappel(s), :resolues refermée(s).',
            $bilan
        ));

        return self::SUCCESS;
    }
}
