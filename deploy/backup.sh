#!/usr/bin/env bash
# Respaldo de la base de datos (comprimido). Conserva 14 días.
set -euo pipefail
DIR=/var/backups/auditorias
mkdir -p "$DIR"
STAMP=$(date +%Y%m%d-%H%M)
mysqldump --single-transaction --quick --routines auditoria | gzip > "$DIR/auditoria-$STAMP.sql.gz"
find "$DIR" -name 'auditoria-*.sql.gz' -mtime +14 -delete
echo "$(date '+%F %T') respaldo $DIR/auditoria-$STAMP.sql.gz"
