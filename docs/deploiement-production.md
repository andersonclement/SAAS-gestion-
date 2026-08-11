# Guide de mise en production

Ce document décrit la mise en ligne de l'application et les opérations
récurrentes à mettre en place. Il complète `app/.env.production.example`.

## 1. Prérequis serveur

| Composant | Version minimale | Remarque |
|---|---|---|
| PHP | 8.2 | extensions : `pdo_mysql`, `mbstring`, `openssl`, `bcmath`, `intl` |
| MySQL | 8.0 | ou PostgreSQL 14+ |
| Serveur web | Nginx ou Apache | racine web sur `app/public`, **jamais** sur `app/` |
| Certificat TLS | — | obligatoire : l'application force le HTTPS hors développement |

> **SQLite est exclu en production.** Il ne fournit pas de verrous de ligne,
> or la protection contre la survente (`lockForUpdate` dans
> `VenteController::allouerStock`) en dépend : deux caisses vendant
> simultanément le même produit pourraient sinon rendre le stock négatif.

## 2. Première installation

```bash
git clone <dépôt> && cd SAAS-gestion-/app

composer install --no-dev --optimize-autoloader

cp .env.production.example .env
# Remplir les valeurs « À REMPLIR » (base de données, SMTP, domaine)
php artisan key:generate

php artisan migrate --force
```

### Créer le premier compte superadmin

Aucune inscription publique n'existe pour ce rôle (c'est voulu). Le premier
compte se crée en console, les suivants depuis l'interface
(**Administrateurs → Nouvel administrateur**) :

```bash
php artisan tinker
>>> App\Models\Admin::create([
...     'name' => 'Votre nom',
...     'email' => 'admin@exemple.com',
...     'password' => 'un-mot-de-passe-long-et-unique',
... ]);
```

> Ne lancez **jamais** `php artisan db:seed` en production : le seeder crée
> des comptes de démonstration dont le mot de passe est `password`.

### Mise en cache (à refaire à chaque déploiement)

```bash
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

## 3. Déploiements suivants

```bash
php artisan down --render="errors::503"
git pull
composer install --no-dev --optimize-autoloader
php artisan migrate --force
php artisan config:cache && php artisan route:cache && php artisan view:cache
php artisan up
```

## 4. Tâches planifiées

Le planificateur Laravel doit tourner (utile pour les traitements
périodiques et la purge des tokens expirés) :

```cron
* * * * * cd /chemin/vers/app && php artisan schedule:run >> /dev/null 2>&1
```

## 5. Sauvegardes

**À mettre en place avant la première vraie donnée client.** L'application
contient l'inventaire, les ventes, les dettes clients et la trésorerie de
chaque boutique : une perte est irrécupérable.

```cron
30 2 * * * mysqldump --single-transaction --quick \
  -u gestion_stock -p'MOT_DE_PASSE' gestion_stock \
  | gzip > /sauvegardes/gestion_stock_$(date +\%F).sql.gz
```

Recommandations :

- conserver au moins 30 jours d'historique ;
- copier les sauvegardes **hors du serveur applicatif** (un serveur perdu
  emporte sinon ses propres sauvegardes) ;
- **tester une restauration** au moins une fois : une sauvegarde jamais
  restaurée n'est pas une sauvegarde vérifiée.

## 6. Supervision

Sans supervision, une erreur en production passe inaperçue jusqu'à ce qu'un
client la signale.

- **Erreurs applicatives** : brancher Sentry (ou équivalent). Les logs
  `storage/logs/laravel-*.log` sont un minimum, mais personne ne les lit
  spontanément.
- **Disponibilité** : surveiller l'URL `/up` (endpoint de santé fourni par
  Laravel) depuis un service externe.
- **Trafic et connexions** : l'espace superadmin fournit déjà
  **Connexions** (tentatives réussies/échouées avec IP) et
  **Journal global**. Une hausse anormale des échecs de connexion mérite
  un coup d'œil.

## 7. Points de vigilance connus

- **Activation des abonnements manuelle.** Chaque code est généré et
  transmis à la main par le superadmin. C'est le fonctionnement voulu ;
  il devient un goulot d'étranglement au-delà de quelques dizaines de
  clients, où un paiement en ligne prendrait le relais.
- **Pas de vérification d'adresse e-mail** à l'inscription. L'accès étant
  conditionné à un code d'activation transmis hors application, le risque
  est limité, mais une adresse erronée empêche la réinitialisation du mot
  de passe.
- **Aucun test de charge** n'a été réalisé. Les volumes visés (quelques
  boutiques, quelques milliers de ventes par mois) sont très en deçà des
  limites de la pile, mais le chiffre reste à confirmer par la mesure.
