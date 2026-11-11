#!/usr/bin/env bash
# Publica la versión más reciente (o una etiqueta) en el servidor, conservando los datos.
#   bash /var/www/auditorias/deploy/deploy.sh          -> último commit de main
#   bash /var/www/auditorias/deploy/deploy.sh v1.0.0   -> una versión etiquetada
set -euo pipefail
export COMPOSER_ALLOW_SUPERUSER=1
APP_DIR=/var/www/auditorias
REF="${1:-origin/main}"
cd "$APP_DIR"

echo "==> Respaldo previo de la base"
/usr/local/bin/auditorias-backup

php artisan down --retry=15 || true
trap 'php artisan up' EXIT

echo "==> Código: ${REF}"
git fetch -q --tags origin
git reset -q --hard "$REF"

composer install -q --no-dev --optimize-autoloader --no-interaction
php artisan migrate --force          # solo aplica migraciones nuevas; no borra datos
php artisan optimize

chown -R www-data:www-data "$APP_DIR"
chmod 640 .env
systemctl reload php8.3-fpm

echo "==> Publicado: $(git describe --tags --always) ($(git log -1 --format=%cd --date=format:'%d/%m/%Y %H:%M'))"
