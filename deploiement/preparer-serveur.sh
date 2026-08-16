#!/usr/bin/env bash
#
# Prépare un VPS Ubuntu 24.04 nu à héberger Gestion Stock.
#
# À lancer une seule fois, en root, sur un serveur qui vient d'être livré :
#
#     sudo ./deploiement/preparer-serveur.sh
#
# Le script est idempotent : le relancer après avoir modifié une valeur
# ci-dessous met la configuration à jour sans rien casser.
#
# Il ne déploie pas l'application — c'est le rôle de deployer.sh — et ne
# touche ni au DNS ni au certificat TLS, qui demandent que le domaine pointe
# déjà sur ce serveur. Les deux étapes restantes sont rappelées à la fin.

set -euo pipefail

# --------------------------------------------------------------- Réglages

# Domaine servi. Tant qu'il n'est pas acheté, laisser l'adresse IP du VPS :
# l'application sera accessible en HTTP et pourra être validée immédiatement.
DOMAINE="${DOMAINE:-$(hostname -I | awk '{print $1}')}"

# Emplacement du code sur le serveur. deployer.sh utilise le même.
RACINE="${RACINE:-/var/www/gestion-stock/app}"

# Compte système propriétaire du code. Il n'est pas www-data : nginx et
# PHP-FPM ne doivent pas pouvoir réécrire le code qu'ils exécutent.
UTILISATEUR="${UTILISATEUR:-gestionstock}"

# Base de données et compte MySQL applicatif.
BASE="${BASE:-gestion_stock}"
UTILISATEUR_BASE="${UTILISATEUR_BASE:-gestion_stock}"

# Version de PHP des dépôts Ubuntu 24.04. Le code exige 8.2 au minimum.
VERSION_PHP="${VERSION_PHP:-8.3}"

# Port SSH, pour la règle de pare-feu.
PORT_SSH="${PORT_SSH:-22}"

# ------------------------------------------------------------------ Utile

BLEU='\033[1;34m'; VERT='\033[1;32m'; JAUNE='\033[1;33m'; NEUTRE='\033[0m'
etape()  { printf "\n${BLEU}==> %s${NEUTRE}\n" "$1"; }
ok()     { printf "    ${VERT}✓${NEUTRE} %s\n" "$1"; }
alerte() { printf "    ${JAUNE}!${NEUTRE} %s\n" "$1"; }

if [[ $EUID -ne 0 ]]; then
    echo "Ce script doit être lancé en root : sudo $0" >&2
    exit 1
fi

RACINE_SCRIPT="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"

# ------------------------------------------------------------- 1. Paquets

etape "Installation des paquets"

export DEBIAN_FRONTEND=noninteractive
apt-get update -qq

apt-get install -y -qq \
    nginx \
    mysql-server \
    "php${VERSION_PHP}-fpm" \
    "php${VERSION_PHP}-mysql" \
    "php${VERSION_PHP}-mbstring" \
    "php${VERSION_PHP}-xml" \
    "php${VERSION_PHP}-curl" \
    "php${VERSION_PHP}-bcmath" \
    "php${VERSION_PHP}-intl" \
    "php${VERSION_PHP}-zip" \
    "php${VERSION_PHP}-gd" \
    "php${VERSION_PHP}-opcache" \
    composer git unzip curl \
    certbot python3-certbot-nginx \
    ufw fail2ban unattended-upgrades

ok "PHP ${VERSION_PHP}, nginx, MySQL, certbot installés"

# ------------------------------------------------- 2. Utilisateur système

etape "Compte système et arborescence"

if ! id "$UTILISATEUR" &>/dev/null; then
    adduser --system --group --home "$(dirname "$RACINE")" --shell /bin/bash "$UTILISATEUR"
    ok "Utilisateur ${UTILISATEUR} créé"
else
    ok "Utilisateur ${UTILISATEUR} déjà présent"
fi

# www-data doit pouvoir lire le code et écrire dans storage/ : on le place
# dans le groupe du propriétaire plutôt que d'ouvrir les permissions.
usermod -aG "$UTILISATEUR" www-data
mkdir -p "$RACINE"
chown -R "$UTILISATEUR:$UTILISATEUR" "$(dirname "$RACINE")"
ok "Racine ${RACINE} prête"

# ------------------------------------------------------- 3. Base MySQL

etape "Base de données"

if mysql -e "USE ${BASE}" 2>/dev/null; then
    ok "Base ${BASE} déjà existante — inchangée"
    MOT_DE_PASSE_BASE=""
else
    MOT_DE_PASSE_BASE="$(openssl rand -base64 24 | tr -d '/+=' | head -c 24)"

    mysql <<SQL
CREATE DATABASE ${BASE} CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER IF NOT EXISTS '${UTILISATEUR_BASE}'@'127.0.0.1' IDENTIFIED BY '${MOT_DE_PASSE_BASE}';
GRANT ALL PRIVILEGES ON ${BASE}.* TO '${UTILISATEUR_BASE}'@'127.0.0.1';
FLUSH PRIVILEGES;
SQL
    ok "Base ${BASE} et compte ${UTILISATEUR_BASE} créés"
fi

# Fichier d'identifiants pour sauvegarder.sh : le mot de passe n'a ainsi
# jamais à figurer sur une ligne de commande, où « ps aux » l'exposerait à
# tous les comptes du serveur.
if [[ -n "$MOT_DE_PASSE_BASE" ]]; then
    FICHIER_CNF="$(dirname "$RACINE")/.my.cnf"
    cat > "$FICHIER_CNF" <<CNF
[client]
user=${UTILISATEUR_BASE}
password=${MOT_DE_PASSE_BASE}
host=127.0.0.1
CNF
    chown "$UTILISATEUR:$UTILISATEUR" "$FICHIER_CNF"
    chmod 600 "$FICHIER_CNF"
    ok "Identifiants écrits dans ${FICHIER_CNF} (chmod 600)"
fi

# ------------------------------------------------------------- 4. PHP-FPM

etape "PHP-FPM et OPcache"

install -m 644 "${RACINE_SCRIPT}/opcache.ini" \
    "/etc/php/${VERSION_PHP}/fpm/conf.d/99-gestion-stock.ini"
systemctl restart "php${VERSION_PHP}-fpm"
ok "OPcache activé, PHP-FPM redémarré"

# --------------------------------------------------------------- 5. nginx

etape "nginx"

sed -e "s|__DOMAINE__|${DOMAINE}|g" \
    -e "s|__RACINE__|${RACINE}|g" \
    -e "s|__PHP_VERSION__|${VERSION_PHP}|g" \
    "${RACINE_SCRIPT}/nginx-gestion-stock.conf" \
    > /etc/nginx/sites-available/gestion-stock

# Tous les VPS ne fournissent pas d'IPv6. Là où la pile est absente, nginx
# refuse de démarrer sur « listen [::]:80 » — et c'est tout le déploiement qui
# se bloque sur un détail réseau. On retire la directive plutôt que d'exiger
# une IPv6.
if [[ ! -f /proc/net/if_inet6 ]]; then
    sed -i '/listen \[::\]:80;/d' /etc/nginx/sites-available/gestion-stock
    alerte "Pas d'IPv6 sur ce serveur : écoute limitée à l'IPv4"
fi

ln -sf /etc/nginx/sites-available/gestion-stock /etc/nginx/sites-enabled/gestion-stock

# Le site par défaut d'Ubuntu répondrait à la place du nôtre sur une requête
# sans nom d'hôte connu, et écoute lui aussi en IPv6.
rm -f /etc/nginx/sites-enabled/default

nginx -t
systemctl reload nginx
ok "Site nginx activé pour ${DOMAINE}"

# ---------------------------------------------------------- 6. Pare-feu

etape "Durcissement"

ufw allow "${PORT_SSH}/tcp" >/dev/null
ufw allow 80/tcp  >/dev/null
ufw allow 443/tcp >/dev/null
ufw --force enable >/dev/null
ok "Pare-feu actif : ${PORT_SSH}, 80, 443 uniquement"

# SSH : on refuse la connexion root et par mot de passe — mais seulement si
# une clé publique est déjà autorisée. Couper l'authentification par mot de
# passe sans clé en place reviendrait à se verrouiller dehors.
CLES_PRESENTES=0
for foyer in /root /home/*; do
    [[ -s "${foyer}/.ssh/authorized_keys" ]] && CLES_PRESENTES=1
done

if [[ $CLES_PRESENTES -eq 1 ]]; then
    install -d -m 755 /etc/ssh/sshd_config.d
    cat > /etc/ssh/sshd_config.d/99-durcissement.conf <<'SSHD'
PermitRootLogin no
PasswordAuthentication no
KbdInteractiveAuthentication no
SSHD
    if sshd -t; then
        systemctl reload ssh
        ok "SSH : connexion root et par mot de passe désactivées"
    else
        rm -f /etc/ssh/sshd_config.d/99-durcissement.conf
        alerte "Configuration SSH refusée par sshd -t — durcissement annulé"
    fi
else
    alerte "Aucune clé SSH autorisée trouvée : authentification par mot de passe"
    alerte "laissée active pour ne pas vous verrouiller dehors."
    alerte "Déposez votre clé (ssh-copy-id), puis relancez ce script."
fi

systemctl enable --now fail2ban >/dev/null
ok "fail2ban actif sur sshd"

# Correctifs de sécurité appliqués sans intervention : sur un serveur qui
# tourne des mois sans qu'on s'en occupe, c'est ce qui évite de rester sur une
# faille publiée.
cat > /etc/apt/apt.conf.d/20auto-upgrades <<'CONF'
APT::Periodic::Update-Package-Lists "1";
APT::Periodic::Unattended-Upgrade "1";
CONF
ok "Mises à jour de sécurité automatiques"

# ------------------------------------------------------------- 7. Bilan

etape "Serveur prêt"

cat <<RESUME

    Domaine servi     : ${DOMAINE}
    Racine du code    : ${RACINE}
    Utilisateur       : ${UTILISATEUR}
    Base de données   : ${BASE} (compte ${UTILISATEUR_BASE}@127.0.0.1)
    PHP               : ${VERSION_PHP}

RESUME

if [[ -n "$MOT_DE_PASSE_BASE" ]]; then
    printf "${JAUNE}    Mot de passe MySQL (affiché une seule fois) :${NEUTRE}\n"
    printf "    %s\n\n" "$MOT_DE_PASSE_BASE"
    echo "    Reportez-le dans DB_PASSWORD du fichier .env."
    echo
fi

cat <<'SUITE'
    Étapes suivantes, dans l'ordre :

    1. Déposer le code et le configurer
         sudo -u gestionstock git clone <dépôt> /var/www/gestion-stock
         cd /var/www/gestion-stock/app
         cp .env.production.example .env    # puis remplir les « À REMPLIR »
         php artisan key:generate

    2. Premier déploiement
         ./deploiement/deployer.sh

    3. Quand le domaine est acheté — et seulement quand l'enregistrement
       DNS A pointe déjà sur ce serveur :
         sudo certbot --nginx -d exemple.com -d www.exemple.com
       puis mettre APP_URL et SESSION_DOMAIN à jour dans .env,
       relancer DOMAINE=exemple.com ./deploiement/preparer-serveur.sh,
       et redéployer.

    4. Sauvegardes et alertes quotidiennes (voir docs/deploiement-production.md)

SUITE
