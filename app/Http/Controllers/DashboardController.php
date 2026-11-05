<?php

namespace App\Http\Controllers;

use App\Enums\ActivityStatus;
use App\Enums\Role;
use App\Models\Activity;
use App\Models\Attendance;
use App\Models\Team;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/**
 * Dashboard de auditoría. Solo administradores y gerentes (ver routes/web.php).
 */
class DashboardController extends Controller
{
    private const PERIODS = [
        '7' => 'Últimos 7 días',
        '30' => 'Últimos 30 días',
        '90' => 'Últimos 90 días',
        'all' => 'Todo',
    ];

    public function __invoke(Request $request): View
    {
        $period = (string) $request->input('period', '30');
        $period = array_key_exists($period, self::PERIODS) ? $period : '30';
        $teamId = $request->integer('team_id') ?: null;
        $from = $period === 'all' ? null : now()->subDays((int) $period)->startOfDay();

        // Actividades del periodo (por fecha de creación)
        $base = Activity::query()
            ->when($from, fn ($q) => $q->where('created_at', '>=', $from))
            ->when($teamId, fn ($q) => $q->where('team_id', $teamId));

        $byStatus = (clone $base)
            ->select('status', DB::raw('COUNT(*) as total'))
            ->groupBy('status')
            ->pluck('total', 'status');

        $total = (int) $byStatus->sum();
        $completed = (int) ($byStatus[ActivityStatus::Completada->value] ?? 0);
        $cancelled = (int) ($byStatus[ActivityStatus::Cancelada->value] ?? 0);
        $open = (int) ($byStatus[ActivityStatus::Pendiente->value] ?? 0) + (int) ($byStatus[ActivityStatus::EnProgreso->value] ?? 0);
        $overdue = (clone $base)->overdue()->count();
        $effective = $total - $cancelled;
        $completionRate = $effective > 0 ? round($completed / $effective * 100) : 0;

        // Asistencia de hoy
        $staff = User::active()
            ->whereIn('role', [Role::Encargado->value, Role::Empleado->value])
            ->when($teamId, fn ($q) => $q->where('team_id', $teamId))
            ->orderBy('name')
            ->get(['id', 'name', 'role', 'team_id']);

        $presentIds = Attendance::whereDate('work_date', today())
            ->whereIn('user_id', $staff->pluck('id'))
            ->pluck('user_id')
            ->all();

        $absent = $staff->whereNotIn('id', $presentIds)->values();

        // Ranking por persona
        $ranking = $this->ranking($base);

        // Desempeño de encargados: lo que asignan y cómo va su equipo
        $leaders = $this->leaders($from, $teamId);

        // Actividades completadas por día (últimos 14 días)
        $trend = $this->completionTrend($teamId);

        $recent = Activity::with(['assignee:id,name', 'creator:id,name'])
            ->when($teamId, fn ($q) => $q->where('team_id', $teamId))
            ->latest('updated_at')
            ->limit(8)
            ->get();

        return view('dashboard', [
            'periods' => self::PERIODS,
            'period' => $period,
            'teams' => Team::orderBy('name')->get(['id', 'name']),
            'teamId' => $teamId,
            'stats' => compact('total', 'completed', 'open', 'overdue', 'completionRate'),
            'byStatus' => $byStatus,
            'staffCount' => $staff->count(),
            'presentCount' => count($presentIds),
            'absent' => $absent,
            'ranking' => $ranking,
            'leaders' => $leaders,
            'trend' => $trend,
            'recent' => $recent,
        ]);
    }

    private function ranking($base)
    {
        $done = ActivityStatus::Completada->value;
        $openStatuses = "'".ActivityStatus::Pendiente->value."','".ActivityStatus::EnProgreso->value."'";
        $today = today()->toDateString();

        $rows = (clone $base)
            ->select('assigned_to')
            ->selectRaw('COUNT(*) as assigned')
            ->selectRaw('SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) as completed', [$done])
            ->selectRaw("SUM(CASE WHEN status IN ($openStatuses) THEN 1 ELSE 0 END) as open")
            ->selectRaw("SUM(CASE WHEN status IN ($openStatuses) AND due_date < ? THEN 1 ELSE 0 END) as overdue", [$today])
            ->groupBy('assigned_to')
            ->orderByDesc('assigned')
            ->get();

        $users = User::whereIn('id', $rows->pluck('assigned_to'))->with('team:id,name')->get()->keyBy('id');

        return $rows->map(function ($row) use ($users) {
            $assigned = (int) $row->assigned;
            $completed = (int) $row->completed;

            return (object) [
                'user' => $users->get($row->assigned_to),
                'assigned' => $assigned,
                'completed' => $completed,
                'open' => (int) $row->open,
                'overdue' => (int) $row->overdue,
                'rate' => $assigned > 0 ? round($completed / $assigned * 100) : 0,
            ];
        })->filter(fn ($row) => $row->user !== null)->values();
    }

    private function leaders($from, ?int $teamId)
    {
        $done = ActivityStatus::Completada->value;

        return User::query()
            ->where('role', Role::Encargado->value)
            ->with('ledTeams:id,name,leader_id')
            ->withCount([
                'createdActivities as created_total' => fn ($q) => $q
                    ->when($from, fn ($q) => $q->where('created_at', '>=', $from))
                    ->when($teamId, fn ($q) => $q->where('team_id', $teamId)),
                'createdActivities as created_done' => fn ($q) => $q
                    ->where('status', $done)
                    ->when($from, fn ($q) => $q->where('created_at', '>=', $from))
                    ->when($teamId, fn ($q) => $q->where('team_id', $teamId)),
                'assignedActivities as own_total' => fn ($q) => $q
                    ->when($from, fn ($q) => $q->where('created_at', '>=', $from)),
                'assignedActivities as own_done' => fn ($q) => $q
                    ->where('status', $done)
                    ->when($from, fn ($q) => $q->where('created_at', '>=', $from)),
            ])
            ->when($teamId, fn ($q) => $q->whereHas('ledTeams', fn ($t) => $t->where('id', $teamId)))
            ->orderByDesc('created_total')
            ->get();
    }

    private function completionTrend(?int $teamId): array
    {
        $start = today()->subDays(13);

        $counts = Activity::query()
            ->where('status', ActivityStatus::Completada->value)
            ->where('completed_at', '>=', $start)
            ->when($teamId, fn ($q) => $q->where('team_id', $teamId))
            ->get(['completed_at'])
            ->groupBy(fn (Activity $a) => $a->completed_at->toDateString())
            ->map->count();

        $days = [];
        for ($i = 0; $i < 14; $i++) {
            $day = $start->copy()->addDays($i);
            $days[] = [
                'label' => $day->translatedFormat('D j'),
                'count' => (int) ($counts[$day->toDateString()] ?? 0),
            ];
        }

        return $days;
    }
}
