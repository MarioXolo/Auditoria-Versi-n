<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Attendance extends Model
{
    protected $fillable = [
        'user_id',
        'work_date',
        'check_in_at',
        'check_in_photo',
        'check_out_at',
        'check_out_photo',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'user_id' => 'integer',
            'work_date' => 'date',
            'check_in_at' => 'datetime',
            'check_out_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** Horas trabajadas en formato "Xh Ym". */
    public function workedTime(): ?string
    {
        if (! $this->check_in_at || ! $this->check_out_at) {
            return null;
        }

        $minutes = (int) $this->check_in_at->diffInMinutes($this->check_out_at);

        return intdiv($minutes, 60).'h '.($minutes % 60).'m';
    }
}
