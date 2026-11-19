<?php

namespace App\Models;

use App\Enums\ActivityStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Activity extends Model
{
    protected $fillable = [
        'folio',
        'folio_group',
        'folio_seq',
        'activity_type_id',
        'community_id',
        'assigned_to',
        'created_by',
        'team_id',
        'status',
        'start_date',
        'end_date',
        'completed_at',
        'evidence_path',
        'evidence_text',
        'marked_by',
        'marked_at',
        'mark_notes',
        'requiere_ubicacion',
        'destino_lat',
        'destino_lng',
        'destino_radio',
        'destino_nombre',
        'ubicacion_lat',
        'ubicacion_lng',
        'ubicacion_precision',
        'ubicacion_distancia',
        'ubicacion_at',
    ];

    protected function casts(): array
    {
        return [
            'assigned_to' => 'integer',
            'created_by' => 'integer',
            'team_id' => 'integer',
            'activity_type_id' => 'integer',
            'community_id' => 'integer',
            'status' => ActivityStatus::class,
            'start_date' => 'date',
            'end_date' => 'date',
            'requiere_ubicacion' => 'boolean',
            'destino_lat' => 'float',
            'destino_lng' => 'float',
            'destino_radio' => 'integer',
            'ubicacion_lat' => 'float',
            'ubicacion_lng' => 'float',
            'ubicacion_precision' => 'integer',
            'ubicacion_distancia' => 'integer',
            'ubicacion_at' => 'datetime',
            'completed_at' => 'datetime',
            'marked_by' => 'integer',
            'marked_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        // Folio por equipo, en secuencia: ID{número de equipo}.{0001…}. Una vez asignado no cambia.
        static::creating(function (Activity $activity) {
            if ($activity->folio) {
                return;
            }

            $group = $activity->team_id ? (int) Team::whereKey($activity->team_id)->value('number') : 0;
            $seq = self::nextSeq($group);

            $activity->folio_group = $group;
            $activity->folio_seq = $seq;
            $activity->folio = self::formatCode($group, $seq);
        });
    }

    /** Folio visible: ID1.0001 (equipo 1, actividad 1). Equipo 0 = sin equipo. */
    public static function formatCode(int $group, int $seq): string
    {
        return 'ID'.$group.'.'.str_pad((string) $seq, 4, '0', STR_PAD_LEFT);
    }

    public static function nextSeq(int $group): int
    {
        return ((int) self::where('folio_group', $group)->max('folio_seq')) + 1;
    }

    /** Folio que tendrá la siguiente actividad del equipo (solo para mostrarlo en el formulario). */
    public static function nextCode(?int $group): string
    {
        $group ??= 0;

        return self::formatCode($group, self::nextSeq($group));
    }

    public function getCodeAttribute(): string
    {
        return (string) $this->folio;
    }

    /** Nombre corto para títulos: «Tipo · Comunidad». */
    public function getLabelAttribute(): string
    {
        return trim(($this->type?->name ?? 'Actividad').' · '.($this->community?->name ?? ''), ' ·');
    }

    public function type(): BelongsTo
    {
        return $this->belongsTo(ActivityType::class, 'activity_type_id');
    }

    public function community(): BelongsTo
    {
        return $this->belongsTo(Community::class);
    }

    public function hasEvidence(): bool
    {
        return $this->evidence_path !== null || filled($this->evidence_text);
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /** Superior que marcó la actividad como Concluido o No completada. */
    public function marker(): BelongsTo
    {
        return $this->belongsTo(User::class, 'marked_by');
    }

    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    /** Actividades que el usuario tiene permitido ver. */
    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        if ($user->seesEverything()) {
            return $query;
        }

        // Encargado: solo las suyas (las que le asignaron y las que se cargó). No ve las de otros encargados.
        return $query->where(fn (Builder $q) => $q->where('assigned_to', $user->id)->orWhere('created_by', $user->id));
    }

    // ------------------------------------------------------------------
    // Geolocalización
    // ------------------------------------------------------------------

    /** Precisión mínima aceptada del GPS del celular, en metros. */
    public const PRECISION_MAXIMA = 100;

    /** Distancia en metros entre dos puntos (fórmula de Haversine). */
    public static function distanceMeters(float $lat1, float $lng1, float $lat2, float $lng2): int
    {
        $r = 6371000;
        $dLat = deg2rad($lat2 - $lat1);
        $dLng = deg2rad($lng2 - $lng1);
        $a = sin($dLat / 2) ** 2 + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLng / 2) ** 2;

        return (int) round(2 * $r * asin(min(1, sqrt($a))));
    }

    /**
     * Estado de la ubicación: null si la actividad no la requiere; «pendiente» si aún no se
     * valida; «fuera» si se validó lejos del destino; «verificada» si estaba dentro del radio.
     * Pendiente y fuera son alertas (rojo).
     */
    public function locationStatus(): ?string
    {
        if (! $this->requiere_ubicacion) {
            return null;
        }
        if (! $this->ubicacion_at) {
            return 'pendiente';
        }

        return $this->ubicacion_distancia !== null && $this->ubicacion_distancia <= $this->destino_radio ? 'verificada' : 'fuera';
    }

    public function locationAlert(): bool
    {
        return in_array($this->locationStatus(), ['pendiente', 'fuera'], true);
    }

    public function locationLabel(): ?string
    {
        $m = fn (int $v) => $v >= 1000 ? number_format($v / 1000, 1).' km' : $v.' m';

        return match ($this->locationStatus()) {
            null => null,
            'pendiente' => 'Ubicación no validada',
            'fuera' => 'Fuera del lugar ('.$m($this->ubicacion_distancia).' del destino)',
            'verificada' => 'Ubicación verificada ('.$m($this->ubicacion_distancia).')',
        };
    }

    /**
     * Quien concluye su propia actividad de ubicación necesita haberla validado dentro del radio.
     * Un gerente o administrador que revisa la de otra persona no tiene esta restricción.
     */
    public function locationBlocksSelfConclusion(User $user): bool
    {
        return $this->assigned_to === $user->id
            && $this->requiere_ubicacion
            && $this->locationStatus() !== 'verificada';
    }

    public function scopeWithLocationAlert(Builder $q): Builder
    {
        return $q->where('requiere_ubicacion', true)
            ->where(fn ($w) => $w->whereNull('ubicacion_at')->orWhereColumn('ubicacion_distancia', '>', 'destino_radio'));
    }

    // ------------------------------------------------------------------
    // Duración: desde la fecha de inicio hasta que se marcó «Concluido»
    // ------------------------------------------------------------------

    /** Día en que se terminó (se marcó «Concluido»), o null si no está concluida. */
    public function finishedOn(): ?\Illuminate\Support\Carbon
    {
        if ($this->status !== ActivityStatus::Concluido) {
            return null;
        }
        $at = $this->completed_at ?? $this->marked_at;

        return $at?->copy()->startOfDay();
    }

    /** Días que duró: de la fecha de inicio al día en que se terminó, contando ambos (mismo día = 1). */
    public function durationDays(): ?int
    {
        $end = $this->finishedOn();
        if (! $end || ! $this->start_date) {
            return null;
        }

        return max(1, (int) $this->start_date->copy()->startOfDay()->diffInDays($end, false) + 1);
    }

    /** Días que se tenían planeados: de la fecha de inicio a la fecha de fin, contando ambos. */
    public function plannedDays(): ?int
    {
        if (! $this->start_date || ! $this->end_date) {
            return null;
        }

        return max(1, (int) $this->start_date->copy()->startOfDay()->diffInDays($this->end_date->copy()->startOfDay(), false) + 1);
    }

    /**
     * Días contra la fecha de fin: negativo = terminó antes, 0 = en la fecha, positivo = después.
     * Null si no está concluida o no tiene fecha de fin.
     */
    public function deadlineDelta(): ?int
    {
        $end = $this->finishedOn();
        if (! $end || ! $this->end_date) {
            return null;
        }

        return (int) $this->end_date->copy()->startOfDay()->diffInDays($end, false);
    }

    /** «antes», «a_tiempo» (el mismo día) o «despues»; null si no aplica. */
    public function deadlineResult(): ?string
    {
        $d = $this->deadlineDelta();

        return match (true) {
            $d === null => null,
            $d < 0 => 'antes',
            $d === 0 => 'a_tiempo',
            default => 'despues',
        };
    }

    public function deadlineLabel(): ?string
    {
        $d = $this->deadlineDelta();
        $dias = fn (int $n) => $n === 1 ? '1 día' : "{$n} días";

        return match (true) {
            $d === null => null,
            $d < 0 => 'Terminó '.$dias(-$d).' antes',
            $d === 0 => 'Terminó en la fecha',
            default => 'Terminó '.$dias($d).' después',
        };
    }

    /** La cargó la misma persona que la tiene asignada (empleado o encargado). */
    public function isSelfAssigned(): bool
    {
        return $this->created_by === $this->assigned_to;
    }

    /** Vencidas: con fecha de fin pasada y sin marcar ni suspender. */
    public function scopeOverdue(Builder $query): Builder
    {
        return $query->whereDate('end_date', '<', today())
            ->whereIn('status', [ActivityStatus::NoIniciado->value, ActivityStatus::EnProceso->value]);
    }

    public function isOverdue(): bool
    {
        return $this->end_date !== null
            && $this->end_date->isBefore(today())
            && $this->status->isOpen();
    }
}
