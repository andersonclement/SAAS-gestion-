<?php

namespace App\Console\Commands;

use App\Enums\UserRole;
use App\Mail\RecapitulatifAlertes;
use App\Models\Boutique;
use App\Models\Tenant;
use App\Models\User;
use App\Support\CentreAlertes;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;

/**
 * Envoie à chaque responsable le récapitulatif des alertes de son périmètre.
 *
 * Prévue pour tourner une fois par jour (voir routes/console.php). La commande
 * s'exécute hors session HTTP : la portée multi-tenant automatique ne
 * s'applique pas, chaque requête est donc explicitement bornée aux boutiques
 * du tenant traité.
 */
class EnvoyerAlertesStock extends Command
{
    protected $signature = 'alertes:envoyer
                            {--tenant= : Limiter l\'envoi à un identifiant de tenant}
                            {--force : Renvoyer même si un courriel est déjà parti aujourd\'hui}';

    protected $description = 'Envoie par e-mail le récapitulatif quotidien des alertes de stock';

    public function handle(): int
    {
        $tenants = Tenant::query()
            ->when($this->option('tenant'), fn ($query, $id) => $query->whereKey($id))
            ->where('actif', true)
            ->get()
            // Un client dont l'abonnement a expiré n'a plus accès à
            // l'application : lui écrire chaque matin n'aurait aucun sens.
            ->filter(fn (Tenant $tenant) => $tenant->abonnementActif());

        $envoyes = 0;

        foreach ($tenants as $tenant) {
            $envoyes += $this->traiterTenant($tenant);
        }

        $this->info(__(':nombre récapitulatif(s) envoyé(s).', ['nombre' => $envoyes]));

        return self::SUCCESS;
    }

    private function traiterTenant(Tenant $tenant): int
    {
        $boutiques = Boutique::withoutGlobalScopes()->where('tenant_id', $tenant->id)->get();

        if ($boutiques->isEmpty()) {
            return 0;
        }

        // Seuls les rôles qui décident d'un réapprovisionnement sont
        // destinataires ; un vendeur n'a pas la main sur les achats.
        $destinataires = User::query()
            ->where('tenant_id', $tenant->id)
            ->where('actif', true)
            ->where('alertes_email', true)
            ->whereIn('role', [UserRole::Patron, UserRole::Gerant])
            ->get();

        $envoyes = 0;

        foreach ($destinataires as $destinataire) {
            if (! $this->option('force') && $destinataire->alerte_envoyee_le?->isToday()) {
                continue;
            }

            $perimetre = $destinataire->boutique_id
                ? $boutiques->firstWhere('id', $destinataire->boutique_id)
                : null;

            // Un gérant rattaché à une boutique supprimée n'a plus de
            // périmètre : rien à lui envoyer.
            if ($destinataire->boutique_id && $perimetre === null) {
                continue;
            }

            $boutiqueIds = $perimetre ? collect([$perimetre->id]) : $boutiques->pluck('id');
            $alertes = CentreAlertes::pour($boutiqueIds);

            if (RecapitulatifAlertes::compter($alertes) === 0) {
                continue;
            }

            Mail::to($destinataire->email)->send(new RecapitulatifAlertes(
                $destinataire,
                $alertes,
                $perimetre?->nom ?? $tenant->nom,
            ));

            // Marqué après l'envoi : si l'expédition échoue, l'exécution
            // suivante retentera au lieu de sauter la journée.
            $destinataire->forceFill(['alerte_envoyee_le' => now()->toDateString()])->save();

            $envoyes++;
        }

        return $envoyes;
    }
}
