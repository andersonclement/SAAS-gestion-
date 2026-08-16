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

## 2. Préparer le serveur

Un script fait l'installation complète d'un VPS Ubuntu 24.04 nu :

```bash
git clone <dépôt> /var/www/gestion-stock
cd /var/www/gestion-stock
sudo DOMAINE=exemple.com ./deploiement/preparer-serveur.sh
```

Sans domaine encore acheté, omettre `DOMAINE=` : le script prend l'adresse IP
du serveur et l'application est joignable en HTTP, ce qui permet de valider
l'installation tout de suite.

Ce que le script met en place :

| | |
|---|---|
| Paquets | PHP 8.3 et ses extensions, nginx, MySQL, composer, certbot |
| Base | `gestion_stock` en `utf8mb4`, compte applicatif, mot de passe généré |
| Identifiants | `~/.my.cnf` en `chmod 600`, lu par le script de sauvegarde |
| Compte système | `gestionstock`, propriétaire du code ; `www-data` dans son groupe |
| nginx | vhost installé depuis `deploiement/nginx-gestion-stock.conf` |
| PHP-FPM | OPcache activé (voir plus bas) |
| Durcissement | pare-feu, SSH, fail2ban, mises à jour automatiques |

Il est **idempotent** : le relancer après avoir changé le domaine met la
configuration à jour sans rien détruire. Il ne touche ni au DNS ni au
certificat TLS, qui exigent que le domaine pointe déjà sur le serveur.

### Durcissement appliqué

- **Pare-feu** (`ufw`) : seuls 22, 80 et 443 sont ouverts.
- **SSH** : connexion root et authentification par mot de passe désactivées —
  mais **seulement si une clé publique est déjà autorisée**. Sans clé, le
  script laisse le mot de passe actif et vous avertit, plutôt que de vous
  verrouiller dehors. Déposez votre clé (`ssh-copy-id`) puis relancez-le.
- **fail2ban** sur `sshd`, contre le balayage de mots de passe.
- **Mises à jour de sécurité automatiques** : sur un serveur qui tourne des
  mois sans qu'on s'en occupe, c'est ce qui évite de rester sur une faille
  publiée.

### OPcache

PHP recompile par défaut chaque fichier à chaque requête. Le cache d'opcode
(`deploiement/opcache.ini`, posé par le script) change l'ordre de grandeur du
temps de réponse sur un petit VPS : c'est le réglage le plus rentable de la
pile, et il ne coûte que de la mémoire.

Il est réglé sur `validate_timestamps=0` — PHP ne va plus vérifier la date des
fichiers. **Conséquence : un déploiement n'a d'effet qu'après rechargement de
PHP-FPM**, ce que `deployer.sh` fait à chaque passage.

## 3. Première installation

Le serveur étant préparé, il reste à configurer l'application :

```bash
cd /var/www/gestion-stock/app

cp .env.production.example .env
# Remplir les « À REMPLIR » : DB_PASSWORD (affiché par preparer-serveur.sh),
# SMTP, APP_URL, SESSION_DOMAIN
php artisan key:generate

# Le premier déploiement fait le reste : dépendances, migrations, caches.
cd /var/www/gestion-stock
sudo -u gestionstock ./deploiement/deployer.sh
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
2. **`TRUSTED_PROXIES`** doit rester à `*` avec nginx sur la même machine.
   Contre-intuitif, mais nginx transmet à PHP `REMOTE_ADDR = l'IP du
   visiteur`, pas la sienne : `TRUSTED_PROXIES=127.0.0.1` ne correspondrait
   jamais, `X-Forwarded-Proto` serait ignoré et **l'en-tête HSTS ne serait pas
   émis**. `*` est sans risque ici, PHP-FPM n'écoutant que sur une socket unix
   joignable par le seul nginx. Ne mettre une liste d'adresses que si
   l'application est exposée derrière un service tiers (plages Cloudflare).
3. **Le certificat TLS** doit couvrir le domaine ; l'application force le
   HTTPS sur toutes les URL qu'elle génère.

La configuration nginx est versionnée dans
`deploiement/nginx-gestion-stock.conf` et installée par
`preparer-serveur.sh` — ne pas éditer la copie du serveur, mais ce fichier,
puis relancer le script.

Elle sert la racine sur `app/public` (jamais sur `app/`), refuse les fichiers
sensibles (`.env`, `.git`, `*.sql`, `*.log`), met en cache les ressources
statiques et transmet `X-Forwarded-Proto` — sans quoi l'application se croirait
en HTTP derrière le proxy TLS et n'émettrait pas l'en-tête HSTS.

Le certificat s'obtient **une fois que l'enregistrement DNS A pointe sur le
serveur** ; certbot ajoute lui-même le bloc HTTPS et la redirection :

```bash
sudo certbot --nginx -d exemple.com -d www.exemple.com
```

Puis mettre `APP_URL` et `SESSION_DOMAIN` à jour dans `.env`, relancer
`sudo DOMAINE=exemple.com ./deploiement/preparer-serveur.sh`, et redéployer.

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

## 4. Déploiements suivants

```bash
sudo -u gestionstock ./deploiement/deployer.sh
```

Le script enchaîne : récupération du code, dépendances, passage en
maintenance, migrations, reconstruction des caches, permissions, rechargement
de PHP-FPM, remise en ligne.

**Ne pas le lancer en root** : le code ne doit pas appartenir au compte qui
l'exécute, sinon une faille applicative permettrait de le réécrire.

Trois garde-fous qu'un déploiement à la main n'a pas :

- **La remise en ligne est posée en piège de sortie dès le départ.** Migration
  en échec, disque plein, interruption au clavier : le site ressort de
  maintenance quoi qu'il arrive, sur la version précédente qui fonctionnait.
- **Les permissions sont réappliquées à chaque passage** — l'étape la plus
  souvent oubliée. Seuls `storage/` et `bootstrap/cache` sont inscriptibles
  par le serveur web ; `.env` est refermé en `600`.
- **`composer audit` est lancé en amont** et signale les vulnérabilités
  connues sans bloquer : une faille publiée mérite d'être vue, mais ne doit
  pas empêcher de livrer un correctif urgent.

Exécuter aussi `composer audit` une fois par mois même sans déploiement : une
faille peut être publiée sur une version que vous utilisez déjà.

### Aucun worker de file d'attente n'est nécessaire

`QUEUE_CONNECTION=database` est configuré, mais **rien n'est mis en file dans
le code actuel** : les e-mails partent en synchrone. Inutile donc de faire
tourner `queue:work` ou de le superviser. Si des envois différés sont ajoutés
plus tard, il faudra un service systemd — pas avant.

## 5. Tâches planifiées

Le planificateur Laravel doit tourner (utile pour les traitements
périodiques et la purge des tokens expirés) :

À installer dans la crontab de `gestionstock` (`sudo -u gestionstock crontab -e`) :

```cron
* * * * * cd /var/www/gestion-stock/app && php artisan schedule:run >> /dev/null 2>&1
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
sudo -u gestionstock php artisan alertes:envoyer --force
```

Vérifier ensuite que le planificateur tourne réellement — une ligne de cron
est facile à croire installée :

```bash
sudo -u gestionstock crontab -l          # la ligne doit apparaître
grep CRON /var/log/syslog | tail -3      # elle doit s'exécuter chaque minute
```

Chaque responsable peut couper la réception depuis **Équipe → Modifier** ;
seuls les patrons et les gérants sont destinataires.

## 6. Sauvegardes

**À mettre en place avant la première vraie donnée client.** La base contient
l'inventaire, les ventes, les dettes clients et la trésorerie de chaque
boutique. Une perte est irrécupérable : il n'existe aucune autre source pour
ces données.

```cron
30 2 * * * /var/www/gestion-stock/deploiement/sauvegarder.sh \
           >> /var/log/gestion-stock-sauvegarde.log 2>&1
```

À installer dans la crontab de `gestionstock`, qui possède le `~/.my.cnf` écrit
par `preparer-serveur.sh`. Le mot de passe MySQL n'est **jamais** passé en
argument : il serait visible par `ps aux` depuis n'importe quel compte du
serveur.

Le script fait un dump cohérent sans verrouiller les tables — les ventes en
cours ne sont pas interrompues — puis :

- **il vérifie l'archive avant tout le reste** : taille non nulle, gzip
  lisible, marqueur de fin que `mysqldump` n'écrit que s'il est allé au bout ;
- **il n'écrit dans le dossier qu'après vérification.** Le dump se fait dans un
  fichier temporaire qui ne prend son nom définitif qu'une fois validé. Sans
  cela, un dump interrompu — disque plein, base injoignable, serveur redémarré
  — laisserait un fichier tronqué portant le nom du jour, qu'on prendrait plus
  tard pour une sauvegarde valable ;
- **il ne purge qu'ensuite.** Une sauvegarde ratée n'efface jamais
  l'historique : c'est le scénario qui transforme un incident en catastrophe.

Deux réglages à ne pas laisser par défaut :

| Variable | Défaut | À faire |
|---|---|---|
| `RETENTION_JOURS` | 30 | suffisant dans la plupart des cas |
| `COPIE_DISTANTE` | vide | **à renseigner** |

Tant que `COPIE_DISTANTE` est vide, les sauvegardes restent sur le serveur
qu'elles sont censées protéger : une panne disque, une compromission ou une
erreur de l'hébergeur emporte les deux d'un coup. Renseigner une destination
`rclone` (`distant:dossier`) ou `rsync` (`user@hote:/dossier`).

### Restaurer — à tester une fois

Une sauvegarde jamais restaurée n'est pas une sauvegarde vérifiée. Sur une
base jetable, sans toucher à la production :

```bash
mysql -e "CREATE DATABASE restauration_test"
gunzip < /var/sauvegardes/gestion-stock/gestion_stock_AAAA-MM-JJ.sql.gz \
  | mysql restauration_test
mysql restauration_test -e "SELECT COUNT(*) FROM ventes"
mysql -e "DROP DATABASE restauration_test"
```

Si le compte de la vraie base doit servir à la restauration, lui accorder
d'abord les droits sur la base de test.

## 7. Supervision

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

## 8. Points de vigilance connus

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
