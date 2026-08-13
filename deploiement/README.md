# Fichiers de déploiement

Tout ce qui concerne le serveur, par opposition à `app/` qui contient
l'application. Le mode d'emploi complet est dans
[`../docs/deploiement-production.md`](../docs/deploiement-production.md) ;
ce fichier n'est qu'un aide-mémoire.

| Fichier | Quand |
|---|---|
| `preparer-serveur.sh` | Une fois, sur un VPS neuf. Et à chaque changement de domaine. |
| `deployer.sh` | À chaque mise en ligne d'une nouvelle version. |
| `sauvegarder.sh` | Chaque nuit, par cron. |
| `nginx-gestion-stock.conf` | Modèle de vhost, installé par `preparer-serveur.sh`. |
| `opcache.ini` | Réglages PHP-FPM, installés par `preparer-serveur.sh`. |

## Mise en service

```bash
# 1. Serveur neuf (Ubuntu 24.04), en root
git clone <dépôt> /var/www/gestion-stock
cd /var/www/gestion-stock
sudo ./deploiement/preparer-serveur.sh          # ou DOMAINE=exemple.com ...

# 2. Configurer l'application
cd app && cp .env.production.example .env       # remplir les « À REMPLIR »
php artisan key:generate

# 3. Déployer
cd .. && sudo -u gestionstock ./deploiement/deployer.sh
```

Puis, quand le domaine pointe sur le serveur :

```bash
sudo certbot --nginx -d exemple.com -d www.exemple.com
```

## Points auxquels se tenir

**Ne pas éditer les copies installées sur le serveur** (`/etc/nginx/...`,
`/etc/php/...`) : elles sont réécrites à chaque passage de
`preparer-serveur.sh`. Modifier les fichiers de ce répertoire, les versionner,
puis relancer le script.

**Ne pas déployer en root.** `deployer.sh` refuse de démarrer : le code ne doit
pas appartenir au compte qui l'exécute, faute de quoi une faille applicative
permettrait de le réécrire.

**Renseigner `COPIE_DISTANTE`** dans `sauvegarder.sh`. Tant qu'elle est vide,
les sauvegardes dorment sur le serveur qu'elles sont censées protéger.

**Tester une restauration** au moins une fois. La procédure est en fin de
`sauvegarder.sh` et dans le guide. Une sauvegarde jamais restaurée n'est pas
une sauvegarde vérifiée.

## Réglages

Chaque script regroupe ses variables en tête et accepte de les recevoir par
l'environnement, sans être modifié :

```bash
sudo DOMAINE=exemple.com ./deploiement/preparer-serveur.sh
RETENTION_JOURS=90 ./deploiement/sauvegarder.sh
BRANCHE=production ./deploiement/deployer.sh
```
