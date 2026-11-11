#!/usr/bin/env bash
# Conecta el Object Storage (S3 compatible, p. ej. Vultr) para fotos y respaldos.
# Pide los datos en pantalla (la llave secreta no se ve al teclear), los guarda en el .env,
# prueba escribir/leer/borrar y mueve las fotos que ya estaban en el servidor.
#   ssh -t vps 'bash /var/www/auditorias/deploy/object-storage.sh'
set -euo pipefail
APP_DIR=/var/www/auditorias
cd "$APP_DIR"

echo "Datos del Object Storage (están en Vultr → Object Storage → tu almacenamiento)"
read -rp "Hostname (ej. ewr1.vultrobjects.com): " HOST
read -rp "Nombre del bucket: " BUCKET
read -rp "Access Key: " KEY
read -rsp "Secret Key (no se ve al teclear): " SECRET; echo
HOST="${HOST#https://}"; HOST="${HOST%/}"

set_env() {
    if grep -q "^$1=" .env; then sed -i "s|^$1=.*|$1=$2|" .env; else echo "$1=$2" >> .env; fi
}
cp .env ".env.respaldo-$(date +%Y%m%d%H%M)"
set_env AWS_ACCESS_KEY_ID "$KEY"
set_env AWS_SECRET_ACCESS_KEY "$SECRET"
set_env AWS_DEFAULT_REGION us-east-1
set_env AWS_BUCKET "$BUCKET"
set_env AWS_ENDPOINT "https://${HOST}"
set_env AWS_USE_PATH_STYLE_ENDPOINT true

php artisan config:clear >/dev/null
echo "Probando conexión…"
if sudo -u www-data php artisan tinker --execute='$d=Storage::disk("s3"); $d->put("prueba-conexion.txt","ok"); echo $d->get("prueba-conexion.txt")==="ok" ? "CONEXION-OK" : "FALLO"; $d->delete("prueba-conexion.txt");' 2>&1 | grep -q CONEXION-OK; then
    set_env ARCHIVOS_DISCO s3
    php artisan optimize >/dev/null
    sudo -u www-data php artisan auditoria:mover-archivos --borrar
    chown www-data:www-data .env; chmod 640 .env
    echo "Listo: las fotos y los respaldos ahora van al Object Storage (bucket ${BUCKET})."
else
    echo "No se pudo conectar. Revisa hostname, bucket y llaves. La app sigue usando el disco del servidor."
    set_env ARCHIVOS_DISCO local
    php artisan optimize >/dev/null
    exit 1
fi
