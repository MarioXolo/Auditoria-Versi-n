<?php

namespace App\Support;

/**
 * Punto 19 · Permisos por perfil: para cada módulo, qué acciones puede hacer un perfil.
 *
 *   ver       entrar y consultar
 *   capturar  agregar, editar y borrar registros
 *   revisar   aprobar o cambiar estatus de control (p. ej. marcar un evento como reportado al IEEQ)
 *   exportar  descargar CSV
 *
 * El administrador puede todo siempre. Un gerente sin perfil conserva el acceso completo que ya
 * tenía; un encargado sin perfil no entra a estos módulos (igual que antes).
 */
final class Access
{
    public const ACTIONS = [
        'ver' => 'Ver',
        'capturar' => 'Capturar',
        'revisar' => 'Revisar',
        'exportar' => 'Exportar',
    ];

    /** Módulos con permisos (clave => [nombre, descripción]). */
    public const MODULES = [
        'cuaderno' => ['Cuaderno Maestro', 'Hojas 1, 2 y 5 a 10: datos, estructura territorial, actores, defensa del voto, presupuesto, cronograma, KPIs y logros.'],
        'promotores' => ['Promotores', 'Hoja 4 del Cuaderno: padrón de promotores.'],
        'votantes' => ['Base de votantes', 'Hoja 3 del Cuaderno. Datos personales.'],
        'estrategia' => ['11. Estrategia de campaña', 'Los libros: mítines, bardas, lonas, puerta por puerta…'],
        'diad' => ['12. Día D', 'Plan por casilla, sala de situación, estampida, encuesta de salida y conteo.'],
        'apoyo' => ['13. Campaña de apoyo', 'Restringido: solo los perfiles que lo tengan (el gerente sin perfil no entra).'],
        'cuartos' => ['14. Cuartos de guerra', 'Cuartos, bitácora, monitoreo y reuniones.'],
        'mensajes' => ['15. Propuestas y mensajes', '«Revisar» = aprobar mensajes.'],
        'estadisticas' => ['16. Estadísticas y análisis', 'Restringido y con registro de quién consulta o exporta.'],
        'cierre' => ['17. Cierre de campaña', 'Cierres por comunidad y cierre principal.'],
        'sectores' => ['18. Sectores', 'Organizaciones, líderes, postura y fiestas patronales.'],
        'agenda' => ['20. Agenda IEEQ', '«Revisar» = registrar si ya se reportó, folio y estatus.'],
        'minutas' => ['Minutas', 'Minutas de reunión.'],
        'organigrama' => ['19. Organigrama', 'Personas, a quién reportan y servidores públicos.'],
        'catalogos' => ['Catálogos', 'Tipos de actividad, comunidades y secciones electorales.'],
    ];

    /** Módulos que solo ve quien tenga un perfil que los incluya (el gerente sin perfil no entra). */
    public const RESTRICTED = ['apoyo', 'estadisticas'];

    /** Módulos agregados con los puntos 12 a 18 (para actualizar los perfiles de fábrica). */
    public const NEW_IN_V5 = ['diad', 'apoyo', 'cuartos', 'mensajes', 'estadisticas', 'cierre', 'sectores'];

    /** Perfiles con los que arranca el sistema (se pueden editar o borrar). */
    public static function defaults(): array
    {
        $all = array_fill_keys(array_keys(self::MODULES), array_keys(self::ACTIONS));

        return [
            ['Coordinación general', 'Ve, captura, revisa y exporta todo.', $all, false],
            ['Coordinación territorial', 'Estructura, promotores, votantes y estrategia en territorio.', [
                'cuaderno' => ['ver', 'capturar'],
                'promotores' => ['ver', 'capturar', 'revisar', 'exportar'],
                'votantes' => ['ver', 'capturar', 'exportar'],
                'estrategia' => ['ver', 'capturar', 'revisar'],
                'diad' => ['ver', 'capturar'],
                'cuartos' => ['ver', 'capturar'],
                'mensajes' => ['ver'],
                'cierre' => ['ver', 'capturar'],
                'sectores' => ['ver', 'capturar'],
                'agenda' => ['ver'],
                'organigrama' => ['ver'],
                'catalogos' => ['ver'],
            ], false],
            ['Análisis', 'Consulta y exporta para análisis; no captura.', [
                'cuaderno' => ['ver', 'exportar'],
                'promotores' => ['ver', 'exportar'],
                'votantes' => ['ver', 'exportar'],
                'estrategia' => ['ver', 'exportar'],
                'diad' => ['ver', 'exportar'],
                'cuartos' => ['ver', 'exportar'],
                'mensajes' => ['ver', 'exportar'],
                'estadisticas' => ['ver', 'capturar', 'exportar'],
                'cierre' => ['ver', 'exportar'],
                'sectores' => ['ver', 'exportar'],
                'agenda' => ['ver', 'exportar'],
                'organigrama' => ['ver'],
            ], false],
            ['Equipo de campaña de apoyo', 'Su punto 13, estrategia, mensajes y agenda de eventos.', [
                'apoyo' => ['ver', 'capturar', 'revisar', 'exportar'],
                'estrategia' => ['ver', 'capturar'],
                'mensajes' => ['ver', 'capturar'],
                'agenda' => ['ver', 'revisar'],
                'organigrama' => ['ver'],
            ], false],
            ['Promotor', 'Solo captura y ve a sus propios votantes.', [
                'votantes' => ['ver', 'capturar'],
            ], true],
        ];
    }

    /** Normaliza lo que llega del formulario: solo módulos y acciones válidos; «ver» va implícito en las demás. */
    public static function clean(array $input): array
    {
        $out = [];
        foreach (self::MODULES as $m => $_) {
            $acts = array_values(array_intersect(array_keys(self::ACTIONS), (array) ($input[$m] ?? [])));
            if ($acts && ! in_array('ver', $acts, true)) {
                array_unshift($acts, 'ver');
            }
            if ($acts) {
                $out[$m] = $acts;
            }
        }

        return $out;
    }
}
