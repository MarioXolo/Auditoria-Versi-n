#!/usr/bin/env bash
# Auditoría de capacidad y seguridad del servidor. SOLO LECTURA: no cambia nada.
# Desde el codespace:  ssh vps 'sudo bash -s' < deploy/capacidad.sh
set -uo pipefail
APP=/var/www/auditorias
DB=auditoria
h() { printf '\n== %s ==\n' "$1"; }

h "Equipo"
echo "CPU: $(nproc) núcleos · $(grep -m1 'model name' /proc/cpuinfo | cut -d: -f2 | xargs)"
free -h | awk 'NR==1||/Mem|Swap/'
echo "Encendido: $(uptime -p) · carga 1/5/15 min: $(cut -d' ' -f1-3 /proc/loadavg)"

h "Disco"
df -h / | awk 'NR==1||NR==2'
echo "Fotos (local):  $(du -sh $APP/storage/app 2>/dev/null | cut -f1)"
echo "Respaldos:      $(du -sh /var/backups/auditorias 2>/dev/null | cut -f1) en $(ls /var/backups/auditorias 2>/dev/null | wc -l) archivos"
echo "Logs Laravel:   $(du -sh $APP/storage/logs 2>/dev/null | cut -f1)"

h "Base de datos"
mysql -N -e "SELECT CONCAT('Tamaño total: ', ROUND(SUM(data_length+index_length)/1024/1024,1), ' MB') FROM information_schema.tables WHERE table_schema='$DB';"
mysql -N -e "SELECT table_name, table_rows FROM information_schema.tables WHERE table_schema='$DB' AND table_rows>0 ORDER BY table_rows DESC LIMIT 12;" | column -t
mysql -N -e "SHOW VARIABLES WHERE Variable_name IN ('max_connections','innodb_buffer_pool_size','bind_address');" | column -t
mysql -N -e "SHOW GRANTS FOR 'auditoria'@'localhost';"

h "PHP-FPM (usuarios simultáneos)"
POOL=$(ls /etc/php/*/fpm/pool.d/www.conf | head -1)
grep -E '^pm(\.max_children|\.start_servers|\.max_spare_servers)? *=' "$POOL"
RSS=$(ps -C php-fpm8.3 -o rss= 2>/dev/null | awk '{s+=$1;n++} END {if(n) printf "%d", s/n/1024}')
echo "Memoria promedio por proceso PHP: ${RSS:-?} MB"
FREE=$(free -m | awk '/Mem/ {print $7}')
[ -n "${RSS:-}" ] && [ "$RSS" -gt 0 ] && echo "Procesos PHP que caben en la RAM libre: ~$((FREE / RSS))"
grep -E 'upload_max_filesize|post_max_size|memory_limit' /etc/php/*/fpm/php.ini | grep -v '^;' | sed 's|.*/php.ini:||'

h "Tráfico (últimos 7 días)"
for f in /var/log/nginx/access.log /var/log/nginx/access.log.1; do [ -f "$f" ] && cat "$f"; done 2>/dev/null \
  | awk '{print $4}' | cut -d: -f1 | tr -d '[' | sort | uniq -c | tail -7
echo "Errores 5xx recientes: $(grep -hc '" 5[0-9][0-9] ' /var/log/nginx/access.log 2>/dev/null || echo 0)"

h "Seguridad"
echo "Puertos abiertos al exterior:"; ss -tlnp | awk 'NR>1 && $4 !~ /127.0.0.1|::1/ {print "  "$4, $6}' | sed 's/users:((//; s/,pid.*//'
ufw status | head -8
echo "fail2ban: $(fail2ban-client status sshd 2>/dev/null | grep -E 'Currently banned|Total banned' | xargs)"
grep -E '^(PasswordAuthentication|PermitRootLogin)' /etc/ssh/sshd_config /etc/ssh/sshd_config.d/*.conf 2>/dev/null | sed 's|.*:||'
grep -E '^APP_(ENV|DEBUG)=' $APP/.env
echo "Permisos de .env: $(stat -c '%a %U' $APP/.env)"
echo "Actualizaciones pendientes: $(apt list --upgradable 2>/dev/null | grep -c upgradable) (seguridad: $(apt list --upgradable 2>/dev/null | grep -c security))"
echo "Certificado HTTPS vence: $(openssl x509 -enddate -noout -in /etc/letsencrypt/live/auditorias.weadvise.site/cert.pem 2>/dev/null | cut -d= -f2)"
echo "Último respaldo: $(ls -t /var/backups/auditorias 2>/dev/null | head -1)"
