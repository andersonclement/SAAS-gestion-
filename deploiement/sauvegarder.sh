#!/usr/bin/env bash
#
# Sauvegarde la base de Gestion Stock.
#
#     ./deploiement/sauvegarder.sh
#
# Prévu pour tourner chaque nuit par cron, sous le compte propriétaire du
# code :
#
#     30 2 * * * /var/www/gestion-stock/deploiement/sauvegarder.sh >> \
#                /var/log/gestion-stock-sauvegarde.log 2>&1
#
# La base contient l'inventaire, les ventes, les dettes clients et la
# trésorerie de chaque boutique. Une perte est irrécupérable : il n'existe
# aucune autre source pour ces données.
#
# Le mot de passe MySQL n'est jamais passé en argument — il serait lisible par
# « ps aux » depuis n'importe quel compte du serveur. Il est lu dans le
# fichier ~/.my.cnf en chmod 600 que preparer-serveur.sh a écrit.

set -euo pipefail

# --------------------------------------------------------------- Réglages

BASE="${BASE:-gestion_stock}"
DESTINATION="${DESTINATION:-/var/sauvegardes/gestion-stock}"
RETENTION_JOURS="${RETENTION_JOURS:-30}"

# Copie hors du serveur applicatif. Un serveur perdu — panne disque,
# compromission, erreur de l'hébergeur — emporte sinon ses propres
# sauvegardes. Renseigner une destination rclone (« distant:dossier ») ou une
# cible rsync (« user@hote:/dossier »), et laisser vide pour ne rien copier.
COPIE_DISTANTE="${COPIE_DISTANTE:-}"

# ------------------------------------------------------------------ Utile

horodatage() { date '+%Y-%m-%d %H:%M:%S'; }
journal()    { printf '[%s] %s\n' "$(horodatage)" "$1"; }

ARCHIVE="${DESTINATION}/${BASE}_$(date +%F).sql.gz"

# On écrit d'abord à côté, et l'archive ne prend son nom définitif qu'une fois
# vérifiée. Sans cela, un dump interrompu — disque plein, base injoignable,
# serveur redémarré — laisserait dans le dossier un fichier tronqué portant le
# nom du jour, qu'on prendrait plus tard pour une sauvegarde valable.
PARTIEL="${ARCHIVE}.partiel"

mkdir -p "$DESTINATION"
chmod 700 "$DESTINATION"

nettoyer() {
    local code=$?
    if [[ -e "$PARTIEL" ]]; then
        rm -f "$PARTIEL"
        [[ $code -ne 0 ]] && journal "ÉCHEC : archive partielle supprimée, historique conservé."
    fi
    exit $code
}
trap nettoyer EXIT

# --------------------------------------------------------------- 1. Dump

journal "Sauvegarde de ${BASE} vers ${ARCHIVE}"

# --single-transaction : dump cohérent sans verrouiller les tables, donc sans
#   interrompre les ventes en cours (InnoDB).
# --quick : ne charge pas les tables entières en mémoire, ce qui compte sur un
#   petit VPS quand l'historique grossit.
# --set-gtid-purged=OFF est volontairement absent : inutile hors réplication.
mysqldump \
    --single-transaction \
    --quick \
    --routines \
    --events \
    --default-character-set=utf8mb4 \
    "$BASE" \
    | gzip -9 > "$PARTIEL"

# ------------------------------------------------------- 2. Vérification

# Un dump vide ou tronqué est le pire cas : il passe inaperçu jusqu'au jour de
# la restauration. On refuse d'aller plus loin — et surtout de purger
# l'historique — sans avoir vérifié que l'archive du jour tient debout.

if [[ ! -s "$PARTIEL" ]]; then
    journal "ÉCHEC : le dump est vide."
    exit 1
fi

if ! gzip -t "$PARTIEL" 2>/dev/null; then
    journal "ÉCHEC : le dump est illisible."
    exit 1
fi

# Le marqueur de fin que mysqldump n'écrit que s'il est allé au bout.
if ! gunzip -c "$PARTIEL" | tail -5 | grep -q "Dump completed"; then
    journal "ÉCHEC : dump incomplet (marqueur de fin absent)."
    exit 1
fi

# Vérifié : l'archive peut prendre son nom définitif.
mv "$PARTIEL" "$ARCHIVE"
chmod 600 "$ARCHIVE"

TAILLE="$(du -h "$ARCHIVE" | cut -f1)"
journal "Archive vérifiée (${TAILLE})"

# ---------------------------------------------------- 3. Copie distante

if [[ -n "$COPIE_DISTANTE" ]]; then
    if command -v rclone >/dev/null && [[ "$COPIE_DISTANTE" == *:* && "$COPIE_DISTANTE" != *@* ]]; then
        rclone copy "$ARCHIVE" "$COPIE_DISTANTE" && journal "Copiée vers ${COPIE_DISTANTE}"
    else
        rsync -a "$ARCHIVE" "$COPIE_DISTANTE" && journal "Copiée vers ${COPIE_DISTANTE}"
    fi
else
    journal "ATTENTION : aucune copie hors serveur configurée (COPIE_DISTANTE vide)."
fi

# --------------------------------------------------------- 4. Rotation

# Purge seulement maintenant, une fois l'archive du jour vérifiée : sinon une
# sauvegarde ratée effacerait l'historique tout en laissant croire au succès.
SUPPRIMEES="$(find "$DESTINATION" -name "${BASE}_*.sql.gz" -type f -mtime "+${RETENTION_JOURS}" -print -delete | wc -l)"
[[ "$SUPPRIMEES" -gt 0 ]] && journal "${SUPPRIMEES} archive(s) de plus de ${RETENTION_JOURS} jours supprimée(s)"

CONSERVEES="$(find "$DESTINATION" -name "${BASE}_*.sql.gz" -type f | wc -l)"
journal "Terminé — ${CONSERVEES} archive(s) en réserve"

# -----------------------------------------------------------------------
# Restaurer une sauvegarde (à tester au moins une fois, sur une base jetable ;
# une sauvegarde jamais restaurée n'est pas une sauvegarde vérifiée) :
#
#     mysql -e "CREATE DATABASE restauration_test"
#     gunzip < /var/sauvegardes/gestion-stock/gestion_stock_AAAA-MM-JJ.sql.gz \
#       | mysql restauration_test
#     mysql restauration_test -e "SELECT COUNT(*) FROM ventes"
#     mysql -e "DROP DATABASE restauration_test"
# -----------------------------------------------------------------------
