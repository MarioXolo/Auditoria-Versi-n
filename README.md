# Sistema de Auditorías · Versión 1

Portal web para controlar y auditar las actividades y la asistencia del personal. Funciona en celular, tablet y computadora desde el navegador.

## Roles

| Rol | Qué puede hacer |
|---|---|
| **Administrador** | Todo: es el único que crea, edita y desactiva usuarios. |
| **Gerente** | Todo lo demás: ve todas las actividades y la asistencia, asigna a encargados y empleados, administra equipos, consulta la lista de usuarios y usa el dashboard de auditoría. No crea ni edita usuarios. |
| **Encargado** | Solo crea y asigna actividades a los empleados de su equipo. Ve las actividades que él asignó, las que le asignaron a él y las que sus empleados se cargaron, y las marca como completadas o incompletas. No ve las de otros encargados. Ve la asistencia y las fotos de entrada de su equipo. |
| **Empleado** | Solo ve las actividades asignadas a él, aunque comparta equipo con otros. Puede cargarse actividades a sí mismo, pero no editarlas, borrarlas ni marcarlas. Registra su entrada y salida. |

## Módulos de esta versión

- **Inicio de sesión** con bloqueo tras 5 intentos fallidos por minuto. Los usuarios desactivados no pueden entrar.
- **Usuarios y equipos** (solo administrador). Cada equipo tiene un encargado.
- **Actividades**: el formulario pide, en orden, ID automático (folio ACT-0001), tipo de actividad, comunidad, persona asignada, evidencia (fotografía o texto), fecha de inicio y fecha de fin. Se buscan por folio, tipo o comunidad y se filtran por estado, tipo, comunidad, persona, equipo y vencidas (fecha de fin pasada sin marcar).
- **Catálogos** (administrador y gerente): altas, cambios de nombre y activación de tipos de actividad y comunidades. Lo que ya tiene actividades no se borra; se desactiva para que deje de salir en el formulario.
- **Marcado de actividades**: solo un superior marca cada actividad con un checkbox: ✓ completada o ✗ incompleta (la actividad se pinta de rojo). Volver a tocar la marca la quita. Nadie marca una actividad asignada a sí mismo.
- **Actividades cargadas por el empleado**: el empleado puede cargarse actividades a sí mismo (no editarlas ni borrarlas); su encargado las ve y las marca.
- **Asistencia**: entrada con foto obligatoria tomada desde la cámara y salida con foto opcional. Encargados, gerentes y administradores ven quién registró y quién no, por día.
- **Dashboard** (administrador y gerente): indicadores del periodo, cumplimiento por persona, quién tiene más actividades asignadas, desempeño de encargados, personal sin entrada hoy, avance por tipo de actividad y por comunidad, y movimientos recientes.
- **Mi perfil**: cada usuario cambia su contraseña.

Las fotos y evidencias se guardan en `storage/app/private` y solo se muestran a quien tiene permiso; no quedan públicas en internet.

## Vista previa en GitHub Codespaces (sin servidor)

1. En la página del repositorio, da clic en **Code → Codespaces → Create codespace on main**.
2. Espera unos minutos: se instalan las dependencias y se cargan datos de prueba automáticamente.
3. Cuando termine, se abre la aplicación (si no, ve a la pestaña **Ports** y abre el puerto **8000**).
4. Entra con cualquiera de estos usuarios, contraseña `password`:
   - Administrador: `admin@demo.test`
   - Gerente: `gerente@demo.test`
   - Encargado: `encargado.norte@demo.test`
   - Empleado: `empleado.norte@demo.test`

Si cierras el codespace y lo vuelves a abrir, el servidor arranca solo. Si no, en la terminal corre `php artisan serve --host=0.0.0.0 --port=8000`. Para no consumir tus horas gratis, detén el codespace cuando termines (**Codespaces → Stop**).

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

Usuarios demo: `gerente@demo.test`, `encargado.norte@demo.test`, `encargado.sur@demo.test`, `empleado.norte@demo.test`, `empleado.sur@demo.test`.

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
