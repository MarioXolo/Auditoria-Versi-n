#!/usr/bin/env bash
# Ajustes de capacidad y limpieza (auditoría del 17 nov 2026). Se puede volver a correr sin daño.
# Desde el codespace:  ssh vps 'sudo bash -s' < deploy/ajustes-servidor.sh
set -euo pipefail
export DEBIAN_FRONTEND=noninteractive

echo "== 1. PHP-FPM: 20 peticiones simultáneas (≈43 MB c/u, ~0.9 GB en total)"
PHPV=$(ls /etc/php | sort -V | tail -1)
POOL=/etc/php/$PHPV/fpm/pool.d/www.conf
cp -n "$POOL" "$POOL.orig"
sed -i -E 's/^pm\.max_children *=.*/pm.max_children = 20/;
           s/^pm\.start_servers *=.*/pm.start_servers = 4/;
           s/^pm\.min_spare_servers *=.*/pm.min_spare_servers = 2/;
           s/^pm\.max_spare_servers *=.*/pm.max_spare_servers = 6/;
           s/^;?pm\.max_requests *=.*/pm.max_requests = 500/' "$POOL"
php-fpm"$PHPV" -t
systemctl reload php"$PHPV"-fpm
grep -E "^pm\.max_children" "$POOL"

echo "== 2. MySQL: 1 GB de caché InnoDB"
cat > /etc/mysql/mysql.conf.d/zz-auditoria.cnf <<'CNF'
# Ajuste del Sistema de Auditorías (servidor de 4 GB)
[mysqld]
innodb_buffer_pool_size = 1G
CNF
systemctl restart mysql
mysql -N -e "SELECT CONCAT('buffer pool = ', @@innodb_buffer_pool_size/1024/1024, ' MB')"

echo "== 3. SSH: retirar el archivo viejo de cloud-init (decía PasswordAuthentication yes)"
if [ -f /etc/ssh/sshd_config.d/50-cloud-init.conf ]; then
    mv /etc/ssh/sshd_config.d/50-cloud-init.conf /root/50-cloud-init.conf.retirado
fi
sshd -t
systemctl reload ssh
sshd -T | grep -Ei '^(passwordauthentication|permitrootlogin)'

echo "== 4. Actualizaciones del sistema"
apt-get update -q >/dev/null
apt-get -y -q -o Dpkg::Options::=--force-confold upgrade | tail -2
[ -f /var/run/reboot-required ] && echo "AVISO: el sistema pide reinicio (no se reinició)" || echo "No requiere reinicio"
echo "LISTO"
