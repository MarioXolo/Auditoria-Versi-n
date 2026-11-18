<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/** Renglón de «5. Compromisos y/o asuntos pendientes». */
class MinuteCommitment extends Model
{
    public $timestamps = false;

    protected $fillable = ['orden', 'actividad', 'responsable', 'fecha_limite', 'estatus'];

    /** La fecha límite como fecha, si lo es (en el Excel a veces es texto: «Noviembre»). */
    public function fechaLimiteDate(): ?Carbon
    {
        $v = $this->fecha_limite;
        if (! $v) {
            return null;
        }
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $v)) {
            return Carbon::parse($v);
        }
        if (preg_match('#^(\d{1,2})/(\d{1,2})/(\d{4})$#', $v, $m)) {
            return Carbon::create((int) $m[3], (int) $m[2], (int) $m[1]);
        }

        return null;
    }

    public function fechaLimiteLabel(): string
    {
        return $this->fechaLimiteDate()?->format('d/m/Y') ?? (string) $this->fecha_limite;
    }

    public function abierto(): bool
    {
        return ! in_array(mb_strtolower((string) $this->estatus), ['concluida', 'concluido', 'terminado', 'terminada'], true);
    }

    public function vencido(): bool
    {
        return $this->abierto() && ($d = $this->fechaLimiteDate()) && $d->isBefore(today());
    }
}
