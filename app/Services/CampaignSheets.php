<?php

namespace App\Services;

use App\Models\Campaign;
use App\Models\CampaignActor;
use App\Models\CampaignAchievement;
use App\Models\CampaignBudgetItem;
use App\Models\CampaignPollingStation;
use App\Models\CampaignTask;
use Illuminate\Database\Eloquent\Model;

/**
 * Pestañas de la etapa 2 del Cuaderno Maestro (5, 6, 7, 8 y 10). Cada una se define
 * con las mismas columnas, en el mismo orden y con las mismas opciones que su hoja
 * del Excel; la vista y el controlador son comunes.
 *
 * Tipos de campo: text, int, money, percent (se captura 40 = 40 %), select, bool,
 * community (catálogo u «otra» escrita a mano), date, phone.
 */
class CampaignSheets
{
    public const POSTURAS = ['Afín', 'Neutral', 'Adverso'];
    public const ESTATUS = ['Pendiente', 'En curso', 'Hecho'];
    public const PILARES = ['Seguridad', 'Salud', 'Campo', 'Juventud', 'Cercanía'];

    public static function keys(): array
    {
        return array_keys(self::all());
    }

    public static function get(string $key): array
    {
        return self::all()[$key] + ['key' => $key];
    }

    public static function all(): array
    {
        return [
            'actores' => [
                'tab' => '5. Mapeo de Actores',
                'title' => 'Mapeo de actores y liderazgos',
                'intro' => 'Quién mueve votos en cada comunidad. Postura: Afín / Neutral / Adverso.',
                'model' => CampaignActor::class,
                'relation' => 'actors',
                'singular' => 'actor',
                'order' => [['nombre', 'asc']],
                'search' => ['nombre', 'tipo', 'responsable', 'notas'],
                'filters' => ['community_id', 'postura', 'contactado'],
                'totals' => ['votos_influye'],
                'fields' => [
                    'nombre' => ['label' => 'Nombre / Líder', 'type' => 'text', 'required' => true, 'max' => 150, 'w' => 'min-w-48'],
                    'tipo' => ['label' => 'Tipo de liderazgo', 'type' => 'text', 'max' => 120, 'w' => 'w-40', 'placeholder' => 'Comisariado ejidal'],
                    'community_id' => ['label' => 'Delegación / Comunidad', 'type' => 'community', 'w' => 'w-44'],
                    'postura' => ['label' => 'Postura', 'type' => 'select', 'options' => self::POSTURAS, 'w' => 'w-28',
                        'colors' => ['Afín' => 'bg-emerald-50 text-emerald-800', 'Neutral' => 'bg-slate-50', 'Adverso' => 'bg-rose-50 text-rose-800']],
                    'votos_influye' => ['label' => 'Votos que influye (est.)', 'type' => 'int', 'w' => 'w-24'],
                    'contactado' => ['label' => '¿Contactado?', 'type' => 'bool'],
                    'responsable' => ['label' => 'Responsable de relación', 'type' => 'text', 'max' => 150, 'w' => 'w-40'],
                    'notas' => ['label' => 'Notas', 'type' => 'text', 'max' => 500, 'w' => 'min-w-48'],
                ],
            ],

            'defensa-del-voto' => [
                'tab' => '6. Defensa del Voto',
                'title' => 'Defensa del voto — RC / RG',
                'intro' => 'Una fila por casilla. Meta: 100 % con propietario + suplente acreditados y capacitados. Sobrerreclutar 20-30 %.',
                'model' => CampaignPollingStation::class,
                'relation' => 'pollingStations',
                'singular' => 'casilla',
                'order' => [['seccion', 'asc'], ['casilla', 'asc']],
                'search' => ['seccion', 'casilla', 'rc_propietario', 'rc_suplente', 'rg_zona'],
                'filters' => ['acreditado', 'capacitado', 'reporto_acta'],
                'unique' => ['seccion', 'casilla'],
                'fields' => [
                    'seccion' => ['label' => 'Sección', 'type' => 'text', 'required' => true, 'max' => 10, 'w' => 'w-20', 'placeholder' => '0512'],
                    'casilla' => ['label' => 'Casilla', 'type' => 'text', 'required' => true, 'max' => 20, 'w' => 'w-20', 'placeholder' => 'B1'],
                    'rc_propietario' => ['label' => 'RC Propietario', 'type' => 'text', 'max' => 150, 'w' => 'w-40'],
                    'tel_propietario' => ['label' => 'Tel', 'type' => 'phone', 'w' => 'w-32'],
                    'rc_suplente' => ['label' => 'RC Suplente', 'type' => 'text', 'max' => 150, 'w' => 'w-40'],
                    'tel_suplente' => ['label' => 'Tel', 'type' => 'phone', 'w' => 'w-32'],
                    'acreditado' => ['label' => '¿Acreditado?', 'type' => 'bool'],
                    'capacitado' => ['label' => '¿Capacitado?', 'type' => 'bool'],
                    'rg_zona' => ['label' => 'RG de zona', 'type' => 'text', 'max' => 120, 'w' => 'w-32'],
                    'reporto_acta' => ['label' => 'Reportó acta día D', 'type' => 'bool'],
                ],
            ],

            'presupuesto' => [
                'tab' => '7. Presupuesto',
                'title' => 'Presupuesto maestro',
                'intro' => 'Todo se registra en el SIF del INE. NUNCA rebasar el tope legal.',
                'model' => CampaignBudgetItem::class,
                'relation' => 'budgetItems',
                'singular' => 'rubro',
                'order' => [['id', 'asc']],
                'search' => [],
                'filters' => [],
                'totals' => ['porcentaje', 'asignado', 'ejercido', 'disponible'],
                'fields' => [
                    'rubro' => ['label' => 'Rubro', 'type' => 'text', 'required' => true, 'max' => 150, 'w' => 'min-w-52'],
                    'porcentaje' => ['label' => '% sugerido', 'type' => 'percent', 'w' => 'w-20'],
                    'asignado' => ['label' => 'Asignado', 'type' => 'money', 'w' => 'w-32'],
                    'ejercido' => ['label' => 'Ejercido', 'type' => 'money', 'w' => 'w-32'],
                    'notas' => ['label' => 'Notas', 'type' => 'text', 'max' => 500, 'w' => 'min-w-48'],
                ],
                // Columna calculada (texto negro en el Excel): Disponible = Asignado − Ejercido
                'computed' => ['disponible' => ['label' => 'Disponible', 'after' => 'ejercido', 'type' => 'money', 'value' => fn (CampaignBudgetItem $r) => $r->disponible()]],
            ],

            'cronograma' => [
                'tab' => '8. Cronograma',
                'title' => 'Cronograma de campaña',
                'intro' => 'Fases y tareas rumbo a la jornada. Estatus: Pendiente / En curso / Hecho. Las tareas con fecha límite vencida y sin terminar se marcan en rojo.',
                'model' => CampaignTask::class,
                'relation' => 'tasks',
                'singular' => 'tarea',
                'order' => [['id', 'asc']],
                'search' => ['fase', 'tarea', 'area', 'nota_fecha'],
                'filters' => ['estatus', 'vencidas'],
                'fields' => [
                    'fase' => ['label' => 'Fase / Periodo', 'type' => 'text', 'max' => 120, 'w' => 'w-44', 'placeholder' => '1. Estructura'],
                    'tarea' => ['label' => 'Tarea', 'type' => 'text', 'required' => true, 'max' => 255, 'w' => 'min-w-60'],
                    'area' => ['label' => 'Área responsable', 'type' => 'text', 'max' => 120, 'w' => 'w-40'],
                    'fecha_limite' => ['label' => 'Fecha límite', 'type' => 'date', 'w' => 'w-36'],
                    'nota_fecha' => ['label' => 'Detalle de fecha', 'type' => 'text', 'max' => 255, 'w' => 'min-w-48', 'placeholder' => 'Ventana o nota del calendario IEEQ'],
                    'estatus' => ['label' => 'Estatus', 'type' => 'select', 'options' => self::ESTATUS, 'required' => true, 'default' => 'Pendiente', 'w' => 'w-28',
                        'colors' => ['Pendiente' => 'bg-slate-50', 'En curso' => 'bg-amber-50 text-amber-800', 'Hecho' => 'bg-emerald-50 text-emerald-800']],
                ],
            ],

            'logros' => [
                'tab' => '10. Logros Comunicables',
                'title' => 'Inventario de logros comunicables',
                'intro' => 'La munición del mensaje. Pilar: Seguridad / Salud / Campo / Juventud / Cercanía. Una obra emblema por comunidad.',
                'model' => CampaignAchievement::class,
                'relation' => 'achievements',
                'singular' => 'logro',
                'order' => [['id', 'asc']],
                'search' => ['logro', 'lugar', 'frase'],
                'filters' => ['pilar', 'evidencia'],
                'totals' => ['recurso'],
                'fields' => [
                    'logro' => ['label' => 'Logro / Obra / Programa', 'type' => 'text', 'required' => true, 'max' => 255, 'w' => 'min-w-56'],
                    'pilar' => ['label' => 'Pilar', 'type' => 'select', 'options' => self::PILARES, 'w' => 'w-32'],
                    'lugar' => ['label' => 'Comunidad / Colonia', 'type' => 'text', 'max' => 150, 'w' => 'w-44'],
                    'beneficiados' => ['label' => 'Beneficiados (est.)', 'type' => 'text', 'max' => 120, 'w' => 'w-32', 'placeholder' => '1,200 o «Todo el municipio»'],
                    'recurso' => ['label' => 'Recurso invertido', 'type' => 'money', 'nullable' => true, 'w' => 'w-32'],
                    'evidencia' => ['label' => '¿Evidencia visual?', 'type' => 'bool'],
                    'frase' => ['label' => 'Frase de mensaje', 'type' => 'text', 'max' => 500, 'w' => 'min-w-60'],
                ],
            ],
        ];
    }

    /** Tarjetas de resumen arriba de cada pestaña. */
    public static function summary(array $sheet, Campaign $campaign): array
    {
        $q = fn () => $campaign->{$sheet['relation']}();

        return match ($sheet['key']) {
            'actores' => [
                ['Actores mapeados', number_format($q()->count())],
                ['Afines', number_format($q()->where('postura', 'Afín')->count()), number_format((int) $q()->where('postura', 'Afín')->sum('votos_influye')).' votos que influyen'],
                ['Adversos', number_format($q()->where('postura', 'Adverso')->count()), number_format((int) $q()->where('postura', 'Adverso')->sum('votos_influye')).' votos que influyen'],
                ['Contactados', number_format($q()->where('contactado', true)->count()).' de '.number_format($q()->count())],
            ],
            'defensa-del-voto' => (function () use ($q) {
                $total = $q()->count();
                $cubiertas = $q()->whereNotNull('rc_propietario')->where('rc_propietario', '!=', '')
                    ->whereNotNull('rc_suplente')->where('rc_suplente', '!=', '')->count();
                $pct = fn ($n) => $total > 0 ? number_format($n / $total * 100, 1).' %' : '—';

                return [
                    ['Casillas registradas', number_format($total)],
                    ['Con RC propietario + suplente', number_format($cubiertas), $pct($cubiertas)],
                    ['Acreditados', number_format($a = $q()->where('acreditado', true)->count()), $pct($a)],
                    ['Capacitados', number_format($c = $q()->where('capacitado', true)->count()), $pct($c)],
                ];
            })(),
            'presupuesto' => (function () use ($q, $campaign) {
                $asignado = (float) $q()->sum('asignado');
                $ejercido = (float) $q()->sum('ejercido');
                $tope = $campaign->tope_gastos;

                return [
                    ['Tope de gastos', $tope > 0 ? self::money($tope) : 'Sin capturar'],
                    ['Asignado', self::money($asignado), $tope > 0 ? number_format($asignado / $tope * 100, 1).' % del tope' : null],
                    ['Ejercido', self::money($ejercido), $tope > 0 ? number_format($ejercido / $tope * 100, 1).' % del tope' : null],
                    ['Disponible', self::money($asignado - $ejercido)],
                ];
            })(),
            'cronograma' => (function () use ($q) {
                $total = $q()->count();
                $hechas = $q()->where('estatus', 'Hecho')->count();

                return [
                    ['Tareas', number_format($total)],
                    ['Hechas', number_format($hechas), $total > 0 ? number_format($hechas / $total * 100, 1).' %' : null],
                    ['En curso', number_format($q()->where('estatus', 'En curso')->count())],
                    ['Vencidas', number_format($q()->where('estatus', '!=', 'Hecho')->whereDate('fecha_limite', '<', today())->count())],
                ];
            })(),
            'logros' => [
                ['Logros', number_format($q()->count())],
                ['Recurso invertido', self::money((float) $q()->sum('recurso'))],
                ['Con evidencia visual', number_format($q()->where('evidencia', true)->count())],
                ['Comunidades con logro', number_format($q()->whereNotNull('lugar')->distinct()->count('lugar'))],
            ],
        };
    }

    public static function money(?float $v): string
    {
        return $v === null ? '—' : '$'.number_format($v, fmod($v, 1) == 0.0 ? 0 : 2);
    }

    /** Valor de una celda como texto (tabla de solo lectura y CSV). */
    public static function display(array $field, Model $row, string $name): string
    {
        $v = $row->{$name};

        return match ($field['type']) {
            'bool' => $v ? 'Sí' : 'No',
            'money' => $v === null ? '' : self::money((float) $v),
            'percent' => number_format((float) $v * 100, 1).'%',
            'int' => number_format((int) $v),
            'date' => $v?->format('d/m/Y') ?? '',
            'community' => (string) $row->community?->name,
            default => (string) $v,
        };
    }

    /** Fecha límite y tareas sugeridas del cronograma del Excel (calendario IEEQ 2026-2027). */
    public const TASK_TEMPLATE = [
        ['0. Arranque (M-10/M-8)', 'Encargar encuesta basal', 'Inteligencia electoral', null, '4 encuestas ya integradas', 'Hecho'],
        ['0. Arranque', 'Conseguir calendario y topes IEEQ', 'Jurídico', null, 'Calendario: obtenido ✓ · Tope de gastos: pendiente', 'Hecho'],
        ['0. Arranque', 'Auditoría de logros comunicables', 'Comunicación', null, null, 'Pendiente'],
        ['0. Arranque', 'Mapeo de actores por delegación', 'Territorio', null, null, 'Pendiente'],
        ['0. Arranque', 'Instalar protocolo 134 con el gabinete', 'Jurídico', '2027-04-04', 'Veda de propaganda gubernamental: 4 abr – 6 jun 2027', 'Pendiente'],
        ['0. Arranque', 'Definir presupuesto y meta de votos', 'Coordinación / Finanzas', null, 'Meta: definida (provisional) · Presupuesto: falta tope IEEQ', 'En curso'],
        ['1. Estructura (M-8/M-6)', 'Estructura seccional al 100%', 'Territorio', null, null, 'Pendiente'],
        ['1. Estructura', 'Definir eslogan y 3 mensajes fuerza', 'Estrategia', null, null, 'Pendiente'],
        ['1. Estructura', 'Negociar coalición y método de selección PAN', 'Operación política', '2027-01-14', 'LÍMITE convenio de coalición', 'Pendiente'],
        ['2. Identificación (M-6/M-4)', 'Identificar 60% de la meta de votos', 'Territorio', null, null, 'Pendiente'],
        ['2. Identificación', 'Producción de contenido y presencia digital', 'Digital', null, null, 'Pendiente'],
        ['2. Precampaña', 'Precampaña dentro de topes', 'Coordinación / Jurídico', '2027-02-12', '14 ene – 12 feb 2027 (precampaña; confirmar ventana interna PAN)', 'Pendiente'],
        ['3. Cierre estructura (M-4/M-2)', 'Reclutar y capacitar RG/RC', 'Territorio / Jurídico', null, null, 'Pendiente'],
        ['3. Intercampaña', 'Cero proselitismo; afinar materiales y argumentario', 'Estrategia', null, null, 'Pendiente'],
        ['4. Registro (M-2/M-1)', 'Registro de candidatura y representantes', 'Jurídico', '2027-04-11', 'Registro Ayuntamiento: 7–11 abr 2027 · Separación de servidores: 8 mar 2027', 'Pendiente'],
        ['4. Preparación', 'Simulacros de casilla; preparación de debate', 'Territorio / Estrategia', null, null, 'Pendiente'],
        ['5. Campaña (~30-45 días)', 'Despliegue total; agenda diaria de territorio', 'Todas', '2027-06-02', '19 abr – 2 jun 2027 (campaña de Ayuntamientos, ~45 días)', 'Pendiente'],
        ['5. Campaña', 'Control diario de gasto (SIF)', 'Finanzas', null, null, 'Pendiente'],
        ['6. Veda (últimos 3 días)', 'Cese de proselitismo; logística día D', 'Coordinación', '2027-06-03', '3 – 6 jun 2027', 'Pendiente'],
        ['7. Jornada (día D)', 'Movilización total + defensa del voto + conteo rápido', 'Todas', '2027-06-06', 'Jornada electoral', 'Pendiente'],
        ['8. Post-jornada', 'Cómputos, resguardo de actas, defensa jurídica', 'Jurídico', '2027-06-09', 'Inicio de cómputos', 'Pendiente'],
    ];
}
