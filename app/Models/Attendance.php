<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

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

    /** ¿Se puede checar ese día? (lunes a viernes, según config/auditoria.php). */
    public static function isWorkday(?Carbon $day = null): bool
    {
        return in_array(($day ?? today())->isoWeekday(), config('auditoria.dias_laborales'), true);
    }

    /** Nombres de los días laborales, p. ej. «lunes a viernes». */
    public static function workdaysLabel(): string
    {
        $dias = config('auditoria.dias_laborales');
        $nombres = [1 => 'lunes', 'martes', 'miércoles', 'jueves', 'viernes', 'sábado', 'domingo'];
        sort($dias);
        $consecutivos = count($dias) > 2 && end($dias) - reset($dias) === count($dias) - 1;

        return $consecutivos
            ? $nombres[reset($dias)].' a '.$nombres[end($dias)]
            : implode(', ', array_map(fn ($d) => $nombres[$d], $dias));
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
