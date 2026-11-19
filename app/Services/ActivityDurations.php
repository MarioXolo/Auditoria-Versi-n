<?php

namespace App\Services;

use App\Enums\ActivityStatus;
use App\Models\Activity;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Cuánto duraron las actividades concluidas (de su fecha de inicio al día en que se
 * marcaron «Concluido») y si terminaron antes, en la fecha o después de su fecha de fin.
 * Lo usan el dashboard de auditoría y el dashboard del encargado.
 */
class ActivityDurations
{
    /** Actividades concluidas dentro del rango (por el día en que se terminaron). */
    public static function query(Carbon $from, Carbon $to): Builder
    {
        return Activity::query()
            ->where('status', ActivityStatus::Concluido->value)
            ->whereRaw('COALESCE(completed_at, marked_at) BETWEEN ? AND ?', [$from, $to]);
    }

    /** @param  Collection<int, Activity>  $activities  actividades concluidas */
    public static function summarize(Collection $activities, int $rows = 10): array
    {
        $done = $activities->filter(fn (Activity $a) => $a->durationDays() !== null)->values();
        $withDeadline = $done->filter(fn (Activity $a) => $a->deadlineDelta() !== null);
        $antes = $withDeadline->filter(fn (Activity $a) => $a->deadlineDelta() < 0);
        $enFecha = $withDeadline->filter(fn (Activity $a) => $a->deadlineDelta() === 0);
        $despues = $withDeadline->filter(fn (Activity $a) => $a->deadlineDelta() > 0);
        $avg = fn (Collection $c, callable $f) => $c->isEmpty() ? null : round($c->avg($f), 1);
        $pct = fn (int $n) => $withDeadline->isEmpty() ? null : round($n / $withDeadline->count() * 100, 1);

        return [
            'total' => $done->count(),
            'duracion' => $avg($done, fn ($a) => $a->durationDays()),
            'planeado' => $avg($done->filter(fn ($a) => $a->plannedDays() !== null), fn ($a) => $a->plannedDays()),
            'antes' => $antes->count(),
            'en_fecha' => $enFecha->count(),
            'despues' => $despues->count(),
            'antes_pct' => $pct($antes->count()),
            'en_fecha_pct' => $pct($enFecha->count()),
            'despues_pct' => $pct($despues->count()),
            'a_tiempo_pct' => $pct($antes->count() + $enFecha->count()),
            'dias_adelanto' => $avg($antes, fn ($a) => -$a->deadlineDelta()),
            'dias_retraso' => $avg($despues, fn ($a) => $a->deadlineDelta()),
            'rows' => $done->sortByDesc(fn ($a) => $a->finishedOn())->take($rows)->values(),
        ];
    }
}
