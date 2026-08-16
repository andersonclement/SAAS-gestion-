#!/usr/bin/env bash
#
# Déploie Gestion Stock : récupère le code, migre la base, reconstruit les
# caches et remet le site en ligne.
#
#     ./deploiement/deployer.sh
#
# À lancer sous le compte propriétaire du code (gestionstock), pas en root :
# le code ne doit pas appartenir à l'utilisateur qui l'exécute.
#
# Le site passe en maintenance le temps de la migration. Quoi qu'il arrive —
# migration en échec, cache impossible à écrire, interruption au clavier — il
# en ressort : la remise en ligne est posée en piège de sortie dès le départ.

set -euo pipefail

# --------------------------------------------------------------- Réglages

RACINE="${RACINE:-/var/www/gestion-stock/app}"
VERSION_PHP="${VERSION_PHP:-8.3}"
BRANCHE="${BRANCHE:-main}"

# ------------------------------------------------------------------ Utile

BLEU='\033[1;34m'; VERT='\033[1;32m'; JAUNE='\033[1;33m'; ROUGE='\033[1;31m'; NEUTRE='\033[0m'
etape()  { printf "\n${BLEU}==> %s${NEUTRE}\n" "$1"; }
ok()     { printf "    ${VERT}✓${NEUTRE} %s\n" "$1"; }
alerte() { printf "    ${JAUNE}!${NEUTRE} %s\n" "$1"; }

# Contrôles avant tout déplacement : un « cd » qui échoue produirait un
# message d'erreur du shell à la place du nôtre, et le garde-fou root ne
# s'exécuterait jamais.

if [[ $EUID -eq 0 ]]; then
    echo "Ne pas déployer en root : sudo -u gestionstock $0" >&2
    echo "Le code ne doit pas appartenir au compte qui l'exécute." >&2
    exit 1
fi

if [[ ! -d "$RACINE" ]]; then
    echo "Répertoire introuvable : ${RACINE}" >&2
    echo "Déposez d'abord le code, ou corrigez RACINE en tête de ce script." >&2
    exit 1
fi

cd "$RACINE"

if [[ ! -f .env ]]; then
    echo "Aucun fichier .env dans ${RACINE}." >&2
    echo "Copiez .env.production.example en .env, remplissez-le, puis :" >&2
    echo "  php artisan key:generate" >&2
    exit 1
fi

if [[ ! -d .git ]]; then
    echo "${RACINE} n'est pas un dépôt git — impossible de récupérer le code." >&2
    exit 1
fi

# ------------------------------------------------------- Sortie de secours

MAINTENANCE=0
remettre_en_ligne() {
    local code=$?
    if [[ $MAINTENANCE -eq 1 ]]; then
        php artisan up >/dev/null 2>&1 || true
        if [[ $code -ne 0 ]]; then
            printf "\n${ROUGE}Déploiement interrompu — site remis en ligne sur la version précédente.${NEUTRE}\n"
            printf "${ROUGE}Corrigez la cause avant de relancer.${NEUTRE}\n"
        fi
    fi
    exit $code
}
trap remettre_en_ligne EXIT

# ----------------------------------------------------------- 1. Le code

etape "Récupération du code"

git fetch --quiet origin "$BRANCHE"
ANCIEN="$(git rev-parse --short HEAD)"
git checkout --quiet "$BRANCHE"
git pull --quiet --ff-only origin "$BRANCHE"
NOUVEAU="$(git rev-parse --short HEAD)"

if [[ "$ANCIEN" == "$NOUVEAU" ]]; then
    ok "Déjà à jour (${NOUVEAU})"
else
    ok "${ANCIEN} → ${NOUVEAU}"
fi

# ---------------------------------------------------- 2. Les dépendances

etape "Dépendances"

composer install --no-dev --optimize-autoloader --no-interaction --quiet
ok "Paquets installés (sans les dépendances de développement)"

# Signale sans bloquer : une faille publiée mérite d'être vue, mais ne doit
# pas empêcher de livrer un correctif urgent.
if ! composer audit --no-dev --quiet 2>/dev/null; then
    alerte "composer audit signale une vulnérabilité — à traiter rapidement."
fi

# ------------------------------------------------------- 3. Maintenance

etape "Passage en maintenance"

php artisan down --render="errors::503" --retry=60 >/dev/null
MAINTENANCE=1
ok "Site en maintenance"

# ---------------------------------------------------------- 4. Migration

etape "Base de données"

php artisan migrate --force --no-interaction
ok "Migrations appliquées"

# ------------------------------------------------------------- 5. Caches

etape "Reconstruction des caches"

# L'ordre importe : vider avant de reconstruire, sinon un cache de
# configuration périmé sert de source aux suivants.
php artisan config:clear >/dev/null
php artisan config:cache >/dev/null
php artisan route:cache  >/dev/null
php artisan view:cache   >/dev/null
ok "Configuration, routes et vues mises en cache"

# ------------------------------------------------------- 6. Permissions

etape "Permissions"

# Le point le plus souvent oublié d'un déploiement manuel. Seuls storage/ et
# bootstrap/cache doivent être inscriptibles par le serveur web ; le reste est
# en lecture seule, y compris .env — qu'on referme sur son propriétaire.
#
# Aucun changement de groupe ici : les fichiers restent au groupe du compte
# propriétaire, et www-data y accède parce que preparer-serveur.sh l'a inscrit
# dans ce groupe. Un « chgrp www-data » échouerait d'ailleurs, le compte de
# déploiement n'étant pas membre de ce groupe.
#
# Le bit setgid (2 de 2775) fait hériter le groupe aux fichiers créés
# ensuite par PHP-FPM : sans lui, les logs et vues compilées naîtraient au
# groupe www-data et redeviendraient illisibles au prochain déploiement.
find storage bootstrap/cache -type d -exec chmod 2775 {} +
find storage bootstrap/cache -type f -exec chmod 664 {} +
chmod 600 .env
ok "storage/ et bootstrap/cache inscriptibles, .env fermé"

# ---------------------------------------------------------- 7. Rechargement

etape "Rechargement de PHP-FPM"

# OPcache est réglé sur validate_timestamps=0 : sans ce rechargement, PHP
# continuerait de servir le code de la version précédente.
if sudo -n systemctl reload "php${VERSION_PHP}-fpm" 2>/dev/null; then
    ok "PHP-FPM rechargé"
else
    alerte "Rechargement impossible sans sudo. Lancez :"
    alerte "  sudo systemctl reload php${VERSION_PHP}-fpm"
fi

# ------------------------------------------------------ 8. Remise en ligne

etape "Remise en ligne"

php artisan up >/dev/null
MAINTENANCE=0
ok "Site en ligne"

# ------------------------------------------------------------ 9. Contrôle

etape "Contrôle"

php artisan about --only=environment

printf "\n${VERT}Déploiement terminé (%s).${NEUTRE}\n" "$NOUVEAU"
printf "Vérifiez : curl -sI \$APP_URL/login | head -3\n\n"
