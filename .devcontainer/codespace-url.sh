#!/usr/bin/env bash
# Ajusta APP_URL a la dirección pública del puerto 8000 del codespace.
set -euo pipefail
[ -n "${CODESPACE_NAME:-}" ] || exit 0
[ -f .env ] || exit 0

URL="https://${CODESPACE_NAME}-8000.${GITHUB_CODESPACES_PORT_FORWARDING_DOMAIN:-app.github.dev}"

sed -i '/^APP_URL=/d; /^APP_FORCE_URL=/d' .env
printf 'APP_URL=%s\nAPP_FORCE_URL=true\n' "$URL" >> .env
php artisan config:clear >/dev/null 2>&1 || true
echo "APP_URL=$URL"
