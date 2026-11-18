<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/** Minuta de reunión (formato de Excel de 5 secciones). Solo administradores y gerentes. */
class Minute extends Model
{
    public const MODALIDADES = ['Presencial', 'Virtual', 'Híbrida'];

    /** Estatus acordados por el equipo (minuta del 03/11/2026) más «Terminado», que ya venía en el Excel. */
    public const ESTATUS = ['Iniciado', 'En proceso', 'Suspendida', 'Concluida', 'Terminado'];

    protected $fillable = ['titulo', 'tema', 'objetivo', 'fecha', 'hora_inicio', 'hora_fin', 'lugar', 'archivo_origen', 'created_by', 'updated_by'];

    protected function casts(): array
    {
        return ['fecha' => 'date'];
    }

    public function participants(): HasMany
    {
        return $this->hasMany(MinuteParticipant::class)->orderBy('orden');
    }

    public function puntos(): HasMany
    {
        return $this->hasMany(MinuteItem::class)->where('tipo', 'punto')->orderBy('orden');
    }

    public function acuerdos(): HasMany
    {
        return $this->hasMany(MinuteItem::class)->where('tipo', 'acuerdo')->orderBy('orden');
    }

    public function items(): HasMany
    {
        return $this->hasMany(MinuteItem::class);
    }

    public function commitments(): HasMany
    {
        return $this->hasMany(MinuteCommitment::class)->orderBy('orden');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /** Título como en el Excel: «MINUTA REUNIÓN MARTES 10/11/2026». */
    public static function defaultTitle(Carbon|string $fecha): string
    {
        $f = $fecha instanceof Carbon ? $fecha : Carbon::parse($fecha);

        return 'MINUTA REUNIÓN '.mb_strtoupper($f->locale('es')->dayName).' '.$f->format('d/m/Y');
    }

    public static function hhmm(?string $time): ?string
    {
        return $time ? substr($time, 0, 5) : null;
    }
}
