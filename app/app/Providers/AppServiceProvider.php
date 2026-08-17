<?php

namespace App\Providers;

use App\Models\Boutique;
use App\Models\User;
use App\Support\AccesPrivilegie;
use App\Support\CentreAlertes;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\URL;
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
        // Hors développement, toutes les URL générées (liens, formulaires,
        // lien de réinitialisation de mot de passe) doivent être en HTTPS :
        // l'application transporte des identifiants et des données
        // financières, et tourne généralement derrière un reverse proxy qui
        // termine le TLS — sans cela Laravel générerait des URL en http://.
        if (! $this->app->environment('local', 'testing')) {
            URL::forceScheme('https');
        }

        $this->personnaliserEmailReinitialisation();

        // Les prévisions exposent les volumes d'achat et le rythme de vente de
        // la boutique : elles s'adressent à qui commande ou pilote, pas au
        // vendeur au comptoir.
        Gate::define('prevoir', fn (User $user) => $user->isPatron() || $user->isGerant() || $user->isComptable());

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

            // Code d'accès éventuellement ouvert : la bannière et le menu s'y
            // adaptent, et le gérant sait sous quelle délégation il travaille.
            $view->with('accesPrivilegie', $user ? AccesPrivilegie::actif() : null);

            $view->with('abonnementActif', $user === null || $user->tenant->abonnementActif());

            $joursAvantExpiration = $user?->tenant?->joursAvantExpiration();
            $view->with('abonnementExpireBientot', $joursAvantExpiration !== null && $joursAvantExpiration >= 0 && $joursAvantExpiration <= 7);
        });
    }

    /**
     * L'e-mail de réinitialisation par défaut de Laravel est en anglais :
     * on le réécrit en français, langue principale de la plateforme.
     */
    private function personnaliserEmailReinitialisation(): void
    {
        ResetPassword::toMailUsing(function (object $notifiable, string $token) {
            $lien = url(route('password.reset', [
                'token' => $token,
                'email' => $notifiable->getEmailForPasswordReset(),
            ], false));

            $minutes = config('auth.passwords.users.expire');

            return (new MailMessage)
                ->subject(__('Réinitialisation de votre mot de passe'))
                ->greeting(__('Bonjour,'))
                ->line(__('Vous recevez cet e-mail car une réinitialisation de mot de passe a été demandée pour votre compte.'))
                ->action(__('Réinitialiser mon mot de passe'), url($lien))
                ->line(__('Ce lien expirera dans :minutes minutes.', ['minutes' => $minutes]))
                ->line(__("Si vous n'êtes pas à l'origine de cette demande, vous pouvez ignorer cet e-mail."))
                ->salutation(__('L\'équipe :app', ['app' => config('app.name')]));
        });
    }
}
