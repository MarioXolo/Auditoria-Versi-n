<?php

/*
 * Parámetros de los indicadores (KPI) del dashboard.
 * Se pueden ajustar desde el .env sin tocar código.
 */

return [

    /*
    | Máximo de equipos. Cada equipo recibe un número del 1 al máximo, que es el
    | prefijo del folio de sus actividades (ID1.0001, ID2.0001…).
    */
    'max_equipos' => 30,

    // Hora oficial de entrada y minutos de tolerancia para contar un retardo
    'hora_entrada' => env('AUDITORIA_HORA_ENTRADA', '09:00'),
    'tolerancia_minutos' => (int) env('AUDITORIA_TOLERANCIA', 10),

    // Días laborales (ISO: 1 = lunes ... 7 = domingo)
    'dias_laborales' => array_map('intval', explode(',', env('AUDITORIA_DIAS_LABORALES', '1,2,3,4,5,6'))),

    // Metas de cada indicador (en %). Verde: >= meta. Ámbar: >= meta - margen. Rojo: menor.
    'metas' => [
        'cumplimiento' => (int) env('META_CUMPLIMIENTO', 80),
        'a_tiempo' => (int) env('META_A_TIEMPO', 85),
        'asistencia' => (int) env('META_ASISTENCIA', 95),
        'puntualidad' => (int) env('META_PUNTUALIDAD', 90),
    ],
    'margen_ambar' => (int) env('META_MARGEN_AMBAR', 15),

    // Umbrales de alertas
    'alertas' => [
        'dias_vencida_critica' => 3,   // actividad vencida hace más de N días
        'faltas_recientes' => 2,       // faltas en los últimos 5 días laborales
        'retardos_periodo' => 3,       // retardos en el periodo
        'cumplimiento_encargado' => 50, // % mínimo de lo que asignó un encargado
    ],
];
