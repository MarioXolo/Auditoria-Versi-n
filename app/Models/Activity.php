<?php

namespace App\Models;

use App\Enums\ActivityStatus;
use App\Enums\Priority;
use App\Enums\ValidationStatus;
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
        'validation_status',
        'validated_by',
        'validated_at',
        'validation_notes',
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
            'validation_status' => ValidationStatus::class,
            'validated_by' => 'integer',
            'validated_at' => 'datetime',
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

    public function validator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'validated_by');
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

        // Encargados: solo las que ellos asignaron y las que les asignaron a ellos.
        // Empleados: solo las suyas.
        if ($user->isEncargado()) {
            return $query->where(function (Builder $q) use ($user) {
                $q->where('created_by', $user->id)->orWhere('assigned_to', $user->id);
            });
        }

        return $query->where('assigned_to', $user->id);
    }

    /** Completadas que esperan validación. */
    public function scopePendingValidation(Builder $query): Builder
    {
        return $query->where('status', ActivityStatus::Completada->value)
            ->where('validation_status', ValidationStatus::Pendiente->value);
    }

    public function isPendingValidation(): bool
    {
        return $this->status === ActivityStatus::Completada && $this->validation_status === ValidationStatus::Pendiente;
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
            && in_array($this->status, [ActivityStatus::Pendiente, ActivityStatus::EnProgreso], true);
    }
}
