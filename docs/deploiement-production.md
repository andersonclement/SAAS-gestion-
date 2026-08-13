# Guide de mise en production

Ce document décrit la mise en ligne de l'application et les opérations
récurrentes à mettre en place. Il complète `app/.env.production.example`.

## 1. Prérequis serveur

| Composant | Version minimale | Remarque |
|---|---|---|
| PHP | 8.2 | extensions : `pdo_mysql`, `mbstring`, `openssl`, `bcmath`, `intl` |
| MySQL | 8.0.3+ | ou MariaDB 10.5.2+ |
| Serveur web | Nginx ou Apache | racine web sur `app/public`, **jamais** sur `app/` |
| Certificat TLS | — | obligatoire : l'application force le HTTPS hors développement |

> **SQLite est exclu en production.** Il ne fournit pas de verrous de ligne,
> or la protection contre la survente (`lockForUpdate` dans
> `VenteController::allouerStock`) en dépend : deux caisses vendant
> simultanément le même produit pourraient sinon rendre le stock négatif.

> **PostgreSQL n'est pas pris en charge.** Le calcul de marge repose sur
> `CAST(... AS SIGNED)`, syntaxe propre à MySQL et MariaDB (voir
> `App\Support\CalculMarge`). Les versions antérieures à MySQL 8.0.3 et
> MariaDB 10.5.2 fonctionnent également, Laravel repliant le renommage de
> colonne sur `CHANGE`, mais elles ne sont pas testées.

La suite de tests tourne sur le même moteur que la production : voir
`environnement-local.md`.

## 2. Première installation

```bash
git clone <dépôt> && cd SAAS-gestion-/app

composer install --no-dev --optimize-autoloader

cp .env.production.example .env
# Remplir les valeurs « À REMPLIR » (base de données, SMTP, domaine)
php artisan key:generate

php artisan migrate --force
```

### Brancher le nom de domaine

Trois réglages doivent concorder, sans quoi l'application se protège en
refusant les requêtes (erreur 400) :

1. **`APP_URL`** doit contenir le domaine réel, en `https` :
   `APP_URL=https://exemple.com`.
   L'application n'accepte que ce domaine et ses sous-domaines dans
   l'en-tête `Host`. C'est ce qui empêche un attaquant de falsifier cet
   en-tête pour détourner vers son propre site le lien de réinitialisation
   de mot de passe envoyé à vos clients.
2. **`TRUSTED_PROXIES`** doit être renseigné si l'application est derrière
   nginx, un load-balancer ou Cloudflare (voir les commentaires du fichier
   `.env.production.example`).
3. **Le certificat TLS** doit couvrir le domaine ; l'application force le
   HTTPS sur toutes les URL qu'elle génère.

Exemple de configuration nginx (reverse proxy + PHP-FPM) :

```nginx
server {
    listen 443 ssl http2;
    server_name exemple.com www.exemple.com;

    ssl_certificate     /etc/letsencrypt/live/exemple.com/fullchain.pem;
    ssl_certificate_key /etc/letsencrypt/live/exemple.com/privkey.pem;

    root /chemin/vers/app/public;   # jamais /chemin/vers/app
    index index.php;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ \.php$ {
        fastcgi_pass unix:/run/php/php8.2-fpm.sock;
        include fastcgi_params;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        # Transmet le protocole d'origine : sans cela l'application croit
        # être en HTTP et n'émet pas l'en-tête HSTS.
        fastcgi_param HTTP_X_FORWARDED_PROTO $scheme;
    }

    # Ne jamais servir les fichiers sensibles.
    location ~ /\.(env|git) { deny all; }
}

server {
    listen 80;
    server_name exemple.com www.exemple.com;
    return 301 https://$host$request_uri;
}
```

**Vérification après branchement :**

```bash
# Doit répondre 200 et des liens en https://exemple.com
curl -sI https://exemple.com/login | head -3

# Doit répondre 400 : un Host falsifié est rejeté
curl -sI -H "Host: attaquant.test" https://exemple.com/login | head -1

# Doit lister Strict-Transport-Security et Content-Security-Policy
curl -sI https://exemple.com/login | grep -iE "strict-transport|content-security"
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

### Vérifier les dépendances à chaque déploiement

```bash
composer audit
```

Cette commande signale les vulnérabilités connues des paquets installés.
Elle doit renvoyer *No security vulnerability advisories found* ; sinon,
mettre à jour le paquet concerné avant de déployer. À exécuter également
une fois par mois même sans déploiement : une faille peut être publiée
sur une version que vous utilisez déjà.

## 4. Tâches planifiées

Le planificateur Laravel doit tourner (utile pour les traitements
périodiques et la purge des tokens expirés) :

```cron
* * * * * cd /chemin/vers/app && php artisan schedule:run >> /dev/null 2>&1
```

Sans cette ligne, **le récapitulatif quotidien des alertes de stock ne part
pas** : c'est la seule fonctionnalité qui dépend aujourd'hui du planificateur.

### Envoi des e-mails

Le récapitulatif d'alertes (`alertes:envoyer`, planifié tous les jours à
06 h 30) part par le transport configuré dans `.env`. En production,
`MAIL_MAILER=log` ne suffit pas : il faut un vrai service SMTP.

```dotenv
MAIL_MAILER=smtp
MAIL_HOST=smtp.votre-fournisseur.com
MAIL_PORT=587
MAIL_SCHEME=tls
MAIL_USERNAME=...
MAIL_PASSWORD=...
MAIL_FROM_ADDRESS="alertes@votre-domaine.com"
MAIL_FROM_NAME="${APP_NAME}"
```

Faites pointer `MAIL_FROM_ADDRESS` sur une adresse de votre nom de domaine et
publiez les enregistrements SPF/DKIM fournis par votre service d'envoi, faute
de quoi les messages finiront en indésirables.

Pour tester après déploiement, sans attendre 06 h 30 :

```bash
php artisan alertes:envoyer --force
```

Chaque responsable peut couper la réception depuis **Équipe → Modifier** ;
seuls les patrons et les gérants sont destinataires.

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
