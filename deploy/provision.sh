#!/usr/bin/env bash
# =============================================================================
# Instalación inicial del Sistema de Auditorías en un VPS Ubuntu 24.04 limpio.
# Se ejecuta UNA vez, como root:
#
#   ADMIN_EMAIL=admin@empresa.com ADMIN_NAME="Nombre" DOMAIN=app.dominio.com bash provision.sh
#
# DOMAIN es opcional: sin él, la app queda en http://IP y el HTTPS se agrega
# después con deploy/https.sh cuando el dominio apunte al servidor.
# Instala: Nginx, PHP 8.3-FPM, MySQL, Composer, firewall (ufw), fail2ban,
# actualizaciones automáticas de seguridad, swap y respaldo diario de la base.
# =============================================================================
set -euo pipefail

: "${ADMIN_EMAIL:?Falta ADMIN_EMAIL}"
ADMIN_NAME="${ADMIN_NAME:-Administrador}"
DOMAIN="${DOMAIN:-}"
APP_DIR=/var/www/auditorias
REPO=https://github.com/MarioXolo/Auditoria-Versi-n.git
BRANCH="${BRANCH:-main}"
PHP=8.3
CREDS=/root/auditorias-credenciales.txt
export DEBIAN_FRONTEND=noninteractive COMPOSER_ALLOW_SUPERUSER=1

log() { printf '\n\033[1;34m==> %s\033[0m\n' "$*"; }

IP=$(curl -fsS4 https://ifconfig.me || hostname -I | awk '{print $1}')
APP_URL="http://${IP}"
[ -n "$DOMAIN" ] && APP_URL="http://${DOMAIN}"

log "Zona horaria y swap"
timedatectl set-timezone America/Mexico_City
if ! swapon --show | grep -q swapfile; then
    fallocate -l 2G /swapfile && chmod 600 /swapfile && mkswap /swapfile && swapon /swapfile
    echo '/swapfile none swap sw 0 0' >> /etc/fstab
fi

log "Paquetes del sistema"
apt-get update -q
apt-get -y -q upgrade
apt-get install -y -q nginx mysql-server git unzip curl ufw fail2ban unattended-upgrades \
    certbot python3-certbot-nginx composer \
    php${PHP}-fpm php${PHP}-cli php${PHP}-mysql php${PHP}-sqlite3 php${PHP}-mbstring php${PHP}-xml \
    php${PHP}-curl php${PHP}-zip php${PHP}-bcmath php${PHP}-intl php${PHP}-gd

log "Firewall: solo SSH, HTTP y HTTPS"
ufw allow OpenSSH
ufw allow 'Nginx Full'
ufw --force enable
systemctl enable --now fail2ban
dpkg-reconfigure -f noninteractive unattended-upgrades

log "PHP: límites para fotos de evidencia"
cat > /etc/php/${PHP}/fpm/conf.d/90-auditorias.ini <<INI
upload_max_filesize = 12M
post_max_size = 24M
memory_limit = 256M
max_execution_time = 60
expose_php = Off
opcache.enable = 1
opcache.memory_consumption = 128
opcache.validate_timestamps = 1
INI
systemctl restart php${PHP}-fpm

log "Base de datos MySQL"
if [ ! -f "$CREDS" ]; then
    DB_PASS=$(openssl rand -base64 24 | tr -dc 'A-Za-z0-9' | head -c 24)
    ADMIN_PASS=$(openssl rand -base64 18 | tr -dc 'A-Za-z0-9' | head -c 14)
    mysql <<SQL
CREATE DATABASE IF NOT EXISTS auditoria CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER IF NOT EXISTS 'auditoria'@'localhost' IDENTIFIED BY '${DB_PASS}';
ALTER USER 'auditoria'@'localhost' IDENTIFIED BY '${DB_PASS}';
GRANT ALL PRIVILEGES ON auditoria.* TO 'auditoria'@'localhost';
FLUSH PRIVILEGES;
SQL
    umask 077
    cat > "$CREDS" <<TXT
# Credenciales del Sistema de Auditorías (solo root puede leer este archivo)
DB_DATABASE=auditoria
DB_USERNAME=auditoria
DB_PASSWORD=${DB_PASS}
ADMIN_EMAIL=${ADMIN_EMAIL}
ADMIN_PASSWORD_INICIAL=${ADMIN_PASS}
TXT
    umask 022
fi
# shellcheck disable=SC1090
source <(grep -E '^(DB_PASSWORD|ADMIN_PASSWORD_INICIAL)=' "$CREDS")

log "Código de la aplicación"
if [ ! -d "$APP_DIR/.git" ]; then
    git clone -q --branch "$BRANCH" "$REPO" "$APP_DIR"
fi
git config --global --add safe.directory "$APP_DIR"
cd "$APP_DIR"

if [ ! -f .env ]; then
    cp .env.example .env
    sed -i \
        -e "s|^APP_ENV=.*|APP_ENV=production|" \
        -e "s|^APP_DEBUG=.*|APP_DEBUG=false|" \
        -e "s|^APP_URL=.*|APP_URL=${APP_URL}|" \
        -e "s|^LOG_STACK=.*|LOG_STACK=daily|" \
        -e "s|^LOG_LEVEL=.*|LOG_LEVEL=warning|" \
        -e "s|^DB_PASSWORD=.*|DB_PASSWORD=${DB_PASSWORD}|" \
        -e "s|^ADMIN_NAME=.*|ADMIN_NAME=\"${ADMIN_NAME}\"|" \
        -e "s|^ADMIN_EMAIL=.*|ADMIN_EMAIL=${ADMIN_EMAIL}|" \
        -e "s|^ADMIN_PASSWORD=.*|ADMIN_PASSWORD=${ADMIN_PASSWORD_INICIAL}|" \
        .env
    composer install -q --no-dev --optimize-autoloader --no-interaction
    php artisan key:generate --force
else
    composer install -q --no-dev --optimize-autoloader --no-interaction
fi

php artisan migrate --force
php artisan db:seed --force          # solo crea el primer administrador si no existe
# La contraseña inicial ya no hace falta en el .env (queda en $CREDS)
sed -i "s|^ADMIN_PASSWORD=.*|ADMIN_PASSWORD=|" .env
php artisan optimize

chown -R www-data:www-data "$APP_DIR"
chmod -R ug+rwX storage bootstrap/cache
chmod 640 .env

log "Nginx"
SERVER_NAME="${DOMAIN:-_}"
sed -e "s|__SERVER_NAME__|${SERVER_NAME}|" -e "s|__APP_DIR__|${APP_DIR}|" -e "s|__PHP__|${PHP}|" \
    deploy/nginx.conf > /etc/nginx/sites-available/auditorias
ln -sf /etc/nginx/sites-available/auditorias /etc/nginx/sites-enabled/auditorias
rm -f /etc/nginx/sites-enabled/default
nginx -t
systemctl reload nginx

log "Respaldo diario de la base (03:15, se guardan 14 días)"
install -m 750 deploy/backup.sh /usr/local/bin/auditorias-backup
echo "15 3 * * * root /usr/local/bin/auditorias-backup >> /var/log/auditorias-backup.log 2>&1" > /etc/cron.d/auditorias-backup

if [ -n "$DOMAIN" ]; then
    log "HTTPS con Let's Encrypt para ${DOMAIN}"
    bash deploy/https.sh "$DOMAIN" "$ADMIN_EMAIL" || echo "No se pudo emitir el certificado todavía. Revisa que el dominio apunte a ${IP} y corre: bash ${APP_DIR}/deploy/https.sh ${DOMAIN} ${ADMIN_EMAIL}"
fi

APP_URL_FINAL=$(grep '^APP_URL=' .env | cut -d= -f2-)
cat <<FIN

=====================================================================
 Sistema de Auditorías instalado
 URL:           ${APP_URL_FINAL}
 Administrador: ${ADMIN_EMAIL}
 Contraseña inicial: ${ADMIN_PASSWORD_INICIAL}
   (cámbiala al entrar en «Mi perfil»; también está en ${CREDS})
=====================================================================
FIN
