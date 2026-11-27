<?php

namespace App\Models;

use App\Enums\ActivityStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Actividad de la «Agenda del Presidente». Mismos campos que Activity, en una tabla aparte:
 * no aparece en Actividades, dashboards, calendario ni métricas de los demás usuarios.
 */
class PresidentActivity extends Model
{
    protected $fillable = [
        'folio', 'folio_seq', 'activity_type_id', 'community_id', 'assigned_to', 'created_by', 'status',
        'start_date', 'end_date', 'completed_at', 'evidence_path', 'evidence_text', 'marked_by', 'marked_at', 'mark_notes',
    ];

    protected function casts(): array
    {
        return [
            'status' => ActivityStatus::class,
            'start_date' => 'date',
            'end_date' => 'date',
            'completed_at' => 'datetime',
            'marked_at' => 'datetime',
            'assigned_to' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        // Folio propio: AP-0001, AP-0002…
        static::creating(function (self $a) {
            $a->folio_seq = (int) self::max('folio_seq') + 1;
            $a->folio = 'AP-'.str_pad((string) $a->folio_seq, 4, '0', STR_PAD_LEFT);
        });
    }

    public function type(): BelongsTo
    {
        return $this->belongsTo(ActivityType::class, 'activity_type_id');
    }

    public function community(): BelongsTo
    {
        return $this->belongsTo(Community::class);
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function marker(): BelongsTo
    {
        return $this->belongsTo(User::class, 'marked_by');
    }

    public function hasEvidence(): bool
    {
        return $this->evidence_path !== null || filled($this->evidence_text);
    }

    public function isOverdue(): bool
    {
        return $this->status->isOpen() && $this->end_date->lt(today());
    }

    /** Concluido es definitivo: ya no se edita ni se le quita la marca. */
    public function isLocked(): bool
    {
        return $this->status === ActivityStatus::Concluido;
    }
}
