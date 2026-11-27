<?php

namespace App\Services;

/**
 * Puntos nuevos 11 a 18 del documento de requisitos (26/11/2026): cada punto es una burbuja del
 * Cuaderno Maestro y adentro tiene sus libros (tablas).
 *
 * Todos los registros comparten los campos comunes (comunidad y sección de catálogo, responsable,
 * fecha, estatus, costo estimado y real, foto y archivo). Cada libro agrega los suyos.
 * Los tres libros de casillas del punto 11 son la tabla de Defensa del Voto con filtro por tipo.
 *
 * Tipos de campo propio: text, longtext, int, money, select, multiselect, date, bool, url, file, photo,
 *   casilla (de Defensa del Voto), promotor (del padrón), catalog (catálogo que se puede ampliar),
 *   communities (varias comunidades), propuesta (del punto 15), mensaje (solo mensajes aprobados del punto 15).
 * Opciones del libro:
 *   punto      a qué punto pertenece (11 por defecto)
 *   title      campo que da nombre al registro en listados, calendario y agenda
 *   list       campos que se ven como columnas en el listado
 *   evento     true si cada registro es un evento que se reporta en la Agenda IEEQ
 *   hora       pide hora además de fecha ('required' la vuelve obligatoria)
 *   mapa       pide marcar la ubicación en el mapa
 *   formato    hay formato de autorización para descargar, firmar y subir
 *   totals     campos numéricos que se suman en las tarjetas de resumen
 *   ratios     tarjetas de porcentaje: [etiqueta, campo numerador, campo denominador]
 *   community  'optional' si la comunidad no es obligatoria en ese libro
 *   fecha_max  fecha límite que no se puede pasar (cierre de campaña)
 */
class StrategyBooks
{
    /** Los puntos: número => [nombre, descripción, módulo de permisos, icono]. */
    public const PUNTOS = [
        11 => ['Estrategia de Campaña', 'Prioridades, mítines, bardas, lonas, puerta por puerta, desayunos, kits, caravana, cabalgata, agenda metropolitana y casillas.', 'estrategia', '🎯'],
        12 => ['Día D', 'Plan por casilla, sala de situación, estampida, encuesta de salida y conteo propio.', 'diad', '🗳️'],
        13 => ['Campaña de Apoyo', 'Agenda de eventos y visitas, comunidades y públicos, liderazgos y peticiones. Acceso restringido.', 'apoyo', '🤝'],
        14 => ['Cuartos de Guerra', 'Cuartos, bitácora por cuarto, monitoreo en redes y medios, reuniones con minuta.', 'cuartos', '🛡️'],
        15 => ['Propuestas y Mensajes', 'Propuestas por pilar, mensajes con aprobación y registro de qué se dijo dónde.', 'mensajes', '💬'],
        16 => ['Estadísticas y Análisis', 'Encuestas, resultados históricos por sección y bases protegidas. Acceso restringido y con registro.', 'estadisticas', '📊'],
        17 => ['Cierre de Campaña', 'Cierres por comunidad y cierre principal; fecha límite 2 de junio de 2027.', 'cierre', '🎉'],
        18 => ['Sectores', 'Taxistas, iglesias, mayordomos y más: líderes, postura, peticiones y compromisos.', 'sectores', '👥'],
    ];

    public static function punto(int $n): array
    {
        abort_unless(isset(self::PUNTOS[$n]), 404);
        [$name, $desc, $module, $icon] = self::PUNTOS[$n];

        return compact('name', 'desc', 'module', 'icon') + ['n' => $n];
    }

    /** Libros de un punto. */
    public static function ofPunto(int $n): array
    {
        return array_filter(self::all(), fn ($b) => $b['punto'] === $n);
    }

    /** Módulo de permisos de un libro. */
    public static function module(string $key): string
    {
        return self::PUNTOS[self::get($key)['punto']][2];
    }

    public const ESTATUS = ['Pendiente', 'En curso', 'Hecho'];

    public const PERMISO = ['Pendiente', 'En trámite', 'Obtenido', 'No requiere'];

    public static function keys(): array
    {
        return array_keys(self::all());
    }

    public static function exists(string $key): bool
    {
        return array_key_exists($key, self::all());
    }

    public static function get(string $key): array
    {
        abort_unless(self::exists($key), 404);

        return self::all()[$key] + ['key' => $key];
    }

    /** Nombre corto de cada libro (para listas de opciones y la agenda); con $punto, solo los de ese punto. */
    public static function names(?int $punto = null): array
    {
        return array_map(fn ($b) => $b['name'], $punto ? self::ofPunto($punto) : self::all());
    }

    private static ?array $all = null;

    public static function all(): array
    {
        if (self::$all !== null) {
            return self::$all;
        }
        $books = [];
        foreach ([11 => self::punto11(), 12 => self::punto12(), 13 => self::punto13(), 14 => self::punto14(),
            15 => self::punto15(), 16 => self::punto16(), 17 => self::punto17(), 18 => self::punto18()] as $n => $list) {
            foreach ($list as $k => $b) {
                $books[$k] = $b + ['punto' => $n];
            }
        }

        return self::$all = $books;
    }

    /** 11. Estrategia de campaña (los 10 libros; los 3 de casillas son Defensa del Voto). */
    private static function punto11(): array
    {
        return [
            'prioridades' => [
                'name' => 'Prioridades de comunidades',
                'title_text' => 'Prioridades de comunidades y estrategia',
                'intro' => 'Una fila por comunidad: qué tan importante es, cómo está y qué libros se aplican ahí.',
                'icon' => '★',
                'title' => 'problema',
                'fecha_label' => 'Fecha de revisión',
                'list' => ['prioridad', 'clasificacion', 'meta_votos'],
                'totals' => ['meta_votos'],
                'fields' => [
                    'prioridad' => ['label' => 'Prioridad', 'type' => 'select', 'options' => ['1', '2', '3'], 'required' => true,
                        'colors' => ['1' => 'bg-rose-100 text-rose-800', '2' => 'bg-amber-100 text-amber-800', '3' => 'bg-slate-100 text-slate-700']],
                    'clasificacion' => ['label' => 'Clasificación', 'type' => 'select', 'options' => ['Dura', 'Bisagra', 'Adversa'], 'required' => true,
                        'colors' => ['Dura' => 'bg-emerald-100 text-emerald-800', 'Bisagra' => 'bg-amber-100 text-amber-800', 'Adversa' => 'bg-rose-100 text-rose-800']],
                    'meta_votos' => ['label' => 'Meta de votos', 'type' => 'int'],
                    'problema' => ['label' => 'Problema principal', 'type' => 'text', 'max' => 255, 'required' => true],
                    'liderazgos' => ['label' => 'Liderazgos clave', 'type' => 'longtext', 'max' => 1000],
                    'libros' => ['label' => 'Libros que aplican en esta comunidad', 'type' => 'multiselect', 'options' => 'books11'],
                ],
            ],

            'mitines' => [
                'name' => 'Mítines',
                'intro' => 'Cada mitin pasa solo a la Agenda IEEQ.',
                'icon' => '📣',
                'evento' => true, 'hora' => true,
                'title' => 'lugar',
                'fecha_label' => 'Fecha del mitin',
                'list' => ['lugar', 'aforo_esperado', 'aforo_real', 'permisos'],
                'totals' => ['aforo_esperado', 'aforo_real'],
                'fields' => [
                    'lugar' => ['label' => 'Lugar', 'type' => 'text', 'max' => 200, 'required' => true],
                    'aforo_esperado' => ['label' => 'Aforo esperado', 'type' => 'int'],
                    'aforo_real' => ['label' => 'Aforo real', 'type' => 'int'],
                    'oradores' => ['label' => 'Oradores', 'type' => 'longtext', 'max' => 500],
                    'permisos' => ['label' => 'Permisos', 'type' => 'select', 'options' => self::PERMISO, 'default' => 'Pendiente'],
                ],
            ],

            'bardas' => [
                'name' => 'Bardas',
                'intro' => 'Ubicación en el mapa, autorización firmada del propietario y foto antes y después.',
                'icon' => '🧱',
                'mapa' => true, 'formato' => 'barda',
                'title' => 'direccion',
                'fecha_label' => 'Fecha de pinta',
                'list' => ['direccion', 'propietario', 'medidas', 'autorizacion'],
                'fields' => [
                    'direccion' => ['label' => 'Ubicación (calle y referencia)', 'type' => 'text', 'max' => 255, 'required' => true],
                    'propietario' => ['label' => 'Propietario', 'type' => 'text', 'max' => 150],
                    'telefono' => ['label' => 'Teléfono del propietario', 'type' => 'text', 'max' => 30],
                    'medidas' => ['label' => 'Medidas', 'type' => 'text', 'max' => 60, 'placeholder' => '8 × 2.5 m'],
                    'mensaje' => ['label' => 'Mensaje (solo aprobados)', 'type' => 'mensaje'],
                    'autorizacion' => ['label' => 'Autorización firmada', 'type' => 'file'],
                    'foto_antes' => ['label' => 'Foto antes', 'type' => 'photo'],
                    'foto_despues' => ['label' => 'Foto después', 'type' => 'photo'],
                    'fecha_borrado' => ['label' => 'Fecha de borrado', 'type' => 'date'],
                ],
            ],

            'lonas' => [
                'name' => 'Lonas',
                'intro' => 'Dónde se colocó cada lona, cuántas, autorización del dueño y cuándo se retira.',
                'icon' => '🪧',
                'mapa' => true, 'formato' => 'lona',
                'title' => 'direccion',
                'fecha_label' => 'Fecha de colocación',
                'list' => ['direccion', 'medida', 'cantidad', 'autorizacion'],
                'totals' => ['cantidad'],
                'fields' => [
                    'direccion' => ['label' => 'Ubicación (calle y referencia)', 'type' => 'text', 'max' => 255, 'required' => true],
                    'propietario' => ['label' => 'Dueño del inmueble', 'type' => 'text', 'max' => 150],
                    'medida' => ['label' => 'Medida', 'type' => 'text', 'max' => 60, 'placeholder' => '2 × 1 m'],
                    'cantidad' => ['label' => 'Cantidad', 'type' => 'int'],
                    'mensaje' => ['label' => 'Mensaje (solo aprobados)', 'type' => 'mensaje'],
                    'autorizacion' => ['label' => 'Autorización firmada del dueño', 'type' => 'file'],
                    'fecha_retiro' => ['label' => 'Fecha de retiro', 'type' => 'date'],
                ],
            ],

            'puerta' => [
                'name' => 'Puerta por puerta',
                'intro' => 'Recorridos de promoción casa por casa. Los votos identificados se capturan con nombre en la Base de Votantes.',
                'icon' => '🚪',
                'title' => 'ruta',
                'fecha_label' => 'Fecha del recorrido',
                'list' => ['ruta', 'promotor', 'viviendas', 'contactadas', 'votos_identificados'],
                'totals' => ['viviendas', 'contactadas', 'votos_identificados'],
                'fields' => [
                    'ruta' => ['label' => 'Ruta / calles', 'type' => 'text', 'max' => 255, 'required' => true],
                    'promotor' => ['label' => 'Promotor', 'type' => 'text', 'max' => 150],
                    'viviendas' => ['label' => 'Viviendas visitadas', 'type' => 'int'],
                    'contactadas' => ['label' => 'Personas contactadas', 'type' => 'int'],
                    'votos_identificados' => ['label' => 'Votos identificados', 'type' => 'int'],
                ],
            ],

            'desayunos' => [
                'name' => 'Desayunos',
                'intro' => 'Cada desayuno pasa solo a la Agenda IEEQ.',
                'icon' => '☕',
                'evento' => true, 'hora' => true,
                'title' => 'sede',
                'fecha_label' => 'Fecha del desayuno',
                'list' => ['sede', 'asistentes'],
                'totals' => ['asistentes'],
                'fields' => [
                    'sede' => ['label' => 'Sede', 'type' => 'text', 'max' => 200, 'required' => true],
                    'asistentes' => ['label' => 'Asistentes', 'type' => 'int'],
                    'lista_asistencia' => ['label' => 'Lista de asistencia', 'type' => 'file'],
                    'liderazgos' => ['label' => 'Liderazgos presentes', 'type' => 'longtext', 'max' => 1000],
                ],
            ],

            'kits' => [
                'name' => 'Kits promocionales',
                'intro' => 'Qué lleva cada kit, cuánto cuesta, cuántos hay en bodega y cuántos se entregaron por comunidad.',
                'icon' => '🎁',
                'title' => 'contenido',
                'fecha_label' => 'Fecha de entrega',
                'list' => ['contenido', 'cantidad', 'costo_unitario', 'en_bodega', 'entregados'],
                'totals' => ['cantidad', 'en_bodega', 'entregados'],
                'fields' => [
                    'contenido' => ['label' => 'Contenido del kit', 'type' => 'text', 'max' => 255, 'required' => true],
                    'cantidad' => ['label' => 'Cantidad', 'type' => 'int'],
                    'costo_unitario' => ['label' => 'Costo unitario', 'type' => 'money'],
                    'en_bodega' => ['label' => 'Inventario en bodega', 'type' => 'int'],
                    'entregados' => ['label' => 'Entregados (en esta comunidad)', 'type' => 'int'],
                ],
            ],

            'caravana' => [
                'name' => 'Caravana de coches',
                'intro' => 'Cada caravana pasa sola a la Agenda IEEQ.',
                'icon' => '🚗',
                'evento' => true, 'hora' => true,
                'title' => 'ruta',
                'fecha_label' => 'Fecha',
                'list' => ['ruta', 'vehiculos', 'permiso_vialidad'],
                'totals' => ['vehiculos'],
                'fields' => [
                    'ruta' => ['label' => 'Ruta', 'type' => 'text', 'max' => 255, 'required' => true],
                    'vehiculos' => ['label' => 'Número de vehículos', 'type' => 'int'],
                    'permiso_vialidad' => ['label' => 'Permiso de vialidad', 'type' => 'select', 'options' => self::PERMISO, 'default' => 'Pendiente'],
                ],
            ],

            'cabalgata' => [
                'name' => 'Cabalgata',
                'intro' => 'Cada cabalgata pasa sola a la Agenda IEEQ.',
                'icon' => '🐎',
                'evento' => true, 'hora' => true,
                'title' => 'ruta',
                'fecha_label' => 'Fecha',
                'list' => ['ruta', 'jinetes', 'permisos', 'seguro'],
                'totals' => ['jinetes'],
                'fields' => [
                    'ruta' => ['label' => 'Ruta', 'type' => 'text', 'max' => 255, 'required' => true],
                    'jinetes' => ['label' => 'Jinetes', 'type' => 'int'],
                    'permisos' => ['label' => 'Permisos', 'type' => 'select', 'options' => self::PERMISO, 'default' => 'Pendiente'],
                    'seguro' => ['label' => 'Seguro', 'type' => 'select', 'options' => ['Pendiente', 'En trámite', 'Contratado'], 'default' => 'Pendiente'],
                ],
            ],

            'metropolitana' => [
                'name' => 'Agenda metropolitana',
                'intro' => 'Reuniones con los 4 presidentes municipales. Si es acto de campaña pasa a la Agenda IEEQ; si es acto de gobierno, no.',
                'icon' => '🏛️',
                'evento' => true, 'hora' => true,
                'title' => 'reunion',
                'fecha_label' => 'Fecha de la reunión',
                'list' => ['reunion', 'tipo_acto'],
                'fields' => [
                    'reunion' => ['label' => 'Reunión / tema', 'type' => 'text', 'max' => 200, 'required' => true],
                    'tipo_acto' => ['label' => '¿Acto de gobierno o de campaña?', 'type' => 'select', 'options' => ['Acto de campaña', 'Acto de gobierno'], 'required' => true,
                        'colors' => ['Acto de campaña' => 'bg-brand-100 text-brand-800', 'Acto de gobierno' => 'bg-slate-100 text-slate-700']],
                    'asistentes' => ['label' => 'Asistentes', 'type' => 'longtext', 'max' => 1000],
                    'plan' => ['label' => 'Plan conjunto', 'type' => 'longtext', 'max' => 2000],
                    'acuerdos' => ['label' => 'Acuerdos', 'type' => 'longtext', 'max' => 2000],
                ],
            ],
        ];
    }

    /** 12. Día D: todo se liga a la misma lista de casillas de Defensa del Voto. */
    private static function punto12(): array
    {
        return [
            'dd_plan' => [
                'name' => 'Plan por casilla', 'icon' => '📋',
                'intro' => 'Por casilla: meta de votos, cuántos votantes de la base se le asignan y el promotor responsable.',
                'title' => 'casilla', 'fecha_label' => 'Fecha del plan',
                'list' => ['casilla', 'meta_votos', 'votantes_asignados', 'promotor'],
                'totals' => ['meta_votos', 'votantes_asignados'],
                'fields' => [
                    'casilla' => ['label' => 'Casilla', 'type' => 'casilla', 'required' => true],
                    'meta_votos' => ['label' => 'Meta de votos', 'type' => 'int', 'required' => true],
                    'votantes_asignados' => ['label' => 'Votantes de la base asignados', 'type' => 'int'],
                    'promotor' => ['label' => 'Promotor responsable', 'type' => 'promotor'],
                ],
            ],
            'dd_sala' => [
                'name' => 'Sala de situación', 'icon' => '🚨',
                'intro' => 'Apertura de casilla, llegada de representantes e incidencias, con hora y foto.',
                'hora' => 'required', 'title' => 'tipo', 'fecha_label' => 'Fecha',
                'list' => ['casilla', 'tipo', 'detalle'],
                'fields' => [
                    'casilla' => ['label' => 'Casilla', 'type' => 'casilla', 'required' => true],
                    'tipo' => ['label' => 'Qué pasó', 'type' => 'select', 'required' => true,
                        'options' => ['Apertura de casilla', 'Llegada de representantes', 'Incidencia'],
                        'colors' => ['Apertura de casilla' => 'bg-emerald-100 text-emerald-800', 'Llegada de representantes' => 'bg-sky-100 text-sky-800', 'Incidencia' => 'bg-rose-100 text-rose-800']],
                    'detalle' => ['label' => 'Detalle', 'type' => 'longtext', 'max' => 1000],
                ],
            ],
            'dd_estampida' => [
                'name' => 'Estampida', 'icon' => '🏃',
                'intro' => 'Cortes por hora: de los votantes de la base, cuántos ya votaron y cuántos faltan, por casilla y por promotor. Las tarjetas son el tablero de faltantes.',
                'hora' => 'required', 'title' => 'casilla', 'fecha_label' => 'Fecha',
                'list' => ['casilla', 'promotor', 'ya_votaron', 'faltan'],
                'totals' => ['ya_votaron', 'faltan'],
                'latest_by' => ['casilla', 'promotor'],
                'fields' => [
                    'casilla' => ['label' => 'Casilla', 'type' => 'casilla', 'required' => true],
                    'promotor' => ['label' => 'Promotor', 'type' => 'promotor'],
                    'ya_votaron' => ['label' => 'Ya votaron', 'type' => 'int', 'required' => true],
                    'faltan' => ['label' => 'Faltan', 'type' => 'int', 'required' => true],
                ],
            ],
            'dd_encuesta' => [
                'name' => 'Encuesta de salida', 'icon' => '📝',
                'intro' => 'Casillas de la muestra, encuestadores y captura por hora. El resultado estimado se calcula solo.',
                'hora' => 'required', 'title' => 'casilla', 'fecha_label' => 'Fecha',
                'list' => ['casilla', 'encuestador', 'entrevistas', 'a_favor'],
                'totals' => ['entrevistas', 'a_favor'],
                'ratios' => [['Resultado estimado a favor', 'a_favor', 'entrevistas']],
                'fields' => [
                    'casilla' => ['label' => 'Casilla de la muestra', 'type' => 'casilla', 'required' => true],
                    'encuestador' => ['label' => 'Encuestador', 'type' => 'text', 'max' => 150, 'required' => true],
                    'entrevistas' => ['label' => 'Entrevistas en esta hora', 'type' => 'int', 'required' => true],
                    'a_favor' => ['label' => 'Votaron por nosotros', 'type' => 'int', 'required' => true],
                ],
            ],
            'dd_conteo' => [
                'name' => 'Conteo propio', 'icon' => '🔢',
                'intro' => 'Resultado de cada acta al cierre, comparado con la meta de la casilla.',
                'title' => 'casilla', 'fecha_label' => 'Fecha',
                'list' => ['casilla', 'votos_nuestros', 'votos_total', 'meta'],
                'totals' => ['votos_nuestros', 'votos_total', 'meta'],
                'ratios' => [['Avance contra la meta', 'votos_nuestros', 'meta']],
                'fields' => [
                    'casilla' => ['label' => 'Casilla', 'type' => 'casilla', 'required' => true],
                    'votos_nuestros' => ['label' => 'Votos nuestros (acta)', 'type' => 'int', 'required' => true],
                    'votos_total' => ['label' => 'Votación total (acta)', 'type' => 'int', 'required' => true],
                    'meta' => ['label' => 'Meta de la casilla', 'type' => 'int', 'hint' => 'Si la dejas vacía se toma la del Plan por casilla.'],
                    'acta' => ['label' => 'Foto del acta', 'type' => 'photo'],
                ],
            ],
        ];
    }

    /** 13. Campaña de apoyo (Karen): sus eventos cuentan como gasto de la campaña y pasan a la Agenda IEEQ. */
    private static function punto13(): array
    {
        return [
            'apoyo_agenda' => [
                'name' => 'Agenda de eventos y visitas', 'icon' => '📅',
                'intro' => 'Cada evento o visita pasa sola a la Agenda IEEQ y su costo cuenta como gasto de la campaña.',
                'evento' => true, 'hora' => true, 'title' => 'lugar', 'fecha_label' => 'Fecha',
                'list' => ['tipo', 'lugar', 'publico'],
                'fields' => [
                    'tipo' => ['label' => 'Tipo', 'type' => 'select', 'options' => ['Evento', 'Visita'], 'required' => true],
                    'lugar' => ['label' => 'Lugar', 'type' => 'text', 'max' => 200, 'required' => true],
                    'publico' => ['label' => 'Público', 'type' => 'text', 'max' => 200],
                    'descripcion' => ['label' => 'Descripción', 'type' => 'longtext', 'max' => 1000],
                ],
            ],
            'apoyo_atencion' => [
                'name' => 'Comunidades, liderazgos y peticiones', 'icon' => '🙋',
                'intro' => 'Comunidades y públicos que atiende, liderazgos que suma y peticiones recibidas.',
                'title' => 'publico', 'fecha_label' => 'Fecha',
                'list' => ['publico', 'liderazgos', 'peticiones'],
                'fields' => [
                    'publico' => ['label' => 'Público que atiende', 'type' => 'text', 'max' => 200, 'required' => true],
                    'liderazgos' => ['label' => 'Liderazgos que suma', 'type' => 'longtext', 'max' => 1000],
                    'peticiones' => ['label' => 'Peticiones recibidas', 'type' => 'longtext', 'max' => 2000],
                ],
            ],
        ];
    }

    /** 14. Cuartos de guerra. */
    private static function punto14(): array
    {
        return [
            'cuartos' => [
                'name' => 'Cuartos', 'icon' => '🏠', 'community' => 'optional',
                'intro' => 'Catálogo de cuartos (central, digital, jurídico, territorial por zona y Día D; se pueden agregar más). Por cuarto: responsable, integrantes, sede y horario.',
                'title' => 'cuarto', 'fecha_label' => 'Fecha de instalación',
                'list' => ['cuarto', 'sede', 'horario'],
                'fields' => [
                    'cuarto' => ['label' => 'Cuarto', 'type' => 'catalog', 'catalog' => 'cuartos', 'required' => true],
                    'integrantes' => ['label' => 'Integrantes', 'type' => 'longtext', 'max' => 1000],
                    'sede' => ['label' => 'Sede', 'type' => 'text', 'max' => 200],
                    'horario' => ['label' => 'Horario', 'type' => 'text', 'max' => 120, 'placeholder' => 'Lun a sáb, 9 a 14 h'],
                ],
            ],
            'bitacora' => [
                'name' => 'Bitácora', 'icon' => '📓', 'community' => 'optional',
                'intro' => 'Por cuarto: tema o crisis, responsable, respuesta y estatus. «Subir al central» lo manda al cuarto central.',
                'title' => 'tema', 'fecha_label' => 'Fecha',
                'list' => ['cuarto', 'tema', 'escalado'],
                'escalate' => true,
                'fields' => [
                    'cuarto' => ['label' => 'Cuarto', 'type' => 'catalog', 'catalog' => 'cuartos', 'required' => true],
                    'tema' => ['label' => 'Tema o crisis', 'type' => 'text', 'max' => 255, 'required' => true],
                    'respuesta' => ['label' => 'Respuesta', 'type' => 'longtext', 'max' => 2000],
                    'mensaje' => ['label' => 'Mensaje usado (solo aprobados)', 'type' => 'mensaje'],
                    'escalado' => ['label' => 'Subido al cuarto central', 'type' => 'bool'],
                ],
            ],
            'monitoreo' => [
                'name' => 'Monitoreo', 'icon' => '📡', 'community' => 'optional',
                'intro' => 'Menciones en redes y medios de las personas que se monitorean.',
                'title' => 'resumen', 'fecha_label' => 'Fecha',
                'list' => ['persona', 'medio', 'tono'],
                'fields' => [
                    'persona' => ['label' => 'Persona mencionada', 'type' => 'catalog', 'catalog' => 'monitoreo', 'required' => true],
                    'medio' => ['label' => 'Red o medio', 'type' => 'text', 'max' => 120, 'required' => true],
                    'tono' => ['label' => 'Tono', 'type' => 'select', 'options' => ['Positivo', 'Neutral', 'Negativo'],
                        'colors' => ['Positivo' => 'bg-emerald-100 text-emerald-800', 'Neutral' => 'bg-slate-100 text-slate-700', 'Negativo' => 'bg-rose-100 text-rose-800']],
                    'resumen' => ['label' => 'Qué se dijo', 'type' => 'text', 'max' => 255, 'required' => true],
                    'enlace' => ['label' => 'Enlace', 'type' => 'url'],
                ],
            ],
            'cuarto_reuniones' => [
                'name' => 'Reuniones', 'icon' => '🗓️', 'community' => 'optional',
                'intro' => 'Cada reunión de un cuarto genera su minuta en Minutas para llenarla ahí.',
                'hora' => true, 'title' => 'tema', 'fecha_label' => 'Fecha de la reunión',
                'list' => ['cuarto', 'tema', 'minuta'],
                'minuta' => true,
                'fields' => [
                    'cuarto' => ['label' => 'Cuarto', 'type' => 'catalog', 'catalog' => 'cuartos', 'required' => true],
                    'tema' => ['label' => 'Tema', 'type' => 'text', 'max' => 200, 'required' => true],
                    'lugar' => ['label' => 'Lugar', 'type' => 'text', 'max' => 200],
                    'minuta' => ['label' => 'Minuta', 'type' => 'minuta'],
                ],
            ],
        ];
    }

    /** 15. Propuestas y mensajes. */
    private static function punto15(): array
    {
        return [
            'propuestas' => [
                'name' => 'Propuestas', 'icon' => '💡', 'community' => 'optional',
                'intro' => 'Por pilar: comunidades beneficiadas, costo (en «costo estimado»), fuente de financiamiento y qué orden de gobierno tiene la facultad.',
                'title' => 'propuesta', 'fecha_label' => 'Fecha',
                'list' => ['propuesta', 'pilar', 'orden_gobierno'],
                'fields' => [
                    'propuesta' => ['label' => 'Propuesta', 'type' => 'text', 'max' => 255, 'required' => true],
                    'pilar' => ['label' => 'Pilar', 'type' => 'catalog', 'catalog' => 'pilares', 'required' => true],
                    'comunidades' => ['label' => 'Comunidades beneficiadas', 'type' => 'communities'],
                    'fuente' => ['label' => 'Fuente de financiamiento', 'type' => 'text', 'max' => 200],
                    'orden_gobierno' => ['label' => 'Orden de gobierno con la facultad', 'type' => 'select', 'options' => ['Municipal', 'Estatal', 'Federal', 'Concurrente']],
                ],
            ],
            'mensajes' => [
                'name' => 'Mensajes', 'icon' => '📢', 'community' => 'optional',
                'intro' => 'Texto, pilar, campaña, público, canal y comunidad. Solo quien puede «revisar» lo aprueba; solo los aprobados o en uso se pueden elegir en bardas, lonas y cuartos de guerra.',
                'title' => 'texto', 'fecha_label' => 'Fecha',
                'list' => ['texto', 'pilar', 'campana', 'canal', 'estatus_mensaje', 'aprobado_por'],
                'approval' => 'estatus_mensaje',
                'fields' => [
                    'texto' => ['label' => 'Texto del mensaje', 'type' => 'longtext', 'max' => 1000, 'required' => true],
                    'pilar' => ['label' => 'Pilar', 'type' => 'catalog', 'catalog' => 'pilares'],
                    'campana' => ['label' => 'Campaña', 'type' => 'select', 'options' => ['Candidato', 'Campaña de apoyo', 'Sectores'], 'required' => true],
                    'publico' => ['label' => 'Público', 'type' => 'text', 'max' => 150],
                    'canal' => ['label' => 'Canal', 'type' => 'select', 'options' => ['Bardas', 'Lonas', 'Redes sociales', 'Cuartos de guerra', 'Medios', 'Eventos', 'Otro']],
                    'estatus_mensaje' => ['label' => 'Estatus del mensaje', 'type' => 'select', 'options' => ['Borrador', 'Aprobado', 'En uso', 'Retirado'], 'default' => 'Borrador', 'required' => true,
                        'colors' => ['Borrador' => 'bg-slate-100 text-slate-700', 'Aprobado' => 'bg-emerald-100 text-emerald-800', 'En uso' => 'bg-brand-100 text-brand-800', 'Retirado' => 'bg-slate-200 text-slate-500']],
                    'aprobado_por' => ['label' => 'Aprobó', 'type' => 'readonly'],
                ],
            ],
            'propuesta_dicha' => [
                'name' => 'Propuestas dichas por comunidad', 'icon' => '🗣️',
                'intro' => 'Registro de qué propuesta se dijo en qué comunidad.',
                'title' => 'propuesta', 'fecha_label' => 'Fecha',
                'list' => ['propuesta', 'contexto'],
                'fields' => [
                    'propuesta' => ['label' => 'Propuesta', 'type' => 'propuesta', 'required' => true],
                    'contexto' => ['label' => 'Dónde o en qué evento', 'type' => 'text', 'max' => 200],
                ],
            ],
        ];
    }

    /** 16. Estadísticas, encuestas y análisis electoral (acceso restringido y con registro). */
    private static function punto16(): array
    {
        return [
            'encuestas' => [
                'name' => 'Encuestas', 'icon' => '📈', 'community' => 'optional',
                'intro' => 'Casa encuestadora, fecha, muestra, margen de error, archivo y resultados principales.',
                'title' => 'casa', 'fecha_label' => 'Fecha de levantamiento',
                'list' => ['casa', 'muestra', 'margen'],
                'fields' => [
                    'casa' => ['label' => 'Casa encuestadora', 'type' => 'text', 'max' => 150, 'required' => true],
                    'muestra' => ['label' => 'Muestra', 'type' => 'int'],
                    'margen' => ['label' => 'Margen de error', 'type' => 'text', 'max' => 20, 'placeholder' => '±3.5 %'],
                    'resultados' => ['label' => 'Resultados principales', 'type' => 'longtext', 'max' => 3000],
                ],
            ],
            'historicos' => [
                'name' => 'Resultados históricos', 'icon' => '🏛️', 'community' => 'optional',
                'intro' => 'Por sección y año (2018, 2021 y 2024). Elige la sección en «Datos generales».',
                'title' => 'anio', 'fecha_label' => 'Fecha de la elección',
                'list' => ['anio', 'listado_nominal', 'votacion_total', 'votos_nuestros', 'ganador'],
                'totals' => ['votacion_total', 'votos_nuestros'],
                'ratios' => [['Participación', 'votacion_total', 'listado_nominal'], ['Nuestro porcentaje', 'votos_nuestros', 'votacion_total']],
                'fields' => [
                    'anio' => ['label' => 'Año', 'type' => 'select', 'options' => ['2018', '2021', '2024'], 'required' => true],
                    'listado_nominal' => ['label' => 'Listado nominal', 'type' => 'int'],
                    'votacion_total' => ['label' => 'Votación total', 'type' => 'int', 'required' => true],
                    'votos_nuestros' => ['label' => 'Votos de nuestra fuerza', 'type' => 'int', 'required' => true],
                    'ganador' => ['label' => 'Ganador en la sección', 'type' => 'text', 'max' => 120],
                ],
            ],
        ];
    }

    /** 17. Cierre de campaña. */
    private static function punto17(): array
    {
        return [
            'cierres' => [
                'name' => 'Cierres', 'icon' => '🎤',
                'intro' => 'Cierres por comunidad y cierre principal. Ninguno puede pasar del 2 de junio de 2027. Cada cierre pasa solo a la Agenda IEEQ.',
                'evento' => true, 'hora' => true, 'title' => 'sede', 'fecha_label' => 'Fecha del cierre',
                'fecha_max' => '2027-06-02',
                'list' => ['tipo', 'sede', 'aforo', 'proteccion_civil'],
                'totals' => ['aforo'],
                'fields' => [
                    'tipo' => ['label' => 'Tipo', 'type' => 'select', 'options' => ['Cierre por comunidad', 'Cierre principal'], 'required' => true,
                        'colors' => ['Cierre principal' => 'bg-brand-100 text-brand-800', 'Cierre por comunidad' => 'bg-slate-100 text-slate-700']],
                    'sede' => ['label' => 'Sede', 'type' => 'text', 'max' => 200, 'required' => true],
                    'aforo' => ['label' => 'Aforo', 'type' => 'int'],
                    'oradores' => ['label' => 'Oradores o artistas', 'type' => 'longtext', 'max' => 1000],
                    'templete' => ['label' => 'Templete y sonido', 'type' => 'text', 'max' => 255],
                    'seguridad' => ['label' => 'Seguridad', 'type' => 'text', 'max' => 255],
                    'proteccion_civil' => ['label' => 'Protección civil', 'type' => 'select', 'options' => self::PERMISO, 'default' => 'Pendiente'],
                    'plan_b' => ['label' => 'Plan B por lluvia', 'type' => 'longtext', 'max' => 1000],
                ],
            ],
        ];
    }

    /** 18. Sectores. */
    private static function punto18(): array
    {
        return [
            'sectores' => [
                'name' => 'Sectores', 'icon' => '👥',
                'intro' => 'Organización o grupo por sector (se pueden agregar sectores). Para mayordomos, la fiesta patronal y su fecha aparecen en el Calendario.',
                'title' => 'organizacion', 'fecha_label' => 'Fecha de contacto',
                'list' => ['sector', 'organizacion', 'lider', 'votos_influye', 'postura'],
                'totals' => ['integrantes', 'votos_influye'],
                'fields' => [
                    'sector' => ['label' => 'Sector', 'type' => 'catalog', 'catalog' => 'sectores', 'required' => true],
                    'organizacion' => ['label' => 'Organización o grupo', 'type' => 'text', 'max' => 200, 'required' => true],
                    'lider' => ['label' => 'Líder', 'type' => 'text', 'max' => 150],
                    'telefono' => ['label' => 'Teléfono', 'type' => 'text', 'max' => 30],
                    'integrantes' => ['label' => 'Integrantes', 'type' => 'int'],
                    'votos_influye' => ['label' => 'Votos que influye', 'type' => 'int'],
                    'postura' => ['label' => 'Postura', 'type' => 'select', 'options' => ['Afín', 'Neutral', 'Adverso'],
                        'colors' => ['Afín' => 'bg-emerald-100 text-emerald-800', 'Neutral' => 'bg-slate-100 text-slate-700', 'Adverso' => 'bg-rose-100 text-rose-800']],
                    'peticiones' => ['label' => 'Peticiones', 'type' => 'longtext', 'max' => 2000],
                    'compromiso' => ['label' => 'Compromiso', 'type' => 'longtext', 'max' => 2000],
                    'fiesta' => ['label' => 'Fiesta patronal (mayordomos)', 'type' => 'text', 'max' => 150],
                    'fecha_fiesta' => ['label' => 'Fecha de la fiesta patronal', 'type' => 'date'],
                ],
            ],
            'sector_eventos' => [
                'name' => 'Eventos con sectores', 'icon' => '🤝',
                'intro' => 'Reuniones o eventos con un sector. Pasan solos a la Agenda IEEQ.',
                'evento' => true, 'hora' => true, 'title' => 'lugar', 'fecha_label' => 'Fecha del evento',
                'list' => ['sector', 'lugar', 'asistentes'],
                'totals' => ['asistentes'],
                'fields' => [
                    'sector' => ['label' => 'Sector', 'type' => 'catalog', 'catalog' => 'sectores', 'required' => true],
                    'lugar' => ['label' => 'Lugar', 'type' => 'text', 'max' => 200, 'required' => true],
                    'asistentes' => ['label' => 'Asistentes', 'type' => 'int'],
                    'acuerdos' => ['label' => 'Acuerdos', 'type' => 'longtext', 'max' => 2000],
                ],
            ],
        ];
    }

    /** Catálogos que se pueden ampliar desde el propio formulario, con sus valores iniciales. */
    public const CATALOGS = [
        'cuartos' => ['Cuartos de guerra', ['Central', 'Digital', 'Jurídico', 'Territorial zona 1', 'Día D']],
        'monitoreo' => ['Personas que se monitorean', []],
        'pilares' => ['Pilares de propuestas', []],
        'sectores' => ['Sectores', ['Taxistas', 'Iglesias', 'Mayordomos']],
    ];

    /** Libros de casillas: la misma tabla de Defensa del Voto con filtro por tipo. */
    public const CASILLAS = [
        'Normal' => ['Casillas', 'Sección, domicilio, listado nominal, votos 2024 y representante.'],
        'Especial' => ['Casillas especiales', 'Las casillas especiales de la misma lista.'],
        'Nueva' => ['Casillas nuevas', 'Las casillas nuevas y el motivo.'],
    ];

    /** Opciones de un select/multiselect (las de «libros» salen del propio catálogo de libros). */
    public static function options(array $field): array
    {
        return match ($field['options']) {
            'books11' => self::names(11),
            default => array_combine($field['options'], $field['options']),
        };
    }
}
