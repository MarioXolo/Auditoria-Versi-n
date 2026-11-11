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

        // Encargados: las que ellos asignaron, las que les asignaron a ellos y las que
        // sus empleados se cargaron a sí mismos (para poder marcarlas).
        // Empleados: solo las suyas.
        if ($user->isEncargado()) {
            $memberIds = $user->teamMemberIds();

            return $query->where(function (Builder $q) use ($user, $memberIds) {
                $q->where('created_by', $user->id)
                    ->orWhere('assigned_to', $user->id);

                if ($memberIds !== []) {
                    $q->orWhere(fn (Builder $own) => $own
                        ->whereIn('assigned_to', $memberIds)
                        ->whereColumn('created_by', 'assigned_to'));
                }
            });
        }

        return $query->where('assigned_to', $user->id);
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
