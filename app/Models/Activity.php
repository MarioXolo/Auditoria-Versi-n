<?php

namespace App\Models;

use App\Enums\ActivityStatus;
use App\Enums\Priority;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Activity extends Model
{
    protected $fillable = [
        'title',
        'description',
        'assigned_to',
        'created_by',
        'team_id',
        'priority',
        'status',
        'due_date',
        'completed_at',
        'evidence_path',
        'notes',
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
            'status' => ActivityStatus::class,
            'priority' => Priority::class,
            'due_date' => 'date',
            'completed_at' => 'datetime',
            'marked_by' => 'integer',
            'marked_at' => 'datetime',
        ];
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /** Superior que marcó la actividad como completada o incompleta. */
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

    /** Vencidas: con fecha límite pasada y sin completar ni cancelar. */
    public function scopeOverdue(Builder $query): Builder
    {
        return $query->whereNotNull('due_date')
            ->whereDate('due_date', '<', today())
            ->whereIn('status', [ActivityStatus::Pendiente->value, ActivityStatus::EnProgreso->value]);
    }

    public function isOverdue(): bool
    {
        return $this->due_date !== null
            && $this->due_date->isBefore(today())
            && $this->status->isOpen();
    }
}
