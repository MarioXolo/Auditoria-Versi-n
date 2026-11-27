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

    /*
    | Fotos de evidencia y asistencia.
    | disco: «local» (disco del servidor) o «s3» (Object Storage, ver AWS_* en el .env).
    | Las fotos JPG/PNG/WEBP se reducen al subirlas para ahorrar espacio.
    */
    'archivos' => [
        'disco' => env('ARCHIVOS_DISCO', 'local'),
        'comprimir' => (bool) env('ARCHIVOS_COMPRIMIR', true),
        'lado_maximo' => (int) env('ARCHIVOS_LADO_MAXIMO', 1600),
        'calidad' => (int) env('ARCHIVOS_CALIDAD', 75),
    ],

    // Hora oficial de entrada y minutos de tolerancia para contar un retardo
    'hora_entrada' => env('AUDITORIA_HORA_ENTRADA', '09:00'),
    'tolerancia_minutos' => (int) env('AUDITORIA_TOLERANCIA', 10),

    // Días laborales (ISO: 1 = lunes ... 7 = domingo). El checador solo acepta registros estos días.
    'dias_laborales' => array_map('intval', explode(',', env('AUDITORIA_DIAS_LABORALES', '1,2,3,4,5'))),

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
        'dias_sin_movimiento' => (int) env('ALERTA_DIAS_SIN_MOVIMIENTO', 5),        // encargado sin cargar, concluir ni checar
        'dias_comunidad_olvidada' => (int) env('ALERTA_DIAS_COMUNIDAD_OLVIDADA', 15), // comunidad sin actividades ni registros
    ],

    // Agenda reportada al IEEQ: con cuántos días de anticipación se reporta cada evento
    // (verificar el plazo vigente con el jurídico) y desde cuántos días antes se avisa.
    'ieeq_dias_anticipacion' => (int) env('IEEQ_DIAS_ANTICIPACION', 7),
    'ieeq_dias_aviso' => (int) env('IEEQ_DIAS_AVISO', 3),

    /*
    | Semáforo del Cuaderno Maestro (automático, nadie lo captura).
    | Rojo: algo vencido (fecha pasada sin «Hecho», tarea vencida, evento sin reportar a tiempo, tope rebasado, KPI en rojo).
    | Amarillo: algo vence dentro de «dias_aviso» o la hoja lleva «dias_sin_movimiento» sin capturas.
    */
    'semaforo' => [
        'dias_aviso' => (int) env('SEMAFORO_DIAS_AVISO', 7),
        'dias_sin_movimiento' => (int) env('SEMAFORO_DIAS_SIN_MOVIMIENTO', 15),
    ],
];
