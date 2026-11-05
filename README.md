# Auditoría de Campaña · Versión 1

Portal web para controlar actividades y asistencia del personal de campaña. Funciona en celular, tablet y computadora desde el navegador.

## Roles

| Rol | Qué puede hacer |
|---|---|
| **Administrador** | Todo: usuarios, equipos, actividades, asistencia y dashboard. |
| **Gerente** | Ve todas las actividades, asigna a encargados y empleados, ve la asistencia de todos y el dashboard de auditoría. |
| **Encargado** | Asigna y da seguimiento a las actividades de los empleados de su equipo y ve su asistencia. |
| **Empleado** | Solo ve sus actividades. Registra su entrada y salida. |

## Módulos de esta versión

- **Inicio de sesión** con bloqueo tras 5 intentos fallidos por minuto. Los usuarios desactivados no pueden entrar.
- **Usuarios y equipos** (solo administrador). Cada equipo tiene un encargado.
- **Actividades**: título, descripción, persona asignada, equipo, prioridad, fecha límite, estado, evidencia (foto o PDF) y notas. Filtros por estado, persona, equipo y vencidas.
- **Asistencia**: entrada con foto obligatoria tomada desde la cámara y salida con foto opcional. Encargados, gerentes y administradores ven quién registró y quién no, por día.
- **Dashboard** (administrador y gerente): indicadores del periodo, cumplimiento por persona, quién tiene más actividades asignadas, desempeño de encargados, personal sin entrada hoy y movimientos recientes.
- **Mi perfil**: cada usuario cambia su contraseña.

Las fotos y evidencias se guardan en `storage/app/private` y solo se muestran a quien tiene permiso; no quedan públicas en internet.

## Requisitos

- PHP 8.2 o superior con extensiones `pdo_mysql`, `mbstring`, `openssl`, `fileinfo`, `gd` o `imagick`
- Composer 2
- MySQL o MariaDB
- HTTPS en producción (los navegadores solo abren la cámara en sitios seguros)

Los estilos ya van compilados en `public/build`, así que el servidor **no necesita Node.js**. Solo si cambias las vistas o los estilos, corre `npm install && npm run build` en tu computadora y sube el resultado.

## Instalación en local

```bash
composer install
cp .env.example .env
php artisan key:generate
# Edita .env con los datos de tu base de datos y del administrador
php artisan migrate --seed
php artisan serve
```

Para cargar datos de prueba (2 equipos, encargados, empleados, actividades y asistencias; contraseña `password`):

```bash
php artisan db:seed --class=DemoSeeder
```

Usuarios demo: `gerente@demo.test`, `encargado.norte@demo.test`, `encargado.sur@demo.test`.

## Instalación en el VPS con DirectAdmin

1. **Crea la base de datos** en DirectAdmin → *MySQL Management*, con su usuario y contraseña.
2. **Crea el subdominio** (por ejemplo `auditoria.tudominio.mx`) y activa el certificado SSL con Let's Encrypt.
3. **Sube el proyecto** por SSH, por ejemplo en `~/domains/auditoria.tudominio.mx/app`:
   ```bash
   git clone https://github.com/MarioXolo/auditoria-versi-n.git app
   cd app
   composer install --no-dev --optimize-autoloader
   cp .env.example .env
   php artisan key:generate
   ```
4. **Configura `.env`**: `APP_ENV=production`, `APP_DEBUG=false`, `APP_URL=https://auditoria.tudominio.mx`, los datos `DB_*` y `ADMIN_EMAIL` / `ADMIN_PASSWORD`.
5. **Crea las tablas y el administrador**:
   ```bash
   php artisan migrate --seed --force
   ```
6. **Apunta el dominio a la carpeta `public`**: reemplaza `public_html` por un enlace a `app/public`:
   ```bash
   cd ~/domains/auditoria.tudominio.mx
   mv public_html public_html_old
   ln -s app/public public_html
   ```
7. **Permisos y caché**:
   ```bash
   cd app
   chmod -R 775 storage bootstrap/cache
   php artisan config:cache && php artisan route:cache && php artisan view:cache
   ```
8. Revisa en DirectAdmin que el límite de subida de PHP (`upload_max_filesize` y `post_max_size`) sea de al menos **10M**, para las fotos del celular.

### Actualizar después de cambios

```bash
cd ~/domains/auditoria.tudominio.mx/app
git pull
composer install --no-dev --optimize-autoloader
php artisan migrate --force
php artisan optimize
```

## Estructura principal

```
app/Enums/                    Roles, estados y prioridades
app/Models/                   User, Team, Activity, Attendance
app/Policies/ActivityPolicy   Quién puede ver, editar o borrar cada actividad
app/Http/Middleware/          Control por rol y bloqueo de usuarios inactivos
app/Http/Controllers/         Login, Dashboard, Actividades, Asistencia, Usuarios, Equipos, Perfil
resources/views/              Vistas en Blade + Tailwind
routes/web.php                Rutas y qué rol entra a cada una
```

## Próximas versiones

- **V2**: validación y rechazo de evidencias, ubicación en el registro de entrada, bitácora de cambios, exportación a Excel y PDF.
- **V3**: metas por periodo, alertas de patrones raros, más gráficas y comparativos entre equipos, acceso instalable en el celular.
