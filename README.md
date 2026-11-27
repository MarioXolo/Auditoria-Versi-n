# Sistema de Auditorías · Versión 1

Portal web para controlar y auditar las actividades y la asistencia del personal. Funciona en celular, tablet y computadora desde el navegador.

## Roles

| Rol | Qué puede hacer |
|---|---|
| **Administrador** | Todo: es el único que crea, edita y desactiva usuarios. |
| **Gerente** | Todo lo demás: ve todas las actividades, asigna a los encargados y las revisa (verde/rojo), administra equipos, consulta la lista de usuarios y usa el dashboard de auditoría. No crea ni edita usuarios. |
| **Encargado** | Solo ve sus actividades: las que le asignaron y las que se cargó a sí mismo. Nunca ve las de otros encargados, aunque sean de su equipo. Edita lo que él se cargó mientras no lo revisen y cambia el estado manual de las suyas. Tiene su propio dashboard. |

Desde la versión 3 ya no existe el rol Empleado: esas cuentas se desactivaron y sus actividades quedaron en el historial.

## Módulos de esta versión

- **Inicio de sesión** con bloqueo tras 5 intentos fallidos por minuto. Los usuarios desactivados no pueden entrar.
- **Usuarios y equipos** (solo administrador). Cada equipo tiene un encargado.
- **Actividades**: el formulario pide, en orden, ID automático por equipo (el equipo 1 lleva ID1.0001, ID1.0002…; el equipo 2, ID2.0001…), tipo de actividad y comunidad (de la lista o escritos a mano; lo escrito se agrega al catálogo), persona asignada, evidencia (fotografía o texto), fecha de inicio y fecha de fin. Se buscan por folio, tipo o comunidad y se filtran por estado, tipo, comunidad, persona, equipo y vencidas (fecha de fin pasada sin marcar).
- **Calendario**: vista mensual de las actividades (por fecha de inicio o todos los días que duran), con filtros por equipo, persona, tipo, comunidad y estado. El botón «Imprimir / PDF» genera un reporte para directivos: el calendario en una hoja horizontal y el detalle de actividades en las siguientes.
- **Minutas** — solo administradores y gerentes. «Crea tu minuta» con las 5 secciones del formato de Excel del equipo (información general, participantes, puntos tratados, acuerdos y compromisos), vista para imprimir o guardar en PDF, búsqueda por texto y por mes, e importación de minutas existentes en Excel (.xlsx) sin duplicarlas.
- **Cuaderno Maestro de Campaña (V2)** — solo administradores y gerentes. Un solo cuaderno; su portada muestra las 10 hojas del Excel como burbujas para entrar a llenarlas: meta de votos (con colchón y promotores necesarios), estructura territorial, base de votantes, padrón de promotores, mapeo de actores, defensa del voto, presupuesto, cronograma, tablero KPI con semáforo y logros comunicables. Los avances se cuentan solos; cada hoja exporta a CSV.
- **Equipos**: máximo 30. Cada uno recibe un número del 1 al 30 que es el prefijo de los folios de sus actividades; las actividades de personas sin equipo usan ID0.
- **Catálogos** (administrador y gerente): altas, cambios de nombre y activación de tipos de actividad y comunidades. Lo que ya tiene actividades no se borra; se desactiva para que deje de salir en el formulario.
- **Estado de la actividad**: a mano se elige *No iniciado*, *En proceso* o *Suspendida* (también lo puede cambiar el encargado asignado). Con el checkbox: verde ✓ la pone en *Concluido* y rojo ✗ en *No completada* (la actividad se pinta de rojo). El rojo se puede quitar (regresa a *En proceso*); *Concluido* pide confirmación y es definitivo: ya nadie la edita, le cambia el estado ni le quita la marca (solo el administrador puede borrarla si se cargó por error). Cada quien puede concluir sus propias actividades (también el encargado); el rojo solo lo ponen gerentes y administradores. El dashboard cuenta Concluido y No completada.
- **Actividades cargadas por el encargado**: el encargado se carga actividades a sí mismo; un gerente o administrador las revisa.
- **Duración**: cada actividad concluida muestra cuánto duró (de la fecha de inicio al día en que se marcó «Concluido») y si terminó antes, en la fecha o después de su fecha de fin.
- **Ubicación (opcional por actividad)**: al cargar una actividad se puede marcar «Requiere acudir al lugar» y fijar el punto con un radio permitido (mapa de OpenStreetMap). Gerentes y administradores lo marcan tocando el mapa; el encargado solo con «Usar mi ubicación actual» (GPS del celular, precisión de ±100 m o mejor). El encargado asignado abre la actividad en su celular estando ahí y toca «Validar mi ubicación»: se guarda su posición, la precisión del GPS y la distancia al lugar (se rechazan lecturas con precisión peor que ±100 m). Si no la valida o queda fuera del radio, la actividad sale en rojo en la lista, en su dashboard y en las alertas del gerente; un gerente o administrador sí puede revisarla, pero el propio encargado no puede concluirla hasta validar su ubicación dentro del radio. Después de crearla, el encargado ya no puede mover el lugar: solo gerentes y administradores. Un administrador o gerente puede «Validar a mano (sin GPS)» desde el detalle, sin estar cerca y aunque ya esté concluida: escribe cómo confirmó que estuvo ahí y queda registrado quién lo hizo (se puede quitar). Si después el encargado valida con GPS, queda el dato del GPS.
- **Asistencia (checador)**: un solo botón que registra la entrada y después la salida, sin foto, solo de lunes a viernes (configurable con `AUDITORIA_DIAS_LABORALES`). Marca retardo después de la hora de entrada más la tolerancia. Solo el administrador lo ve y lo usa, y no interviene en ningún indicador del dashboard.
- **Dashboard de auditoría** (administrador y gerente): cumplimiento y entrega a tiempo, actividades por estado, duración de las actividades (antes, en la fecha o después), indicadores por encargado, avance por tipo y por comunidad, calidad de la evidencia, velocidad de revisión y movimientos recientes.
- **Mi dashboard** (encargado): cuántas actividades tiene, cuántas le asignaron y cuántas se cargó, su estado, vencidas, próximas a vencer y la duración de las que terminó.
- **Mi perfil**: cada usuario cambia su contraseña.

Las fotos y evidencias se guardan en `storage/app/private` y solo se muestran a quien tiene permiso; no quedan públicas en internet.


### Puntos nuevos (11–20) · fase 1

- **Catálogo de secciones electorales** (Catálogos): cada sección ligada a su comunidad. Al instalar se precargan las secciones que ya estaban capturadas en votantes y casillas.
- **11. Estrategia de campaña** (burbuja 11 del Cuaderno): 10 libros —prioridades de comunidades, mítines, bardas, lonas, puerta por puerta, desayunos, kits, caravana, cabalgata y agenda metropolitana— con los campos comunes (comunidad y sección de catálogo, responsable, fecha, estatus Pendiente/En curso/Hecho, costo estimado y real, foto y archivo) más los propios de cada libro. Bardas y lonas llevan ubicación en mapa, formato de autorización para imprimir/firmar/subir y fotos antes/después. Todo se exporta a CSV.
- **Casillas** (normales, especiales y nuevas): una sola tabla, la de Defensa del Voto, con tipo, domicilio, listado nominal, votos 2024 y motivo de la casilla nueva.
- **20. Agenda IEEQ** (burbuja 20): cada evento (mitin, desayuno, caravana, cabalgata y reunión metropolitana que sea acto de campaña) aparece solo, con fecha límite de reporte (`IEEQ_DIAS_ANTICIPACION`, 7 días antes por defecto; ajustable por evento), estatus Por reportar / Reportado / Modificado / Cancelado, fecha de reporte y folio. Si un evento reportado cambia de fecha, hora o lugar pasa a «Modificado». Alerta de vencidos y por vencer (`IEEQ_DIAS_AVISO`).
- **Calendario**: administradores y gerentes ven además los eventos de campaña, en verde (reportado), ámbar (por reportar) o rojo (vencido).


### Tipos de actividad privados

Lo que alguien escribe con «Otro…» en el tipo de actividad queda **privado de esa persona**: solo le aparece a ella para sus siguientes actividades y no se agrega a la lista de los demás. Gerentes y administradores lo ven en las actividades que supervisan, pero no en su lista para cargar. La lista común la administran admin y gerentes en Catálogos; el administrador ve aparte los tipos privados y puede «pasar a la lista común» el que convenga. Al instalar, los tipos que ya se habían escrito con «Otro…» (creados junto con su primera actividad y usados por una sola persona) se volvieron privados de quien los escribió. Las comunidades siguen siendo una lista común.

### Agenda del Presidente

Sección propia del menú, solo para administradores y gerentes. Sus actividades tienen los mismos campos que «Actividades» (tipo, comunidad, asignada a, inicio–fin, estado, evidencia en foto o texto, marca ✓ Concluido / ✗ No completada; Concluido es definitivo y pide confirmación), con folio propio `AP-0001`, su propia lista privada de tipos de actividad (lo que se escribe ahí no aparece en Actividades ni en Catálogos, y viceversa), vista de lista y su propio calendario mensual (cada actividad aparece en todos los días que dura; se imprime a PDF). Viven en su propia tabla: no aparecen en Actividades, dashboards, calendario ni métricas de los demás usuarios. Se asignan solo a administradores o gerentes; una concluida solo la puede borrar el administrador.

### Puntos 12 a 18 · cada uno es una burbuja del Cuaderno

El Cuaderno Maestro muestra las hojas 1 a 10 y los puntos nuevos 11 a 20 como burbujas. Del 11 al 18 cada punto tiene sus libros (tablas), todos con los campos comunes más los propios (definidos en `App\Services\StrategyBooks`):

- **12. Día D**: plan por casilla (meta, votantes asignados, promotor), sala de situación (apertura, llegada de representantes, incidencias con hora y foto), estampida (cortes por hora; el tablero toma el último corte de cada casilla y promotor), encuesta de salida (resultado estimado automático) y conteo propio (contra la meta; si el acta no la trae se toma la del plan). Las casillas son las de Defensa del Voto.
- **13. Campaña de apoyo**: agenda de eventos y visitas (pasan a la Agenda IEEQ y su costo cuenta como gasto) y comunidades/públicos, liderazgos y peticiones. Restringido: solo perfiles que lo incluyan.
- **14. Cuartos de guerra**: cuartos (catálogo ampliable: central, digital, jurídico, territorial por zona, Día D), bitácora con «Subir al central», monitoreo de menciones y reuniones que crean su minuta en Minutas.
- **15. Propuestas y mensajes**: propuestas por pilar con comunidades beneficiadas, costo, fuente y orden de gobierno; mensajes con estatus borrador/aprobado/en uso/retirado y quién lo aprobó (solo quien puede «revisar»); bardas, lonas y la bitácora solo ofrecen mensajes aprobados o en uso; registro de qué propuesta se dijo en qué comunidad.
- **16. Estadísticas**: encuestas y resultados históricos por sección (2018, 2021, 2024); bases Nica y Anahí bloqueadas para carga; acceso restringido y registro de cada consulta, archivo abierto o exportación.
- **17. Cierre de campaña**: cierres por comunidad y principal con sede, aforo, oradores, templete y sonido, seguridad, protección civil y plan B; ninguna fecha después del 2 de junio de 2027; pasan a la Agenda IEEQ.
- **18. Sectores**: catálogo ampliable (taxistas, iglesias, mayordomos), organización, líder, teléfono, integrantes, votos que influye, postura, peticiones y compromiso; la fiesta patronal de mayordomos sale cada año en el Calendario; los eventos con sectores pasan a la Agenda IEEQ.

Cada punto tiene su permiso en los perfiles de acceso. «13. Campaña de apoyo» y «16. Estadísticas» son restringidos: el gerente sin perfil no entra.

### Punto 19 · Organigrama y roles

- **Organigrama** (menú): por persona puesto, nombre, a quién reporta, equipo, delegación o sección, teléfono, cuenta en el sistema (de ahí sale su rol y perfil) y «¿es servidor público?». Se dibuja solo con «a quién reporta» (no permite ciclos); quien es servidor público sale resaltado y con alerta arriba. Al quitar a alguien, quienes le reportaban suben a su jefe.
- **Perfiles de acceso** (Usuarios → Perfiles de acceso, solo administrador): permisos de ver, capturar, revisar y exportar por módulo (Cuaderno, Promotores, Base de votantes, Estrategia, Agenda IEEQ, Minutas, Organigrama, Catálogos). Vienen 5: Coordinación general, Coordinación territorial, Análisis, Equipo de campaña de apoyo y Promotor. El perfil se asigna en cada usuario. El administrador puede todo; un gerente sin perfil conserva el acceso completo de antes y un encargado sin perfil no entra a estos módulos. El menú, las burbujas del Cuaderno y los botones se ajustan a lo que permite el perfil.
- **Promotor**: en el Padrón de Promotores se liga cada promotor con su cuenta. Con un perfil «solo sus votantes» ve, captura, edita y exporta únicamente a sus votantes; el aviso de duplicado no le muestra datos de votantes de otros.

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
