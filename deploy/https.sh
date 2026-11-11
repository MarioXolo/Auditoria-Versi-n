#!/usr/bin/env bash
# Activa HTTPS (Let's Encrypt) cuando el dominio ya apunta al servidor.
#   bash deploy/https.sh app.dominio.com correo@dominio.com
set -euo pipefail
DOMAIN="${1:?Uso: https.sh dominio correo}"
EMAIL="${2:?Uso: https.sh dominio correo}"
APP_DIR=/var/www/auditorias

sed -i "s|server_name .*;|server_name ${DOMAIN};|" /etc/nginx/sites-available/auditorias
nginx -t && systemctl reload nginx
certbot --nginx -d "$DOMAIN" --non-interactive --agree-tos -m "$EMAIL" --redirect

cd "$APP_DIR"
sed -i "s|^APP_URL=.*|APP_URL=https://${DOMAIN}|" .env
php artisan optimize
echo "HTTPS activo: https://${DOMAIN} (se renueva solo)"
