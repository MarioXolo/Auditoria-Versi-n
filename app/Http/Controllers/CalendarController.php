<?php

namespace App\Http\Controllers;

use App\Enums\ActivityStatus;
use App\Models\Activity;
use App\Models\ActivityType;
use App\Models\Community;
use App\Models\Team;
use App\Models\User;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\View\View;

/**
 * Calendario mensual de actividades. Cada usuario ve solo lo que su rol le permite
 * (mismo alcance que el listado). Se imprime o guarda en PDF desde el navegador.
 */
class CalendarController extends Controller
{
    public function __invoke(Request $request): View
    {
        $user = $request->user();

        $month = $this->month((string) $request->input('mes'));
        $from = $month->startOfMonth();
        $to = $month->endOfMonth();
        // «inicio»: cada actividad aparece el día que empieza; «duracion»: todos los días que dura
        $mode = $request->input('modo') === 'duracion' ? 'duracion' : 'inicio';

        $activities = Activity::query()
            ->visibleTo($user)
            ->with(['type:id,name', 'community:id,name', 'assignee:id,name', 'team:id,name'])
            ->when($mode === 'inicio',
                fn ($q) => $q->whereDate('start_date', '>=', $from)->whereDate('start_date', '<=', $to),
                fn ($q) => $q->whereDate('start_date', '<=', $to)->whereDate('end_date', '>=', $from))
            ->when($request->filled('team_id'), fn ($q) => $q->where('team_id', $request->integer('team_id')))
            ->when($request->filled('activity_type_id'), fn ($q) => $q->where('activity_type_id', $request->integer('activity_type_id')))
            ->when($request->filled('community_id'), fn ($q) => $q->where('community_id', $request->integer('community_id')))
            ->when($request->filled('assigned_to'), fn ($q) => $q->where('assigned_to', $request->integer('assigned_to')))
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->input('status')))
            ->orderBy('start_date')
            ->orderBy('folio_group')
            ->orderBy('folio_seq')
            ->get();

        $days = $this->days($activities, $from, $to, $mode);

        // Semanas de lunes a domingo que cubren el mes
        $weeks = collect();
        for ($d = $from->startOfWeek(CarbonInterface::MONDAY); $d->lte($to->endOfWeek(CarbonInterface::SUNDAY)); $d = $d->addWeek()) {
            $weeks->push(collect(range(0, 6))->map(fn ($i) => $d->addDays($i)));
        }

        $summary = collect(ActivityStatus::cases())->map(fn ($s) => [
            'status' => $s,
            'count' => $activities->where('status', $s)->count(),
        ]);
        $total = $activities->count();
        $concluded = $activities->where('status', ActivityStatus::Concluido)->count();
        $base = $total - $activities->where('status', ActivityStatus::Suspendida)->count();

        return view('calendar.index', [
            'month' => $month,
            'mode' => $mode,
            'weeks' => $weeks,
            'days' => $days,
            'activities' => $activities,
            'summary' => $summary,
            'total' => $total,
            'cumplimiento' => $base > 0 ? round($concluded / $base * 100, 1) : null,
            'overdue' => $activities->filter->isOverdue()->count(),
            'filters' => $this->filterOptions($user),
            'appliedFilters' => $this->appliedFilters($request),
        ]);
    }

    private function month(string $value): CarbonImmutable
    {
        try {
            return preg_match('/^\d{4}-\d{2}$/', $value)
                ? CarbonImmutable::createFromFormat('!Y-m', $value)
                : CarbonImmutable::today()->startOfMonth();
        } catch (\Throwable) {
            return CarbonImmutable::today()->startOfMonth();
        }
    }

    /** @return array<string, Collection<int, Activity>> actividades por día (Y-m-d) */
    private function days(Collection $activities, CarbonImmutable $from, CarbonImmutable $to, string $mode): array
    {
        $days = [];

        foreach ($activities as $activity) {
            if ($mode === 'inicio') {
                $days[$activity->start_date->toDateString()][] = $activity;

                continue;
            }

            $day = CarbonImmutable::parse($activity->start_date)->max($from);
            $last = CarbonImmutable::parse($activity->end_date)->min($to);
            for (; $day->lte($last); $day = $day->addDay()) {
                $days[$day->toDateString()][] = $activity;
            }
        }

        return array_map(fn ($list) => collect($list), $days);
    }

    private function filterOptions(User $user): array
    {
        $people = match (true) {
            $user->seesEverything() => User::orderBy('name')->get(['id', 'name']),
            $user->isEncargado() => User::whereIn('id', [...$user->teamMemberIds(), $user->id])->orderBy('name')->get(['id', 'name']),
            default => collect(),
        };

        return [
            'teams' => $user->seesEverything() ? Team::orderBy('number')->get(['id', 'name', 'number']) : collect(),
            'types' => ActivityType::orderBy('name')->get(['id', 'name']),
            'communities' => Community::orderBy('name')->get(['id', 'name']),
            'people' => $people,
            'statuses' => ActivityStatus::options(),
        ];
    }

    /** Texto de los filtros aplicados, para el encabezado impreso. */
    private function appliedFilters(Request $request): array
    {
        $applied = [];
        if ($request->filled('team_id')) {
            $applied['Equipo'] = Team::find($request->integer('team_id'))?->name;
        }
        if ($request->filled('activity_type_id')) {
            $applied['Tipo'] = ActivityType::find($request->integer('activity_type_id'))?->name;
        }
        if ($request->filled('community_id')) {
            $applied['Comunidad'] = Community::find($request->integer('community_id'))?->name;
        }
        if ($request->filled('assigned_to')) {
            $applied['Persona'] = User::find($request->integer('assigned_to'))?->name;
        }
        if ($request->filled('status')) {
            $applied['Estado'] = ActivityStatus::tryFrom((string) $request->input('status'))?->label();
        }

        return array_filter($applied);
    }
}
