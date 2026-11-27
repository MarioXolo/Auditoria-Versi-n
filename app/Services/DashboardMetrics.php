<?php

namespace App\Services;

use App\Enums\ActivityStatus;
use App\Enums\Role;
use App\Models\Activity;
use App\Models\ActivityType;
use App\Models\Community;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Calcula los indicadores del dashboard de auditoría.
 *
 * Todas las fórmulas están documentadas en definitions() para que cualquier
 * cifra del tablero se pueda reproducir y auditar.
 */
class DashboardMetrics
{
    public const PERIODS = [
        '7' => 'Últimos 7 días',
        '30' => 'Últimos 30 días',
        '90' => 'Últimos 90 días',
        'all' => 'Todo el historial',
    ];

    public Carbon $from;

    public Carbon $to;

    public ?Carbon $prevFrom = null;

    public ?Carbon $prevTo = null;

    private Collection $staff;

    private array $cache = [];

    /** Áreas de los gerentes, en el orden del organigrama; otras que se capturen van al final. */
    public const AREAS = ['Coordinación', 'Territorial', 'Técnica', 'Agenda'];

    private ?array $areaCreators = null;

    public function __construct(public string $period = '30', public ?string $area = null, public ?int $encargadoId = null)
    {
        $this->area = filled($area) ? $area : null;
        $this->period = array_key_exists($period, self::PERIODS) ? $period : '30';
        $this->to = now();

        if ($this->period === 'all') {
            $first = Activity::min('created_at');
            $this->from = $first ? Carbon::parse($first)->startOfDay() : today()->subDays(29);
        } else {
            $days = (int) $this->period;
            $this->from = today()->subDays($days - 1);
            $this->prevTo = $this->from->copy()->subSecond();
            $this->prevFrom = $this->from->copy()->subDays($days);
        }

        // Personal auditado: los encargados (versión 3: ya no hay empleados activos)
        $this->staff = User::active()
            ->where('role', Role::Encargado->value)
            ->when($this->encargadoId, fn ($q) => $q->whereKey($this->encargadoId))
            ->orderBy('folio_number')
            ->get();
    }

    // ------------------------------------------------------------------
    // Datos base
    // ------------------------------------------------------------------

    /** Áreas que existen (las 4 del organigrama primero). */
    public static function areas(): array
    {
        $capturadas = User::where('role', Role::Gerente->value)->whereNotNull('area')->distinct()->pluck('area')->all();

        return array_values(array_unique(array_merge(array_intersect(self::AREAS, $capturadas), $capturadas)));
    }

    /** Filtro del tablero: por área (lo que encomendaron sus gerentes) y por encargado (lo que tiene asignado). */
    public function scope($q)
    {
        if ($this->area) {
            $this->areaCreators ??= User::where('role', Role::Gerente->value)->where('area', $this->area)->pluck('id')->all();
            $q->whereIn('created_by', $this->areaCreators ?: [0])->whereColumn('created_by', '!=', 'assigned_to');
        }
        if ($this->encargadoId) {
            $q->where('assigned_to', $this->encargadoId);
        }

        return $q;
    }

    /** ¿Hay algún filtro de área o encargado activo? */
    public function filtered(): bool
    {
        return $this->area !== null || $this->encargadoId !== null;
    }

    /** Actividades creadas dentro del rango. */
    private function activities(Carbon $from, Carbon $to): Collection
    {
        $key = 'act'.$from->timestamp.'-'.$to->timestamp;

        return $this->cache[$key] ??= Activity::query()
            ->whereBetween('created_at', [$from, $to])
            ->tap(fn ($q) => $this->scope($q))
            ->get(['id', 'activity_type_id', 'community_id', 'assigned_to', 'created_by', 'team_id', 'status', 'start_date', 'end_date', 'completed_at', 'created_at', 'updated_at']);
    }

    private function isOverdue(Activity $a): bool
    {
        return $a->end_date !== null
            && $a->end_date->lt(today())
            && in_array($a->status, [ActivityStatus::NoIniciado, ActivityStatus::EnProceso], true);
    }

    private function isOnTime(Activity $a): bool
    {
        return $a->status === ActivityStatus::Concluido
            && ($a->end_date === null || ($a->completed_at && $a->completed_at->copy()->startOfDay()->lte($a->end_date)));
    }

    private static function pct(int|float $part, int|float $whole): ?float
    {
        return $whole > 0 ? round($part / $whole * 100, 1) : null;
    }

    // ------------------------------------------------------------------
    // Indicadores
    // ------------------------------------------------------------------

    /** KPI de actividades para un rango. */
    public function activityKpis(Carbon $from, Carbon $to): array
    {
        $acts = $this->activities($from, $to);
        $completed = $acts->filter(fn ($a) => $a->status === ActivityStatus::Concluido);
        $cancelled = $acts->filter(fn ($a) => $a->status === ActivityStatus::Suspendida)->count();
        $effective = $acts->count() - $cancelled;

        // Duración: de la fecha de inicio al día en que se concluyó, contando ambos días
        $durations = $completed->map(fn ($a) => $a->durationDays())->filter(fn ($d) => $d !== null);

        return [
            'total' => $acts->count(),
            'completed' => $completed->count(),
            'open' => $acts->filter(fn ($a) => in_array($a->status, [ActivityStatus::NoIniciado, ActivityStatus::EnProceso], true))->count(),
            'cancelled' => $cancelled,
            'overdue' => $acts->filter(fn ($a) => $this->isOverdue($a))->count(),
            'cumplimiento' => self::pct($completed->count(), $effective),
            'a_tiempo' => self::pct($completed->filter(fn ($a) => $this->isOnTime($a))->count(), $completed->count()),
            'duracion' => $durations->isEmpty() ? null : round($durations->avg(), 1),
            'incompletas' => $acts->filter(fn ($a) => $a->status === ActivityStatus::NoCompletada)->count(),
            'sin_marcar' => $acts->filter(fn ($a) => $a->status->isOpen())->count(),
        ];
    }

    /** KPI del periodo actual con su comparativo contra el periodo anterior. */
    public function summary(): array
    {
        // El checador ya no interviene en ningún indicador: solo actividades
        $now = $this->activityKpis($this->from, $this->to);
        $prev = $this->prevFrom ? $this->activityKpis($this->prevFrom, $this->prevTo) : null;

        $delta = [];
        foreach ($now as $key => $value) {
            $delta[$key] = ($prev && $value !== null && ($prev[$key] ?? null) !== null)
                ? round($value - $prev[$key], 1)
                : null;
        }

        return ['now' => $now, 'prev' => $prev, 'delta' => $delta];
    }

    /** Verde, ámbar o rojo según la meta configurada. */
    public static function status(string $kpi, ?float $value): string
    {
        if ($value === null) {
            return 'none';
        }

        $goal = config("auditoria.metas.$kpi");

        return match (true) {
            $value >= $goal => 'ok',
            $value >= $goal - config('auditoria.margen_ambar') => 'warn',
            default => 'bad',
        };
    }

    // ------------------------------------------------------------------
    // Series para gráficas
    // ------------------------------------------------------------------

    /** Actividades creadas vs concluidas por día (o por semana en rangos largos). */
    public function activityTrend(): array
    {
        $weekly = $this->from->diffInDays($this->to) > 45;
        $buckets = [];

        for ($d = $this->from->copy(); $d->lte($this->to); $d->addDay()) {
            $key = $weekly ? $d->copy()->startOfWeek()->toDateString() : $d->toDateString();
            $buckets[$key] ??= ['label' => $weekly ? 'Sem '.$d->copy()->startOfWeek()->format('d/m') : $d->translatedFormat('D j'), 'created' => 0, 'completed' => 0];
        }

        foreach ($this->activities($this->from, $this->to) as $a) {
            $key = $weekly ? $a->created_at->copy()->startOfWeek()->toDateString() : $a->created_at->toDateString();
            if (isset($buckets[$key])) {
                $buckets[$key]['created']++;
            }
        }

        $completed = Activity::query()
            ->where('status', ActivityStatus::Concluido->value)
            ->whereBetween('completed_at', [$this->from, $this->to])
            ->tap(fn ($q) => $this->scope($q))
            ->pluck('completed_at');

        foreach ($completed as $c) {
            $c = Carbon::parse($c);
            $key = $weekly ? $c->copy()->startOfWeek()->toDateString() : $c->toDateString();
            if (isset($buckets[$key])) {
                $buckets[$key]['completed']++;
            }
        }

        return array_values($buckets);
    }

    public function byStatus(): array
    {
        $acts = $this->activities($this->from, $this->to);

        return collect(ActivityStatus::cases())->map(fn ($s) => [
            'key' => $s->value,
            'label' => $s->label(),
            'value' => $acts->filter(fn ($a) => $a->status === $s)->count(),
        ])->all();
    }

    /** Avance por tipo de actividad (los más usados primero). */
    public function byType(int $limit = 8): array
    {
        return $this->groupedBy('activity_type_id', ActivityType::pluck('name', 'id'), $limit);
    }

    /** Avance por comunidad (las de más actividades primero). */
    public function byCommunity(int $limit = 12): array
    {
        return $this->groupedBy('community_id', Community::pluck('name', 'id'), $limit);
    }

    private function groupedBy(string $column, Collection $names, int $limit): array
    {
        return $this->activities($this->from, $this->to)
            ->where('status', '!=', ActivityStatus::Suspendida)
            ->groupBy($column)
            ->map(fn (Collection $group, $id) => [
                'label' => $names[$id] ?? '—',
                'total' => $group->count(),
                'completadas' => $group->where('status', ActivityStatus::Concluido)->count(),
                'incompletas' => $group->where('status', ActivityStatus::NoCompletada)->count(),
                'sin_marcar' => $group->filter(fn ($a) => $a->status->isOpen())->count(),
            ])
            ->sortByDesc('total')
            ->take($limit)
            ->values()
            ->all();
    }

    // ------------------------------------------------------------------
    // Tablas
    // ------------------------------------------------------------------

    /** Indicadores por encargado. */
    public function people(): Collection
    {
        return $this->cache['people'] ??= $this->buildPeople();
    }

    private function buildPeople(): Collection
    {
        $acts = $this->activities($this->from, $this->to)->groupBy('assigned_to');

        return $this->staff->map(function (User $u) use ($acts) {
            $mine = $acts->get($u->id, collect());
            $completed = $mine->filter(fn ($a) => $a->status === ActivityStatus::Concluido);
            $effective = $mine->filter(fn ($a) => $a->status !== ActivityStatus::Suspendida)->count();
            $durations = $completed->map(fn ($a) => $a->durationDays())->filter(fn ($d) => $d !== null);

            return (object) [
                'user' => $u,
                'assigned' => $mine->count(),
                'completed' => $completed->count(),
                'open' => $mine->filter(fn ($x) => in_array($x->status, [ActivityStatus::NoIniciado, ActivityStatus::EnProceso], true))->count(),
                'incompletas' => $mine->filter(fn ($x) => $x->status === ActivityStatus::NoCompletada)->count(),
                'overdue' => $mine->filter(fn ($x) => $this->isOverdue($x))->count(),
                'cumplimiento' => self::pct($completed->count(), $effective),
                'a_tiempo' => self::pct($completed->filter(fn ($x) => $this->isOnTime($x))->count(), $completed->count()),
                'duracion' => $durations->isEmpty() ? null : round($durations->avg(), 1),
                'propias' => $mine->filter(fn ($x) => $x->created_by === $u->id)->count(),
            ];
        })->sortByDesc('assigned')->values();
    }

    /** Duración de las concluidas en el periodo y si terminaron antes o después de su fecha de fin. */
    public function durations(): array
    {
        return ActivityDurations::summarize(
            ActivityDurations::query($this->from, $this->to)
                ->tap(fn ($q) => $this->scope($q))
                ->with(['assignee:id,name', 'type:id,name'])
                ->get()
        );
    }

    /**
     * Calidad de la evidencia y velocidad de revisión.
     * Evidencia: de las concluidas del periodo, cuántas traen foto, solo texto o nada.
     * Revisión: días entre la fecha de fin y el momento en que se marcó verde o rojo
     * (0 si se revisó antes de la fecha de fin), y cuántas siguen esperando revisión.
     */
    public function evidenceAndReview(): array
    {
        $acts = Activity::query()
            ->whereBetween('created_at', [$this->from, $this->to])
            ->tap(fn ($q) => $this->scope($q))
            ->get(['id', 'status', 'end_date', 'evidence_path', 'evidence_text', 'marked_by', 'marked_at']);

        $concluded = $acts->filter(fn ($a) => $a->status === ActivityStatus::Concluido);
        $photo = $concluded->filter(fn ($a) => $a->evidence_path !== null)->count();
        $text = $concluded->filter(fn ($a) => $a->evidence_path === null && filled($a->evidence_text))->count();
        $none = $concluded->count() - $photo - $text;

        $wait = fn (Activity $a) => max(0, $a->end_date->copy()->startOfDay()->diffInDays($a->marked_at->copy()->startOfDay(), false));
        $reviewed = $acts->filter(fn ($a) => $a->status->isMarked() && $a->marked_at && $a->end_date);
        $pending = $acts->filter(fn ($a) => $this->isOverdue($a));
        $names = User::whereIn('id', $reviewed->pluck('marked_by')->filter()->unique())->pluck('name', 'id');

        return [
            'concluded' => $concluded->count(),
            'photo' => $photo,
            'text' => $text,
            'none' => $none,
            'with_pct' => self::pct($photo + $text, $concluded->count()),
            'reviewed' => $reviewed->count(),
            'review_days' => $reviewed->isEmpty() ? null : round($reviewed->avg($wait), 1),
            'pending' => $pending->count(),
            'pending_max_days' => $pending->isEmpty() ? null : (int) $pending->max(fn ($a) => $a->end_date->copy()->startOfDay()->diffInDays(today())),
            'reviewers' => $reviewed->filter(fn ($a) => $a->marked_by)->groupBy('marked_by')
                ->map(fn (Collection $g, $id) => (object) [
                    'name' => $names[$id] ?? 'Usuario eliminado',
                    'count' => $g->count(),
                    'days' => round($g->avg($wait), 1),
                ])
                ->sortByDesc('days')->take(5)->values(),
        ];
    }

    /**
     * Cumplimiento por área: lo que encomendó cada área de gerencia (y lo que se cargaron
     * los propios encargados), con su cumplimiento, entrega a tiempo y vencidas.
     */
    public function byArea(): array
    {
        $acts = $this->activities($this->from, $this->to);
        $creators = User::whereIn('id', $acts->pluck('created_by')->unique())->get(['id', 'role', 'area'])->keyBy('id');

        $grupo = function (Activity $a) use ($creators): string {
            if ($a->created_by === $a->assigned_to) {
                return 'Cargadas por el propio encargado';
            }
            $c = $creators->get($a->created_by);

            return match ($c?->role) {
                Role::Gerente => $c->area ?: 'Gerencia sin área',
                Role::Admin => 'Administración',
                default => 'Otros',
            };
        };

        $rows = $acts->groupBy($grupo)->map(function (Collection $g, string $label) {
            $completed = $g->filter(fn ($a) => $a->status === ActivityStatus::Concluido);
            $effective = $g->filter(fn ($a) => $a->status !== ActivityStatus::Suspendida)->count();

            return (object) [
                'label' => $label,
                'total' => $g->count(),
                'completed' => $completed->count(),
                'incompletas' => $g->filter(fn ($a) => $a->status === ActivityStatus::NoCompletada)->count(),
                'overdue' => $g->filter(fn ($a) => $this->isOverdue($a))->count(),
                'cumplimiento' => self::pct($completed->count(), $effective),
                'a_tiempo' => self::pct($completed->filter(fn ($a) => $this->isOnTime($a))->count(), $completed->count()),
                // Los 5 estados con su color de siempre (concluido verde, no completada rojo…)
                'estados' => collect(ActivityStatus::cases())->mapWithKeys(fn ($st) => [$st->value => $g->filter(fn ($a) => $a->status === $st)->count()])->all(),
            ];
        });

        $order = array_flip(array_merge(self::AREAS, ['Gerencia sin área', 'Administración', 'Cargadas por el propio encargado', 'Otros']));

        return $rows->sortBy(fn ($r) => $order[$r->label] ?? 50)->values()->all();
    }

    /**
     * Control de ubicación de las actividades del periodo que exigían acudir a un lugar:
     * verificadas con GPS dentro del radio, validadas a mano, fuera del radio y sin validar.
     */
    public function location(): array
    {
        $acts = Activity::query()
            ->whereBetween('created_at', [$this->from, $this->to])
            ->tap(fn ($q) => $this->scope($q))
            ->where('requiere_ubicacion', true)
            ->where('status', '!=', ActivityStatus::Suspendida->value)
            ->get(['id', 'requiere_ubicacion', 'ubicacion_validada_por', 'ubicacion_at', 'ubicacion_distancia', 'destino_radio']);

        $c = $acts->countBy(fn ($a) => $a->locationStatus());
        $total = $acts->count();

        return [
            'total' => $total,
            'verificada' => $c['verificada'] ?? 0,
            'manual' => $c['manual'] ?? 0,
            'fuera' => $c['fuera'] ?? 0,
            'pendiente' => $c['pendiente'] ?? 0,
            'pct_gps' => self::pct($c['verificada'] ?? 0, $total),
            'pct_comprobadas' => self::pct(($c['verificada'] ?? 0) + ($c['manual'] ?? 0), $total),
        ];
    }

    // ------------------------------------------------------------------
    // Alertas
    // ------------------------------------------------------------------

    /** Situaciones que requieren atención, de mayor a menor gravedad. */
    public function alerts(): array
    {
        $cfg = config('auditoria.alertas');
        $alerts = [];

        // Vencidas hace más de N días (sin importar cuándo se crearon)
        $critical = Activity::query()
            ->overdue()
            ->whereDate('end_date', '<', today()->subDays($cfg['dias_vencida_critica']))
            ->tap(fn ($q) => $this->scope($q))
            ->with(['assignee:id,name', 'type:id,name'])
            ->orderBy('end_date')
            ->get();

        if ($critical->isNotEmpty()) {
            $alerts[] = [
                'level' => 'bad',
                'title' => $critical->count().' '.($critical->count() === 1 ? 'actividad vencida' : 'actividades vencidas')." hace más de {$cfg['dias_vencida_critica']} días",
                'detail' => $critical->take(3)->map(fn ($a) => "{$a->code} {$a->type?->name} ({$a->assignee?->name}, venció {$a->end_date->format('d/m')})")->join(' · '),
                'link' => route('activities.index', ['status' => 'vencida']),
            ];
        }

        // Actividades que exigían acudir a un lugar y no se validó (o se validó fuera)
        $located = Activity::query()
            ->withLocationAlert()
            ->whereNotIn('status', [ActivityStatus::Suspendida->value])
            ->tap(fn ($q) => $this->scope($q))
            ->with('assignee:id,name')
            ->orderBy('end_date')
            ->get();

        if ($located->isNotEmpty()) {
            $alerts[] = [
                'level' => 'bad',
                'title' => $located->count().' '.($located->count() === 1 ? 'actividad requiere ubicación y no está validada' : 'actividades requieren ubicación y no están validadas'),
                'detail' => $located->take(3)->map(fn ($a) => "{$a->code} ({$a->assignee?->name}: ".($a->locationStatus() === 'fuera' ? "fuera, a {$a->ubicacion_distancia} m" : 'sin validar').')')->join(' · '),
                'link' => route('activities.index', ['ubicacion' => 'alerta']),
            ];
        }

        // Actividades marcadas como incompletas en el periodo
        $failed = $this->activities($this->from, $this->to)
            ->filter(fn ($a) => $a->status === ActivityStatus::NoCompletada);

        if ($failed->isNotEmpty()) {
            $names = User::whereIn('id', $failed->pluck('assigned_to')->unique())->pluck('name', 'id');
            $byPerson = $failed->groupBy('assigned_to')->map->count()->sortDesc();

            $alerts[] = [
                'level' => 'bad',
                'title' => $failed->count().' '.($failed->count() === 1 ? 'actividad marcada' : 'actividades marcadas').' como No completada en el periodo',
                'detail' => $byPerson->take(5)->map(fn ($n, $id) => ($names[$id] ?? '—')." ({$n})")->join(', '),
                'link' => route('activities.index', ['status' => 'no_completada']),
            ];
        }

        // Actividades vencidas que nadie ha marcado
        $unmarked = Activity::query()
            ->overdue()
            ->whereDate('end_date', '<', today()->subDays(1))
            ->tap(fn ($q) => $this->scope($q))
            ->count();

        if ($unmarked > 0) {
            $alerts[] = [
                'level' => 'info',
                'title' => "{$unmarked} ".($unmarked === 1 ? 'actividad vencida' : 'actividades vencidas').' sin revisar',
                'detail' => 'Revisa si se completaron y márcalas con ✓ o ✗.',
                'link' => route('activities.index', ['status' => 'sin_marcar']),
            ];
        }

        // Personal sin actividades en el periodo
        $idle = $this->people()->filter(fn ($p) => $p->assigned === 0);
        if ($idle->isNotEmpty()) {
            $alerts[] = [
                'level' => 'info',
                'title' => $idle->count().' '.($idle->count() === 1 ? 'encargado sin' : 'encargados sin').' actividades en el periodo',
                'detail' => $idle->take(5)->map(fn ($p) => $p->user->name)->join(', '),
                'link' => null,
            ];
        }

        return $alerts;
    }

    // ------------------------------------------------------------------
    // Definiciones
    // ------------------------------------------------------------------

    /** Fórmula, meta y fuente de cada indicador, para auditoría. */
    public static function definitions(): array
    {
        $m = config('auditoria.metas');

        return [
            ['Cumplimiento', 'Marcadas «Concluido» por un superior ÷ (asignadas − suspendidas) × 100', "≥ {$m['cumplimiento']}%", 'Actividades creadas en el periodo'],
            ['Entrega a tiempo', 'Concluidas en o antes de su fecha de fin ÷ concluidas × 100', "≥ {$m['a_tiempo']}%", 'Actividades concluidas del periodo'],
            ['No completadas', 'Actividades que un superior marcó en rojo (✗) en el periodo', 'Lo más bajo posible', 'Marcado de actividades'],
            ['Vencidas', 'No iniciadas o en proceso con fecha de fin anterior a hoy', 'Lo más bajo posible', 'Actividades creadas en el periodo'],
            ['Duración promedio', 'Promedio de días desde la fecha de inicio hasta el día en que se marcó «Concluido» (contando ambos)', 'Igual o menor que lo planeado', 'Actividades concluidas del periodo'],
            ['Calidad de la evidencia', 'Concluidas con fotografía o texto ÷ concluidas × 100', 'Lo más alto posible', 'Evidencia cargada en cada actividad concluida'],
            ['Velocidad de revisión', 'Promedio de días entre la fecha de fin y la marca verde o roja (0 si se revisó a tiempo)', 'Tendencia a la baja', 'Actividades revisadas del periodo'],
            ['Duración', 'Días de la fecha de inicio al día en que se marcó «Concluido», contando ambos (mismo día = 1)', 'Cerca de lo planeado', 'Actividades concluidas en el periodo'],
            ['Antes / en la fecha / después', 'Día en que se terminó comparado con la fecha de fin de cada actividad', 'Antes o en la fecha', 'Actividades concluidas con fecha de fin'],
            ['Comparativo', 'Valor actual − valor del periodo anterior de la misma duración', 'Positivo', 'Mismo cálculo en el periodo previo'],
            ['Cumplimiento por área', 'Lo que encomendaron los gerentes de cada área (o se cargó el propio encargado), con el mismo cálculo de cumplimiento y entrega a tiempo', "≥ {$m['cumplimiento']}%", 'Actividades creadas en el periodo'],
            ['Ubicación comprobada', '(Verificadas con GPS dentro del radio + validadas a mano) ÷ actividades que exigían acudir × 100', 'Lo más alto posible', 'Actividades con ubicación del periodo'],
            ['Encargados sin movimiento', 'Días desde la última actividad que cargó, concluyó o le cargaron, o desde su último checado', 'Menos de '.config('auditoria.alertas.dias_sin_movimiento', 5).' días', 'Actividades y asistencia'],
            ['Comunidades olvidadas', 'Comunidades activas sin actividades ni registros del Cuaderno en '.config('auditoria.alertas.dias_comunidad_olvidada', 15).' días', 'Ninguna', 'Actividades y Cuaderno Maestro'],
        ];
    }
}
