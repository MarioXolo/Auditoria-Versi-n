<?php

namespace App\Models;

use App\Services\StrategyBooks;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Un registro de cualquier libro de Estrategia (mitin, barda, lona, desayuno…).
 * Los campos comunes son columnas; los propios del libro van en «datos» y los archivos en «archivos».
 * Si el libro es de eventos, el registro se controla en la Agenda reportada al IEEQ.
 */
class StrategyRecord extends Model
{
    public const IEEQ_ESTATUS = ['Por reportar', 'Reportado', 'Modificado', 'Cancelado'];

    protected $fillable = [
        'libro', 'community_id', 'section_id', 'responsable_id', 'fecha', 'hora', 'estatus',
        'costo_estimado', 'costo_real', 'lat', 'lng', 'datos', 'archivos', 'created_by',
    ];

    protected function casts(): array
    {
        return [
            'fecha' => 'date',
            'datos' => 'array',
            'archivos' => 'array',
            'costo_estimado' => 'decimal:2',
            'costo_real' => 'decimal:2',
            'es_evento' => 'boolean',
            'ieeq_fecha_limite' => 'date',
            'ieeq_reportado_el' => 'date',
            'community_id' => 'integer',
            'section_id' => 'integer',
            'responsable_id' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        static::saving(fn (self $r) => $r->syncAgenda());
    }

    public function campaign(): BelongsTo
    {
        return $this->belongsTo(Campaign::class);
    }

    public function community(): BelongsTo
    {
        return $this->belongsTo(Community::class);
    }

    public function section(): BelongsTo
    {
        return $this->belongsTo(Section::class);
    }

    public function responsable(): BelongsTo
    {
        return $this->belongsTo(User::class, 'responsable_id');
    }

    public function book(): array
    {
        return StrategyBooks::get($this->libro);
    }

    public function dato(string $key): mixed
    {
        return $this->datos[$key] ?? null;
    }

    public function archivo(string $key): ?string
    {
        return $this->archivos[$key] ?? null;
    }

    /** Nombre del registro: el campo «title» de su libro. */
    public function title(): string
    {
        return (string) ($this->dato($this->book()['title']) ?: StrategyBooks::names()[$this->libro].' #'.$this->id);
    }

    /** Hora en formato 24 h corto («17:30»). */
    public function horaCorta(): ?string
    {
        return $this->hora ? substr((string) $this->hora, 0, 5) : null;
    }

    // ── Agenda reportada al IEEQ ───────────────────────────────────────────────

    /**
     * Mantiene al día el control del IEEQ cada vez que se guarda el registro:
     * - Entra a la agenda si su libro es de eventos (la agenda metropolitana solo si es acto de campaña).
     * - Fecha límite = fecha del evento menos los días de anticipación configurados (mientras no se haya reportado).
     * - Si ya estaba reportado y cambia la fecha, la hora o el lugar, pasa a «Modificado» para reportar el cambio.
     */
    public function syncAgenda(): void
    {
        $book = StrategyBooks::get($this->libro);
        $evento = ($book['evento'] ?? false) && ! ($this->libro === 'metropolitana' && $this->dato('tipo_acto') === 'Acto de gobierno');

        if (! $evento) {
            $this->es_evento = false;
            $this->ieeq_estatus = null;
            $this->ieeq_fecha_limite = null;

            return;
        }

        $this->es_evento = true;
        $this->ieeq_estatus ??= 'Por reportar';

        $changed = $this->isDirty(['fecha', 'hora', 'community_id']) || $this->datoChanged($book['title']);
        if ($this->exists && $changed && in_array($this->getOriginal('ieeq_estatus'), ['Reportado'], true)) {
            $this->ieeq_estatus = 'Modificado';
        }

        if (in_array($this->ieeq_estatus, ['Por reportar', 'Modificado'], true) && $this->fecha && ($this->isDirty('fecha') || ! $this->ieeq_fecha_limite)) {
            $this->ieeq_fecha_limite = CarbonImmutable::parse($this->fecha)->subDays(self::diasAnticipacion());
        }
    }

    private function datoChanged(string $key): bool
    {
        if (! $this->exists || ! $this->isDirty('datos')) {
            return false;
        }
        $old = json_decode((string) $this->getRawOriginal('datos'), true) ?: [];

        return ($old[$key] ?? null) !== $this->dato($key);
    }

    public static function diasAnticipacion(): int
    {
        return (int) config('auditoria.ieeq_dias_anticipacion', 7);
    }

    /** «vencido», «por_vencer» (dentro de los días de aviso), «en_tiempo», «reportado» o «cancelado». */
    public function agendaSemaforo(): ?string
    {
        if (! $this->es_evento) {
            return null;
        }

        return match (true) {
            $this->ieeq_estatus === 'Reportado' => 'reportado',
            $this->ieeq_estatus === 'Cancelado' => 'cancelado',
            $this->ieeq_fecha_limite === null => 'en_tiempo',
            $this->ieeq_fecha_limite->lt(today()) => 'vencido',
            $this->ieeq_fecha_limite->lte(today()->addDays((int) config('auditoria.ieeq_dias_aviso', 3))) => 'por_vencer',
            default => 'en_tiempo',
        };
    }

    /** Eventos que necesitan reportarse (o reportar su cambio) y no se han reportado. */
    public function scopePendientesIeeq(Builder $q): Builder
    {
        return $q->where('es_evento', true)->whereIn('ieeq_estatus', ['Por reportar', 'Modificado']);
    }
}
