#!/usr/bin/env bash

set -euo pipefail

# Local DB (origem)
LOCAL_DB_NAME="${LOCAL_DB_NAME:-absglobal}"
LOCAL_DB_USER="${LOCAL_DB_USER:-root}"
LOCAL_DB_PASS="${LOCAL_DB_PASS:-root}"
LOCAL_DB_SOCKET="${LOCAL_DB_SOCKET:-/Applications/MAMP/tmp/mysql/mysql.sock}"

# Produção (destino)
PROD_SSH_HOST="${PROD_SSH_HOST:-217.216.92.134}"
PROD_SSH_USER="${PROD_SSH_USER:-root}"
PROD_DB_NAME="${PROD_DB_NAME:-absloja}"
PROD_DB_DEFAULTS_FILE="${PROD_DB_DEFAULTS_FILE:-/root/.my.cnf}"
PROD_WP_PATH="${PROD_WP_PATH:-/var/www/html/absloja}"
PROD_SITE_URL="${PROD_SITE_URL:-https://absloja.jjconsulting.com.br}"

# Opcional: URL da base local para search-replace com WP-CLI (se existir no servidor)
LOCAL_SITE_URL="${LOCAL_SITE_URL:-http://localhost:8888/absglobal}"

TIMESTAMP="$(date +%Y%m%d-%H%M%S)"
LOCAL_DUMP="/tmp/${LOCAL_DB_NAME}-local-${TIMESTAMP}.sql.gz"
REMOTE_DUMP="/root/backups/${LOCAL_DB_NAME}-from-local-${TIMESTAMP}.sql.gz"
REMOTE_BACKUP="/root/backups/${PROD_DB_NAME}-prod-before-sync-${TIMESTAMP}.sql.gz"

need_cmd() {
  command -v "$1" >/dev/null 2>&1 || {
    echo "Comando obrigatório não encontrado: $1" >&2
    exit 1
  }
}

need_cmd ssh
need_cmd scp
need_cmd gzip
need_cmd /Applications/MAMP/Library/bin/mysqldump

echo "1/5 - Gerando dump local (${LOCAL_DB_NAME}) em ${LOCAL_DUMP}"
/Applications/MAMP/Library/bin/mysqldump \
  -u"${LOCAL_DB_USER}" \
  -p"${LOCAL_DB_PASS}" \
  --socket="${LOCAL_DB_SOCKET}" \
  --single-transaction \
  --routines \
  --triggers \
  --add-drop-table \
  "${LOCAL_DB_NAME}" | gzip -1 > "${LOCAL_DUMP}"

echo "2/5 - Gerando backup do banco de produção (${PROD_DB_NAME}) em ${REMOTE_BACKUP}"
ssh -o StrictHostKeyChecking=no "${PROD_SSH_USER}@${PROD_SSH_HOST}" "\
  mkdir -p /root/backups && \
  mysqldump --defaults-file='${PROD_DB_DEFAULTS_FILE}' --single-transaction --routines --triggers '${PROD_DB_NAME}' | gzip -1 > '${REMOTE_BACKUP}' && \
  ls -lah '${REMOTE_BACKUP}'"

echo "3/5 - Enviando dump local para produção (${REMOTE_DUMP})"
scp -o StrictHostKeyChecking=no "${LOCAL_DUMP}" "${PROD_SSH_USER}@${PROD_SSH_HOST}:${REMOTE_DUMP}"

echo "4/5 - Importando dump em produção e normalizando URL do ambiente"
ssh -o StrictHostKeyChecking=no "${PROD_SSH_USER}@${PROD_SSH_HOST}" "\
  set -euo pipefail && \
  gzip -dc '${REMOTE_DUMP}' | mysql --defaults-file='${PROD_DB_DEFAULTS_FILE}' '${PROD_DB_NAME}' && \
  mysql --defaults-file='${PROD_DB_DEFAULTS_FILE}' -D '${PROD_DB_NAME}' -e \"UPDATE wp_options SET option_value='${PROD_SITE_URL}' WHERE option_name IN ('siteurl','home');\" && \
  mysql --defaults-file='${PROD_DB_DEFAULTS_FILE}' -D '${PROD_DB_NAME}' -e \"DELETE FROM wp_options WHERE option_name IN ('_transient_wc_tracks_blog_details','_transient_timeout_wc_tracks_blog_details');\" && \
  if command -v wp >/dev/null 2>&1; then \
    cd '${PROD_WP_PATH}' && \
    wp search-replace '${LOCAL_SITE_URL}' '${PROD_SITE_URL}' --all-tables-with-prefix --skip-columns=guid --precise --quiet; \
  fi && \
  mysql --defaults-file='${PROD_DB_DEFAULTS_FILE}' -D '${PROD_DB_NAME}' -e \"SELECT option_name,option_value FROM wp_options WHERE option_name IN ('siteurl','home') ORDER BY option_name;\""

echo "5/5 - Validando HTTP em produção"
ssh -o StrictHostKeyChecking=no "${PROD_SSH_USER}@${PROD_SSH_HOST}" "curl -k -s -I '${PROD_SITE_URL}' | head -n 1"

echo
echo "Sincronização concluída."
echo "Backup produção: ${REMOTE_BACKUP}"
echo "Dump local: ${LOCAL_DUMP}"
