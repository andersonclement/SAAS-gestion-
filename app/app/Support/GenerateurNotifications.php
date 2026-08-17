<?php

namespace App\Support;

use App\Enums\TypeNotification;
use App\Enums\UserRole;
use App\Models\Boutique;
use App\Models\NotificationInterne;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Transforme l'état des alertes en notifications adressées à quelqu'un.
 *
 * Le principe tient en trois règles, et c'est leur combinaison qui fait la
 * différence entre un centre de notifications qu'on consulte et un déversoir
 * que plus personne n'ouvre :
 *
 * 1. **Une situation, une notification.** Tant qu'une rupture dure, elle
 *    n'est signalée qu'une fois. Pas de doublon quotidien.
 * 2. **Un rappel si ça traîne.** Une notification lue mais dont la situation
 *    persiste redevient non lue au bout d'un délai propre à son type — deux
 *    jours pour une rupture, quatorze pour un surstock.
 * 3. **Une clôture automatique.** Dès que la situation disparaît du calcul
 *    d'alertes, la notification se referme seule. Personne n'a à faire le
 *    ménage, et la pastille ne ment pas.
 *
 * S'exécute hors session HTTP : la portée multi-tenant automatique ne
 * s'applique pas, chaque requête est donc explicitement bornée au tenant traité.
 */
class GenerateurNotifications
{
    /**
     * @return array{creees: int, rappels: int, resolues: int}
     */
    public function pourTousLesTenants(?int $tenantId = null): array
    {
        $bilan = ['creees' => 0, 'rappels' => 0, 'resolues' => 0];

        Tenant::query()
            ->when($tenantId, fn ($query, $id) => $query->whereKey($id))
            ->where('actif', true)
            ->get()
            ->each(function (Tenant $tenant) use (&$bilan) {
                foreach ($this->pourUnTenant($tenant) as $cle => $valeur) {
                    $bilan[$cle] += $valeur;
                }
            });

        return $bilan;
    }

    /**
     * @return array{creees: int, rappels: int, resolues: int}
     */
    public function pourUnTenant(Tenant $tenant): array
    {
        $boutiques = Boutique::withoutGlobalScopes()->where('tenant_id', $tenant->id)->get();

        $utilisateurs = User::query()
            ->where('tenant_id', $tenant->id)
            ->where('actif', true)
            ->get();

        if ($utilisateurs->isEmpty()) {
            return ['creees' => 0, 'rappels' => 0, 'resolues' => 0];
        }

        $situations = $this->situations($tenant, $boutiques);

        $creees = 0;
        $rappels = 0;
        $clesVivantes = [];

        foreach ($situations as $situation) {
            foreach ($this->destinatairesDe($situation, $utilisateurs) as $destinataire) {
                $clesVivantes[] = $destinataire->id.'|'.$situation['cle'];

                $resultat = $this->enregistrer($tenant, $destinataire, $situation);
                $creees += $resultat === 'creee' ? 1 : 0;
                $rappels += $resultat === 'rappel' ? 1 : 0;
            }
        }

        return [
            'creees' => $creees,
            'rappels' => $rappels,
            'resolues' => $this->cloturerLesDisparues($tenant, $clesVivantes),
        ];
    }

    /**
     * Situations en cours, sous une forme indépendante de leur origine.
     *
     * @param  Collection<int, Boutique>  $boutiques
     * @return Collection<int, array<string, mixed>>
     */
    private function situations(Tenant $tenant, Collection $boutiques): Collection
    {
        $situations = collect();

        if ($boutiques->isNotEmpty()) {
            $alertes = CentreAlertes::pour($boutiques->pluck('id'));

            foreach ($alertes['ruptures'] as $entree) {
                $situations->push($this->situation(
                    TypeNotification::Rupture,
                    $entree['boutique'],
                    "produit:{$entree['produit']?->id}",
                    __(':produit est en rupture', ['produit' => $entree['produit']?->nom]),
                    __('Plus aucune unité en stock à :boutique. Toute vente de ce produit est impossible.', [
                        'boutique' => $entree['boutique']?->nom,
                    ]),
                    '/stock',
                ));
            }

            foreach ($alertes['stockFaible'] as $entree) {
                $situations->push($this->situation(
                    TypeNotification::StockFaible,
                    $entree['boutique'],
                    "produit:{$entree['produit']?->id}",
                    __(':produit atteint son seuil minimum', ['produit' => $entree['produit']?->nom]),
                    __('Il reste :quantite unité(s) à :boutique, pour un minimum fixé à :seuil.', [
                        'quantite' => $entree['quantite'],
                        'boutique' => $entree['boutique']?->nom,
                        'seuil' => $entree['produit']?->stock_min,
                    ]),
                    '/previsions',
                ));
            }

            foreach ($alertes['surstock'] as $entree) {
                $situations->push($this->situation(
                    TypeNotification::Surstock,
                    $entree['boutique'],
                    "produit:{$entree['produit']?->id}",
                    __(':produit dépasse son stock maximum', ['produit' => $entree['produit']?->nom]),
                    __(':quantite unité(s) à :boutique immobilisent de la trésorerie et vieillissent en rayon.', [
                        'quantite' => $entree['quantite'],
                        'boutique' => $entree['boutique']?->nom,
                    ]),
                    '/stock',
                ));
            }

            foreach ($alertes['perimes'] as $stock) {
                $situations->push($this->situation(
                    TypeNotification::Perime,
                    $stock->boutique,
                    "lot:{$stock->lot_id}",
                    __(':produit — lot périmé', ['produit' => $stock->produit?->nom]),
                    __('Le lot :lot (:quantite unité(s)) a dépassé sa date de péremption : il doit être retiré de la vente.', [
                        'lot' => $stock->lot?->numero_lot,
                        'quantite' => $stock->quantite,
                    ]),
                    '/stock',
                ));
            }

            foreach ($alertes['peremptionProche'] as $stock) {
                $situations->push($this->situation(
                    TypeNotification::PeremptionProche,
                    $stock->boutique,
                    "lot:{$stock->lot_id}",
                    __(':produit — péremption proche', ['produit' => $stock->produit?->nom]),
                    __('Le lot :lot (:quantite unité(s)) périme le :date.', [
                        'lot' => $stock->lot?->numero_lot,
                        'quantite' => $stock->quantite,
                        'date' => $stock->lot?->date_peremption?->format('d/m/Y'),
                    ]),
                    '/stock',
                ));
            }

            foreach ($alertes['creancesEnRetard'] as $vente) {
                $situations->push($this->situation(
                    TypeNotification::CreanceEnRetard,
                    $vente->boutique,
                    "vente:{$vente->id}",
                    __('Créance en retard — :client', ['client' => $vente->client?->nom ?? __('client de passage')]),
                    __('La vente :numero reste due de :montant FCFA, échéance dépassée depuis le :date.', [
                        'numero' => $vente->numero,
                        'montant' => number_format($vente->montantDu(), 0, ',', ' '),
                        'date' => $vente->date_echeance?->format('d/m/Y'),
                    ]),
                    '/ventes/'.$vente->id,
                ));
            }

            foreach ($alertes['ecartsSynchronisation'] as $ecart) {
                $situations->push($this->situation(
                    TypeNotification::EcartSynchronisation,
                    $ecart->boutique,
                    "ecart:{$ecart->id}",
                    __('Écart de stock à trancher — :produit', ['produit' => $ecart->produit?->nom]),
                    __('La vente :numero, encaissée hors réseau, n\'a pas pu prélever :manquant unité(s) : le stock réel est inférieur au stock théorique.', [
                        'numero' => $ecart->vente?->numero,
                        'manquant' => $ecart->quantite_manquante,
                    ]),
                    '/alertes',
                ));
            }
        }

        // Portée tenant : ne dépend d'aucune boutique.
        $jours = $tenant->joursAvantExpiration();

        if ($jours !== null && $jours >= 0 && $jours <= 7) {
            $situations->push($this->situation(
                TypeNotification::AbonnementExpireBientot,
                null,
                'abonnement:'.$tenant->abonnement_expire_le?->toDateString(),
                __('Votre abonnement expire dans :jours jour(s)', ['jours' => $jours]),
                __("Passée cette date, l'accès à l'application sera suspendu jusqu'au renouvellement."),
                '/abonnement',
            ));
        }

        return $situations;
    }

    /**
     * @return array<string, mixed>
     */
    private function situation(
        TypeNotification $type,
        ?Boutique $boutique,
        string $suffixeCle,
        string $titre,
        string $message,
        ?string $lien,
    ): array {
        return [
            'type' => $type,
            'boutique' => $boutique,
            // La clé décrit la situation, pas l'instant : c'est elle qui permet
            // de reconnaître demain l'alerte d'aujourd'hui.
            'cle' => $type->value.':'.($boutique?->id ?? 'tenant').':'.$suffixeCle,
            'titre' => $titre,
            'message' => $message,
            'lien' => $lien,
        ];
    }

    /**
     * @param  array<string, mixed>  $situation
     * @param  Collection<int, User>  $utilisateurs
     * @return Collection<int, User>
     */
    private function destinatairesDe(array $situation, Collection $utilisateurs): Collection
    {
        /** @var TypeNotification $type */
        $type = $situation['type'];
        $roles = collect($type->destinataires())->map(fn (UserRole $role) => $role->value);
        $boutiqueId = $situation['boutique']?->id;

        return $utilisateurs->filter(function (User $utilisateur) use ($roles, $boutiqueId) {
            if (! $roles->contains($utilisateur->role->value)) {
                return false;
            }

            // Un utilisateur rattaché à une boutique ne reçoit que ce qui la
            // concerne ; le patron et le comptable, sans rattachement, voient
            // l'ensemble du tenant.
            return $utilisateur->boutique_id === null || $utilisateur->boutique_id === $boutiqueId;
        })->values();
    }

    /**
     * @param  array<string, mixed>  $situation
     * @return 'creee'|'rappel'|'inchangee'
     */
    private function enregistrer(Tenant $tenant, User $destinataire, array $situation): string
    {
        /** @var TypeNotification $type */
        $type = $situation['type'];

        $existante = NotificationInterne::withoutGlobalScopes()
            ->where('user_id', $destinataire->id)
            ->where('cle', $situation['cle'])
            ->first();

        if (! $existante) {
            NotificationInterne::withoutGlobalScopes()->create([
                'tenant_id' => $tenant->id,
                'user_id' => $destinataire->id,
                'boutique_id' => $situation['boutique']?->id,
                'type' => $type,
                'importance' => $type->importance(),
                'cle' => $situation['cle'],
                'titre' => $situation['titre'],
                'message' => $situation['message'],
                'lien' => $situation['lien'],
            ]);

            return 'creee';
        }

        // La situation était close et revient : c'est un fait nouveau.
        if ($existante->resolue_le !== null) {
            $existante->forceFill([
                'resolue_le' => null,
                'lu_le' => null,
                'message' => $situation['message'],
                'titre' => $situation['titre'],
            ])->save();

            return 'creee';
        }

        if (! $existante->estLue()) {
            // Déjà en attente de traitement : inutile d'en rajouter.
            return 'inchangee';
        }

        $depuis = $existante->rappele_le ?? $existante->lu_le ?? $existante->updated_at;

        if ($depuis->diffInDays(Carbon::now()) < $type->joursAvantRappel()) {
            return 'inchangee';
        }

        // Lue, mais toujours pas réglée : on la remet devant les yeux.
        $existante->forceFill([
            'lu_le' => null,
            'rappele_le' => now(),
            'nombre_rappels' => $existante->nombre_rappels + 1,
            'message' => $situation['message'],
        ])->save();

        return 'rappel';
    }

    /**
     * Referme les notifications dont la situation n'est plus constatée.
     *
     * @param  array<int, string>  $clesVivantes  Couples « utilisateur|clé » encore d'actualité.
     */
    private function cloturerLesDisparues(Tenant $tenant, array $clesVivantes): int
    {
        $vivantes = collect($clesVivantes)->flip();

        return NotificationInterne::withoutGlobalScopes()
            ->where('tenant_id', $tenant->id)
            ->whereNull('resolue_le')
            ->get()
            ->reject(fn (NotificationInterne $notification) => $vivantes->has($notification->user_id.'|'.$notification->cle))
            ->each(fn (NotificationInterne $notification) => $notification->forceFill(['resolue_le' => now()])->save())
            ->count();
    }
}
