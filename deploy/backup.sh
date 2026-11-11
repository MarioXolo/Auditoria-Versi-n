#!/usr/bin/env bash
# Respaldo de la base de datos (comprimido). Conserva 14 días.
set -euo pipefail
DIR=/var/backups/auditorias
mkdir -p "$DIR"
STAMP=$(date +%Y%m%d-%H%M)
mysqldump --single-transaction --quick --routines auditoria | gzip > "$DIR/auditoria-$STAMP.sql.gz"
find "$DIR" -name 'auditoria-*.sql.gz' -mtime +14 -delete
# Copia fuera del servidor (Object Storage) si está configurado
cd /var/www/auditorias && sudo -u www-data php artisan auditoria:subir-respaldo "$DIR/auditoria-$STAMP.sql.gz" || echo "Aviso: no se pudo subir el respaldo al Object Storage"
echo "$(date '+%F %T') respaldo $DIR/auditoria-$STAMP.sql.gz"
