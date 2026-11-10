<?php

namespace App\Services;

use App\Enums\ActivityStatus;
use App\Enums\Priority;
use App\Enums\Role;
use App\Enums\ValidationStatus;
use App\Models\Activity;
use App\Models\Attendance;
use App\Models\Team;
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

    public function __construct(public string $period = '30', public ?int $teamId = null)
    {
        $this->period = array_key_exists($period, self::PERIODS) ? $period : '30';
        $this->to = now();

        if ($this->period === 'all') {
            $first = Activity::min('created_at') ?? Attendance::min('work_date');
            $this->from = $first ? Carbon::parse($first)->startOfDay() : today()->subDays(29);
        } else {
            $days = (int) $this->period;
            $this->from = today()->subDays($days - 1);
            $this->prevTo = $this->from->copy()->subSecond();
            $this->prevFrom = $this->from->copy()->subDays($days);
        }

        $this->staff = User::active()
            ->whereIn('role', [Role::Encargado->value, Role::Empleado->value])
            ->when($this->teamId, fn ($q) => $q->where('team_id', $this->teamId))
            ->with('team:id,name')
            ->orderBy('name')
            ->get();
    }

    // ------------------------------------------------------------------
    // Datos base
    // ------------------------------------------------------------------

    /** Actividades creadas dentro del rango. */
    private function activities(Carbon $from, Carbon $to): Collection
    {
        $key = 'act'.$from->timestamp.'-'.$to->timestamp;

        return $this->cache[$key] ??= Activity::query()
            ->whereBetween('created_at', [$from, $to])
            ->when($this->teamId, fn ($q) => $q->where('team_id', $this->teamId))
            ->get(['id', 'title', 'assigned_to', 'created_by', 'team_id', 'priority', 'status', 'validation_status', 'due_date', 'completed_at', 'created_at', 'updated_at']);
    }

    /** Registros de asistencia del personal dentro del rango. */
    private function attendances(Carbon $from, Carbon $to): Collection
    {
        $key = 'att'.$from->timestamp.'-'.$to->timestamp;

        return $this->cache[$key] ??= Attendance::query()
            ->whereDate('work_date', '>=', $from->toDateString())
            ->whereDate('work_date', '<=', $to->toDateString())
            ->whereIn('user_id', $this->staff->pluck('id'))
            ->get(['id', 'user_id', 'work_date', 'check_in_at', 'check_out_at']);
    }

    /** Días laborales entre dos fechas (sin pasar de hoy). */
    public function workdays(Carbon $from, Carbon $to): Collection
    {
        $days = collect();
        $end = $to->copy()->min(today())->startOfDay();
        $laborales = config('auditoria.dias_laborales');

        // Hoy solo cuenta cuando ya pasó la hora de entrada más la tolerancia
        [$h, $m] = array_map('intval', explode(':', config('auditoria.hora_entrada')));
        if ($end->isToday() && now()->lt(today()->setTime($h, $m)->addMinutes(config('auditoria.tolerancia_minutos')))) {
            $end->subDay();
        }

        for ($d = $from->copy()->startOfDay(); $d->lte($end); $d->addDay()) {
            if (in_array($d->isoWeekday(), $laborales, true)) {
                $days->push($d->copy());
            }
        }

        return $days;
    }

    private function isLate(Attendance $a): bool
    {
        if (! $a->check_in_at) {
            return false;
        }

        [$h, $m] = array_map('intval', explode(':', config('auditoria.hora_entrada')));
        $limit = $a->check_in_at->copy()->setTime($h, $m)->addMinutes(config('auditoria.tolerancia_minutos'));

        return $a->check_in_at->gt($limit);
    }

    private function isOverdue(Activity $a): bool
    {
        return $a->due_date !== null
            && $a->due_date->lt(today())
            && in_array($a->status, [ActivityStatus::Pendiente, ActivityStatus::EnProgreso], true);
    }

    private function isOnTime(Activity $a): bool
    {
        return $a->status === ActivityStatus::Completada
            && ($a->due_date === null || ($a->completed_at && $a->completed_at->copy()->startOfDay()->lte($a->due_date)));
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
        $completed = $acts->filter(fn ($a) => $a->status === ActivityStatus::Completada);
        $cancelled = $acts->filter(fn ($a) => $a->status === ActivityStatus::Cancelada)->count();
        $effective = $acts->count() - $cancelled;

        $closeDays = $completed
            ->filter(fn ($a) => $a->completed_at)
            ->map(fn ($a) => $a->created_at->diffInMinutes($a->completed_at) / 1440);

        return [
            'total' => $acts->count(),
            'completed' => $completed->count(),
            'open' => $acts->filter(fn ($a) => in_array($a->status, [ActivityStatus::Pendiente, ActivityStatus::EnProgreso], true))->count(),
            'cancelled' => $cancelled,
            'overdue' => $acts->filter(fn ($a) => $this->isOverdue($a))->count(),
            'cumplimiento' => self::pct($completed->count(), $effective),
            'a_tiempo' => self::pct($completed->filter(fn ($a) => $this->isOnTime($a))->count(), $completed->count()),
            'dias_cierre' => $closeDays->isEmpty() ? null : round($closeDays->avg(), 1),
            'por_validar' => $completed->filter(fn ($a) => $a->validation_status === ValidationStatus::Pendiente)->count(),
            'validadas' => $completed->filter(fn ($a) => $a->validation_status === ValidationStatus::Validada)->count(),
            'validacion' => self::pct($completed->filter(fn ($a) => $a->validation_status === ValidationStatus::Validada)->count(), $completed->count()),
        ];
    }

    /** KPI de asistencia para un rango. */
    public function attendanceKpis(Carbon $from, Carbon $to): array
    {
        $workdays = $this->workdays($from, $to);
        $workdaySet = $workdays->map->toDateString()->flip();
        $records = $this->attendances($from, $to)
            ->filter(fn ($r) => $workdaySet->has($r->work_date->toDateString()));

        $expected = $workdays->count() * $this->staff->count();
        $present = $records->count();
        $late = $records->filter(fn ($r) => $this->isLate($r))->count();

        $hours = $records
            ->filter(fn ($r) => $r->check_in_at && $r->check_out_at)
            ->map(fn ($r) => $r->check_in_at->diffInMinutes($r->check_out_at) / 60);

        return [
            'expected' => $expected,
            'present' => $present,
            'absences' => max(0, $expected - $present),
            'late' => $late,
            'asistencia' => self::pct($present, $expected),
            'puntualidad' => self::pct($present - $late, $present),
            'horas' => $hours->isEmpty() ? null : round($hours->avg(), 1),
            'workdays' => $workdays->count(),
        ];
    }

    /** KPI del periodo actual con su comparativo contra el periodo anterior. */
    public function summary(): array
    {
        $now = array_merge($this->activityKpis($this->from, $this->to), $this->attendanceKpis($this->from, $this->to));
        $prev = null;

        if ($this->prevFrom) {
            $prev = array_merge(
                $this->activityKpis($this->prevFrom, $this->prevTo),
                $this->attendanceKpis($this->prevFrom, $this->prevTo),
            );
        }

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

    /** Actividades creadas vs completadas por día (o por semana en rangos largos). */
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
            ->where('status', ActivityStatus::Completada->value)
            ->whereBetween('completed_at', [$this->from, $this->to])
            ->when($this->teamId, fn ($q) => $q->where('team_id', $this->teamId))
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

    /** Porcentaje de asistencia y puntualidad por día laboral (máximo 30 días). */
    public function attendanceTrend(): array
    {
        $from = $this->from->copy()->max(today()->subDays(29));
        $records = $this->attendances($from, $this->to)->groupBy(fn ($r) => $r->work_date->toDateString());
        $staff = max(1, $this->staff->count());

        return $this->workdays($from, $this->to)->map(function (Carbon $day) use ($records, $staff) {
            $rows = $records->get($day->toDateString(), collect());
            $late = $rows->filter(fn ($r) => $this->isLate($r))->count();

            return [
                'label' => $day->translatedFormat('D j'),
                'asistencia' => round($rows->count() / $staff * 100, 1),
                'puntualidad' => $rows->count() ? round(($rows->count() - $late) / $rows->count() * 100, 1) : null,
            ];
        })->values()->all();
    }

    public function byStatus(): array
    {
        $acts = $this->activities($this->from, $this->to);

        return collect(ActivityStatus::cases())->map(fn ($s) => [
            'label' => $s->label(),
            'value' => $acts->filter(fn ($a) => $a->status === $s)->count(),
        ])->all();
    }

    public function byPriority(): array
    {
        $acts = $this->activities($this->from, $this->to);

        return collect(Priority::cases())->map(fn ($p) => [
            'label' => $p->label(),
            'total' => $acts->filter(fn ($a) => $a->priority === $p)->count(),
            'overdue' => $acts->filter(fn ($a) => $a->priority === $p && $this->isOverdue($a))->count(),
        ])->all();
    }

    // ------------------------------------------------------------------
    // Tablas
    // ------------------------------------------------------------------

    /** Indicadores por persona (encargados y empleados). */
    public function people(): Collection
    {
        return $this->cache['people'] ??= $this->buildPeople();
    }

    private function buildPeople(): Collection
    {
        $acts = $this->activities($this->from, $this->to)->groupBy('assigned_to');
        $att = $this->attendanceKpiRowsByUser();

        return $this->staff->map(function (User $u) use ($acts, $att) {
            $mine = $acts->get($u->id, collect());
            $completed = $mine->filter(fn ($a) => $a->status === ActivityStatus::Completada);
            $effective = $mine->filter(fn ($a) => $a->status !== ActivityStatus::Cancelada)->count();
            $close = $completed->filter(fn ($a) => $a->completed_at)->map(fn ($a) => $a->created_at->diffInMinutes($a->completed_at) / 1440);
            $a = $att[$u->id];

            return (object) [
                'user' => $u,
                'assigned' => $mine->count(),
                'completed' => $completed->count(),
                'open' => $mine->filter(fn ($x) => in_array($x->status, [ActivityStatus::Pendiente, ActivityStatus::EnProgreso], true))->count(),
                'overdue' => $mine->filter(fn ($x) => $this->isOverdue($x))->count(),
                'cumplimiento' => self::pct($completed->count(), $effective),
                'a_tiempo' => self::pct($completed->filter(fn ($x) => $this->isOnTime($x))->count(), $completed->count()),
                'dias_cierre' => $close->isEmpty() ? null : round($close->avg(), 1),
                'asistencia' => $a['asistencia'],
                'present' => $a['present'],
                'absences' => $a['absences'],
                'late' => $a['late'],
            ];
        })->sortByDesc('assigned')->values();
    }

    private function attendanceKpiRowsByUser(): array
    {
        return $this->cache['attRows'] ??= $this->buildAttendanceRows();
    }

    private function buildAttendanceRows(): array
    {
        $workdays = $this->workdays($this->from, $this->to);
        $set = $workdays->map->toDateString()->flip();
        $byUser = $this->attendances($this->from, $this->to)
            ->filter(fn ($r) => $set->has($r->work_date->toDateString()))
            ->groupBy('user_id');

        $rows = [];
        foreach ($this->staff as $u) {
            $recs = $byUser->get($u->id, collect());
            $rows[$u->id] = [
                'present' => $recs->count(),
                'absences' => max(0, $workdays->count() - $recs->count()),
                'late' => $recs->filter(fn ($r) => $this->isLate($r))->count(),
                'asistencia' => self::pct($recs->count(), $workdays->count()),
            ];
        }

        return $rows;
    }

    /** Auditoría de encargados: lo que asignaron y cómo va su equipo. */
    public function leaders(): Collection
    {
        return $this->cache['leaders'] ??= $this->buildLeaders();
    }

    private function buildLeaders(): Collection
    {
        $acts = $this->activities($this->from, $this->to)->groupBy('created_by');
        $att = $this->attendanceKpiRowsByUser();

        return User::query()
            ->where('role', Role::Encargado->value)
            ->with(['ledTeams' => fn ($q) => $q->withCount('members')])
            ->when($this->teamId, fn ($q) => $q->whereHas('ledTeams', fn ($t) => $t->where('id', $this->teamId)))
            ->orderBy('name')
            ->get()
            ->map(function (User $leader) use ($acts, $att) {
                $assigned = $acts->get($leader->id, collect());
                $completed = $assigned->filter(fn ($a) => $a->status === ActivityStatus::Completada);
                $effective = $assigned->filter(fn ($a) => $a->status !== ActivityStatus::Cancelada)->count();
                $memberIds = User::whereIn('team_id', $leader->ledTeams->pluck('id'))->pluck('id');
                $teamAtt = collect($att)->only($memberIds->all());
                $present = $teamAtt->sum('present');
                $expected = $present + $teamAtt->sum('absences');

                return (object) [
                    'user' => $leader,
                    'teams' => $leader->ledTeams->pluck('name')->join(', '),
                    'members' => $leader->ledTeams->sum('members_count'),
                    'assigned' => $assigned->count(),
                    'completed' => $completed->count(),
                    'overdue' => $assigned->filter(fn ($a) => $this->isOverdue($a))->count(),
                    'cumplimiento' => self::pct($completed->count(), $effective),
                    'a_tiempo' => self::pct($completed->filter(fn ($a) => $this->isOnTime($a))->count(), $completed->count()),
                    'asistencia_equipo' => self::pct($present, $expected),
                ];
            })
            ->sortByDesc('assigned')
            ->values();
    }

    /** Comparativo entre equipos. */
    public function teams(): Collection
    {
        $acts = $this->activities($this->from, $this->to)->groupBy('team_id');
        $att = $this->attendanceKpiRowsByUser();

        return Team::query()
            ->when($this->teamId, fn ($q) => $q->where('id', $this->teamId))
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(function (Team $team) use ($acts, $att) {
                $mine = $acts->get($team->id, collect());
                $completed = $mine->filter(fn ($a) => $a->status === ActivityStatus::Completada);
                $effective = $mine->filter(fn ($a) => $a->status !== ActivityStatus::Cancelada)->count();
                $members = $this->staff->where('team_id', $team->id)->pluck('id');
                $teamAtt = collect($att)->only($members->all());
                $present = $teamAtt->sum('present');

                return (object) [
                    'name' => $team->name,
                    'assigned' => $mine->count(),
                    'cumplimiento' => self::pct($completed->count(), $effective) ?? 0,
                    'a_tiempo' => self::pct($completed->filter(fn ($a) => $this->isOnTime($a))->count(), $completed->count()) ?? 0,
                    'asistencia' => self::pct($present, $present + $teamAtt->sum('absences')) ?? 0,
                ];
            });
    }

    /** Personal sin registro de entrada hoy (si hoy es día laboral). */
    public function absentToday(): Collection
    {
        if (! in_array(today()->isoWeekday(), config('auditoria.dias_laborales'), true)) {
            return collect();
        }

        $present = Attendance::whereDate('work_date', today()->toDateString())->pluck('user_id')->all();

        return $this->staff->whereNotIn('id', $present)->values();
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
            ->whereDate('due_date', '<', today()->subDays($cfg['dias_vencida_critica']))
            ->when($this->teamId, fn ($q) => $q->where('team_id', $this->teamId))
            ->with('assignee:id,name')
            ->orderBy('due_date')
            ->get();

        if ($critical->isNotEmpty()) {
            $alerts[] = [
                'level' => 'bad',
                'title' => $critical->count().' '.($critical->count() === 1 ? 'actividad vencida' : 'actividades vencidas')." hace más de {$cfg['dias_vencida_critica']} días",
                'detail' => $critical->take(3)->map(fn ($a) => "{$a->title} ({$a->assignee?->name}, venció {$a->due_date->format('d/m')})")->join(' · '),
                'link' => route('activities.index', ['status' => 'vencida']),
            ];
        }

        // Completadas que esperan validación (sin importar cuándo se crearon)
        $pending = Activity::query()
            ->pendingValidation()
            ->when($this->teamId, fn ($q) => $q->where('team_id', $this->teamId))
            ->with('creator:id,name')
            ->get(['id', 'created_by', 'completed_at']);

        if ($pending->isNotEmpty()) {
            $old = $pending->filter(fn ($a) => $a->completed_at && $a->completed_at->lt(now()->subDays(2)))->count();
            $byCreator = $pending->groupBy(fn ($a) => $a->creator?->name ?? 'Sin asignador')->map->count()->sortDesc();

            $alerts[] = [
                'level' => $old > 0 ? 'warn' : 'info',
                'title' => $pending->count().' '.($pending->count() === 1 ? 'actividad completada' : 'actividades completadas').' por validar'.($old > 0 ? " ({$old} con más de 2 días)" : ''),
                'detail' => 'Pendientes de: '.$byCreator->take(4)->map(fn ($n, $name) => "{$name} ({$n})")->join(', '),
                'link' => route('activities.index', ['status' => 'por_validar']),
            ];
        }

        // Faltas recientes
        $recentDays = $this->workdays(today()->subDays(10), today())->take(-5);
        if ($recentDays->isNotEmpty()) {
            $recentSet = $recentDays->map->toDateString()->flip();
            $records = Attendance::query()
                ->whereDate('work_date', '>=', $recentDays->first()->toDateString())
                ->whereIn('user_id', $this->staff->pluck('id'))
                ->get(['user_id', 'work_date'])
                ->filter(fn ($r) => $recentSet->has($r->work_date->toDateString()))
                ->countBy('user_id');

            $absentees = $this->staff->filter(fn ($u) => ($recentDays->count() - ($records[$u->id] ?? 0)) >= $cfg['faltas_recientes']);

            if ($absentees->isNotEmpty()) {
                $alerts[] = [
                    'level' => 'warn',
                    'title' => $absentees->count().' '.($absentees->count() === 1 ? 'persona con' : 'personas con')." {$cfg['faltas_recientes']} o más faltas en los últimos {$recentDays->count()} días laborales",
                    'detail' => $absentees->take(5)->pluck('name')->join(', '),
                    'link' => route('attendance.index'),
                ];
            }
        }

        // Retardos frecuentes en el periodo
        $late = $this->people()->filter(fn ($p) => $p->late >= $cfg['retardos_periodo']);
        if ($late->isNotEmpty()) {
            $alerts[] = [
                'level' => 'warn',
                'title' => $late->count().' '.($late->count() === 1 ? 'persona con' : 'personas con')." {$cfg['retardos_periodo']} o más retardos en el periodo",
                'detail' => $late->take(5)->map(fn ($p) => "{$p->user->name} ({$p->late})")->join(', '),
                'link' => route('attendance.index'),
            ];
        }

        // Encargados con bajo cumplimiento de lo que asignaron
        $weak = $this->leaders()->filter(fn ($l) => $l->assigned >= 3 && $l->cumplimiento !== null && $l->cumplimiento < $cfg['cumplimiento_encargado']);
        if ($weak->isNotEmpty()) {
            $alerts[] = [
                'level' => 'warn',
                'title' => "Encargados con menos de {$cfg['cumplimiento_encargado']}% de cumplimiento en lo que asignaron",
                'detail' => $weak->map(fn ($l) => "{$l->user->name} ({$l->cumplimiento}%)")->join(', '),
                'link' => null,
            ];
        }

        // Personal sin actividades en el periodo
        $idle = $this->people()->filter(fn ($p) => $p->assigned === 0 && $p->user->role === Role::Empleado);
        if ($idle->isNotEmpty()) {
            $alerts[] = [
                'level' => 'info',
                'title' => $idle->count().' '.($idle->count() === 1 ? 'empleado sin' : 'empleados sin').' actividades asignadas en el periodo',
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
        $hora = config('auditoria.hora_entrada');
        $tol = config('auditoria.tolerancia_minutos');

        return [
            ['Cumplimiento', 'Completadas ÷ (asignadas − canceladas) × 100', "≥ {$m['cumplimiento']}%", 'Actividades creadas en el periodo'],
            ['Entrega a tiempo', 'Completadas en o antes de su fecha límite ÷ completadas × 100', "≥ {$m['a_tiempo']}%", 'Actividades completadas del periodo'],
            ['Asistencia', "Entradas registradas ÷ (personal activo × días laborales) × 100. Hoy cuenta a partir de las {$hora} + {$tol} min", "≥ {$m['asistencia']}%", 'Registros de asistencia en días laborales'],
            ['Puntualidad', "Entradas hasta las {$hora} + {$tol} min ÷ entradas registradas × 100", "≥ {$m['puntualidad']}%", 'Hora de entrada de cada registro'],
            ['Validación', 'Completadas validadas por quien asignó, un gerente o el administrador ÷ completadas × 100', '100%', 'Actividades completadas del periodo'],
            ['Vencidas', 'Pendientes o en progreso con fecha límite anterior a hoy', 'Lo más bajo posible', 'Actividades creadas en el periodo'],
            ['Tiempo de cierre', 'Promedio de días entre la creación y el completado', 'Tendencia a la baja', 'Actividades completadas del periodo'],
            ['Horas por jornada', 'Promedio de horas entre entrada y salida', 'Referencia', 'Registros con entrada y salida'],
            ['Comparativo', 'Valor actual − valor del periodo anterior de la misma duración', 'Positivo', 'Mismo cálculo en el periodo previo'],
        ];
    }
}
