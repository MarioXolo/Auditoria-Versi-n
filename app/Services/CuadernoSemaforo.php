<?php

namespace App\Services;

use App\Models\Campaign;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

/**
 * Semáforo automático del Cuaderno Maestro. Nadie lo captura: sale de las fechas,
 * estatus y movimientos que ya existen.
 *
 *  - rojo:     algo vencido (fecha pasada sin «Hecho», tarea vencida, evento sin reportar
 *              al IEEQ a tiempo, tope de gastos rebasado, indicador en rojo).
 *  - amarillo: algo vence dentro de los días de aviso, la hoja lleva varios días sin
 *              capturas, el gasto pasa del 90 % del tope o hay indicadores en ámbar.
 *  - verde:    tiene información y nada de lo anterior.
 *  - gris:     sin información todavía.
 */
class CuadernoSemaforo
{
    /** Hojas con información "viva": si dejan de moverse se ponen en amarillo. */
    private const VIVAS = [3 => 'voters', 4 => 'promoters', 5 => 'actors', 10 => 'achievements'];

    public function __construct(private readonly Campaign $campaign)
    {
    }

    public static function diasAviso(): int
    {
        return (int) config('auditoria.semaforo.dias_aviso', 7);
    }

    public static function diasSinMovimiento(): int
    {
        return (int) config('auditoria.semaforo.dias_sin_movimiento', 15);
    }

    /**
     * Conteos por libro de Estrategia: vencidos, por vencer y última captura.
     *
     * @return Collection<string, object{vencidos:int, por_vencer:int, ultimo:?CarbonImmutable}>
     */
    public function porLibro(): Collection
    {
        $r = fn () => $this->campaign->strategyRecords();
        $vencidos = $r()->vencidos()->selectRaw('libro, count(*) as n')->groupBy('libro')->pluck('n', 'libro');
        $porVencer = $r()->porVencer()->selectRaw('libro, count(*) as n')->groupBy('libro')->pluck('n', 'libro');
        $ultimo = $r()->selectRaw('libro, max(updated_at) as u')->groupBy('libro')->pluck('u', 'libro');

        return collect(StrategyBooks::keys())->mapWithKeys(fn ($k) => [$k => (object) [
            'vencidos' => (int) ($vencidos[$k] ?? 0),
            'por_vencer' => (int) ($porVencer[$k] ?? 0),
            'ultimo' => isset($ultimo[$k]) ? CarbonImmutable::parse($ultimo[$k]) : null,
        ]]);
    }

    /**
     * Color y motivo de cada burbuja.
     *
     * @param  array<int, bool>  $conInfo  número de burbuja => ¿ya tiene información?
     * @return array<int, array{color:string, motivo:?string}>
     */
    public function burbujas(array $conInfo): array
    {
        $libros = $this->porLibro();
        $out = [];

        foreach ($conInfo as $num => $done) {
            if (! $done) {
                $out[$num] = ['color' => 'gris', 'motivo' => null];

                continue;
            }

            [$rojo, $amarillo] = match (true) {
                $num === 7 => $this->presupuesto(),
                $num === 8 => $this->cronograma(),
                $num === 9 => $this->kpis(),
                $num === 20 => $this->agenda(),
                isset(StrategyBooks::PUNTOS[$num]) => $this->punto($num, $libros),
                isset(self::VIVAS[$num]) => [[], $this->sinMovimiento($this->campaign->{self::VIVAS[$num]}()->max('updated_at'))],
                default => [[], []],
            };

            $out[$num] = match (true) {
                $rojo !== [] => ['color' => 'rojo', 'motivo' => implode(' · ', $rojo)],
                $amarillo !== [] => ['color' => 'amarillo', 'motivo' => implode(' · ', $amarillo)],
                default => ['color' => 'verde', 'motivo' => 'Al día'],
            };
        }

        return $out;
    }

    /** Resumen para el encabezado: cuántas burbujas hay de cada color. */
    public static function resumen(array $burbujas): array
    {
        $c = collect($burbujas)->countBy('color');

        return ['rojo' => $c['rojo'] ?? 0, 'amarillo' => $c['amarillo'] ?? 0, 'verde' => $c['verde'] ?? 0, 'gris' => $c['gris'] ?? 0];
    }

    private function punto(int $num, Collection $libros): array
    {
        $mios = $libros->only(array_keys(StrategyBooks::ofPunto($num)));
        $vencidos = $mios->sum('vencidos');
        $porVencer = $mios->sum('por_vencer');
        $ultimo = $mios->pluck('ultimo')->filter()->max();

        if ($num === 11) {
            $casillas = $this->campaign->pollingStations()->max('updated_at');
            $ultimo = collect([$ultimo, $casillas ? CarbonImmutable::parse($casillas) : null])->filter()->max();
        }

        return [
            $vencidos ? [self::plural($vencidos, 'vencido', 'vencidos')] : [],
            array_merge(
                $porVencer ? [self::plural($porVencer, 'vence pronto', 'vencen pronto')] : [],
                $this->sinMovimiento($ultimo),
            ),
        ];
    }

    private function cronograma(): array
    {
        $q = fn () => $this->campaign->tasks()->where('estatus', '!=', 'Hecho')->whereNotNull('fecha_limite');
        $vencidas = $q()->whereDate('fecha_limite', '<', today())->count();
        $pronto = $q()->whereDate('fecha_limite', '>=', today())->whereDate('fecha_limite', '<=', today()->addDays(self::diasAviso()))->count();

        return [
            $vencidas ? [self::plural($vencidas, 'tarea vencida', 'tareas vencidas')] : [],
            $pronto ? [self::plural($pronto, 'tarea vence pronto', 'tareas vencen pronto')] : [],
        ];
    }

    private function presupuesto(): array
    {
        $tope = (float) $this->campaign->tope_gastos;
        $ejercido = (float) $this->campaign->budgetItems()->sum('ejercido');
        if ($tope <= 0) {
            return [[], []];
        }
        $ratio = $ejercido / $tope;

        return [
            $ratio > 1 ? ['Rebasa el tope'] : [],
            $ratio >= 0.9 && $ratio <= 1 ? ['Gasto arriba del 90 % del tope'] : [],
        ];
    }

    private function kpis(): array
    {
        $labels = (new CampaignMetrics($this->campaign))->kpis()->pluck('status.label');
        $rojos = $labels->filter(fn ($l) => in_array($l, ['Rojo', 'Rebasa tope'], true))->count();
        $ambar = $labels->filter(fn ($l) => $l === 'Ámbar')->count();

        return [
            $rojos ? [self::plural($rojos, 'indicador en rojo', 'indicadores en rojo')] : [],
            $ambar ? [self::plural($ambar, 'indicador en ámbar', 'indicadores en ámbar')] : [],
        ];
    }

    private function agenda(): array
    {
        $pendientes = $this->campaign->strategyRecords()->pendientesIeeq()->whereNotNull('ieeq_fecha_limite');
        $vencidos = (clone $pendientes)->whereDate('ieeq_fecha_limite', '<', today())->count();
        $pronto = (clone $pendientes)->whereDate('ieeq_fecha_limite', '>=', today())
            ->whereDate('ieeq_fecha_limite', '<=', today()->addDays((int) config('auditoria.ieeq_dias_aviso', 3)))->count();

        return [
            $vencidos ? [self::plural($vencidos, 'evento sin reportar a tiempo', 'eventos sin reportar a tiempo')] : [],
            $pronto ? [self::plural($pronto, 'evento por reportar ya', 'eventos por reportar ya')] : [],
        ];
    }

    private function sinMovimiento(mixed $ultimo): array
    {
        if (! $ultimo) {
            return [];
        }
        $dias = (int) CarbonImmutable::parse($ultimo)->startOfDay()->diffInDays(today());

        return $dias >= self::diasSinMovimiento() ? ["Sin capturas hace {$dias} días"] : [];
    }

    private static function plural(int $n, string $one, string $many): string
    {
        return number_format($n).' '.($n === 1 ? $one : $many);
    }
}
