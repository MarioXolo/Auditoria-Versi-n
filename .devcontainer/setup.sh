#!/usr/bin/env bash
# Prepara la vista previa en GitHub Codespaces: SQLite + datos de prueba.
set -euo pipefail

composer install --no-interaction --prefer-dist

if [ ! -f .env ]; then
    cp .env.example .env
    # SQLite en lugar de MySQL para la vista previa
    sed -i 's/^DB_CONNECTION=.*/DB_CONNECTION=sqlite/' .env
    sed -i 's/^DB_HOST=/# DB_HOST=/; s/^DB_PORT=/# DB_PORT=/; s/^DB_DATABASE=/# DB_DATABASE=/; s/^DB_USERNAME=/# DB_USERNAME=/; s/^DB_PASSWORD=/# DB_PASSWORD=/' .env
    # Administrador de prueba
    sed -i 's/^ADMIN_EMAIL=.*/ADMIN_EMAIL=admin@demo.test/; s/^ADMIN_PASSWORD=.*/ADMIN_PASSWORD=password/' .env
    # Codespaces sirve la app por HTTPS a través de un proxy
    echo "TRUSTED_PROXIES=*" >> .env
    php artisan key:generate --force
fi

bash .devcontainer/codespace-url.sh

touch database/database.sqlite
php artisan migrate:fresh --seed --force
php artisan db:seed --class=DemoSeeder --force

echo ""
echo "Vista previa lista. Usuarios (contraseña: password):"
echo "  Administrador: admin@demo.test"
echo "  Gerente:       gerente@demo.test"
echo "  Encargado:     encargado.norte@demo.test"
echo "  Empleado:      cualquiera de la lista de usuarios"
