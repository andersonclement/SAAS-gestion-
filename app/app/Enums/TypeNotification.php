<?php

namespace App\Enums;

/**
 * Situations qui déclenchent une notification interne.
 *
 * Chaque type porte son audience et son degré d'urgence : c'est ce qui évite
 * qu'un gérant reçoive des alertes d'abonnement, ou qu'un comptable soit noyé
 * sous les péremptions de rayon.
 */
enum TypeNotification: string
{
    case Rupture = 'rupture';
    case StockFaible = 'stock_faible';
    case Surstock = 'surstock';
    case PeremptionProche = 'peremption_proche';
    case Perime = 'perime';
    case CreanceEnRetard = 'creance_en_retard';
    case EcartSynchronisation = 'ecart_synchronisation';
    case AbonnementExpireBientot = 'abonnement_expire_bientot';

    public function label(): string
    {
        return match ($this) {
            self::Rupture => __('Rupture de stock'),
            self::StockFaible => __('Stock au minimum'),
            self::Surstock => __('Surstock'),
            self::PeremptionProche => __('Péremption proche'),
            self::Perime => __('Produit périmé'),
            self::CreanceEnRetard => __('Créance en retard'),
            self::EcartSynchronisation => __('Écart de stock à traiter'),
            self::AbonnementExpireBientot => __('Abonnement bientôt expiré'),
        };
    }

    public function importance(): ImportanceNotification
    {
        return match ($this) {
            self::Rupture,
            self::Perime,
            self::EcartSynchronisation,
            self::AbonnementExpireBientot => ImportanceNotification::Critique,

            self::StockFaible,
            self::PeremptionProche,
            self::CreanceEnRetard => ImportanceNotification::Avertissement,

            self::Surstock => ImportanceNotification::Information,
        };
    }

    /**
     * Rôles destinataires.
     *
     * @return array<int, UserRole>
     */
    public function destinataires(): array
    {
        return match ($this) {
            // L'argent dû concerne aussi celui qui tient les comptes.
            self::CreanceEnRetard => [UserRole::Patron, UserRole::Gerant, UserRole::Comptable],

            // La vie du compte SaaS ne regarde que celui qui le paie.
            self::AbonnementExpireBientot => [UserRole::Patron],

            default => [UserRole::Patron, UserRole::Gerant],
        };
    }

    /**
     * Délai avant de rappeler une situation déjà signalée mais toujours non
     * résolue. Une rupture se rappelle vite ; un surstock peut attendre.
     */
    public function joursAvantRappel(): int
    {
        return match ($this) {
            self::Rupture, self::Perime, self::EcartSynchronisation => 2,
            self::AbonnementExpireBientot => 1,
            self::StockFaible, self::PeremptionProche, self::CreanceEnRetard => 3,
            self::Surstock => 14,
        };
    }
}
