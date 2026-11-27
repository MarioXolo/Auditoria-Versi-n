<?php

namespace App\Services;

/**
 * «11. Estrategia de campaña»: los libros del documento de requisitos (26/11/2026).
 *
 * Todos comparten los campos comunes (comunidad y sección de catálogo, responsable,
 * fecha, estatus, costo estimado y real, foto y archivo). Cada libro agrega los suyos.
 * Los tres libros de casillas no viven aquí: son la tabla de Defensa del Voto con filtro por tipo.
 *
 * Tipos de campo propio: text, longtext, int, money, select, multiselect, date, file, photo.
 * Opciones del libro:
 *   title    campo que da nombre al registro en listados, calendario y agenda
 *   list     campos que se ven como columnas en el listado
 *   evento   true si cada registro es un evento que se reporta en la Agenda IEEQ
 *   hora     pide hora además de fecha
 *   mapa     pide marcar la ubicación en el mapa
 *   formato  hay formato de autorización para descargar, firmar y subir
 *   totals   campos numéricos que se suman en las tarjetas de resumen
 */
class StrategyBooks
{
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

    /** Nombre corto de cada libro (para listas de opciones y la agenda). */
    public static function names(): array
    {
        return array_map(fn ($b) => $b['name'], self::all());
    }

    public static function all(): array
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
                    'libros' => ['label' => 'Libros que aplican en esta comunidad', 'type' => 'multiselect', 'options' => 'books'],
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

    /** Libros de casillas: la misma tabla de Defensa del Voto con filtro por tipo. */
    public const CASILLAS = [
        'Normal' => ['Casillas', 'Sección, domicilio, listado nominal, votos 2024 y representante.'],
        'Especial' => ['Casillas especiales', 'Las casillas especiales de la misma lista.'],
        'Nueva' => ['Casillas nuevas', 'Las casillas nuevas y el motivo.'],
    ];

    /** Opciones de un select/multiselect (las de «libros» salen del propio catálogo de libros). */
    public static function options(array $field): array
    {
        return $field['options'] === 'books' ? self::names() : array_combine($field['options'], $field['options']);
    }
}
