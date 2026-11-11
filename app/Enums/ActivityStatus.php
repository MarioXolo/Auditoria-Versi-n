<?php

namespace App\Enums;

/**
 * Estado de la actividad.
 * - No iniciado, En proceso y Suspendida se eligen a mano (también el empleado asignado).
 * - Concluido y No completada solo los pone un superior con el checkbox (✓ verde / ✗ rojo).
 */
enum ActivityStatus: string
{
    case NoIniciado = 'no_iniciado';
    case EnProceso = 'en_proceso';
    case Suspendida = 'suspendida';
    case Concluido = 'concluido';
    case NoCompletada = 'no_completada';

    public function label(): string
    {
        return match ($this) {
            self::NoIniciado => 'No iniciado',
            self::EnProceso => 'En proceso',
            self::Suspendida => 'Suspendida',
            self::Concluido => 'Concluido',
            self::NoCompletada => 'No completada',
        };
    }

    /** Clases de Tailwind para la etiqueta de estado. */
    public function badge(): string
    {
        return match ($this) {
            self::NoIniciado => 'bg-amber-100 text-amber-800',
            self::EnProceso => 'bg-sky-100 text-sky-800',
            self::Suspendida => 'bg-slate-200 text-slate-700',
            self::Concluido => 'bg-emerald-600 text-white',
            self::NoCompletada => 'bg-rose-600 text-white',
        };
    }

    /** Concluido y No completada solo se asignan con el checkbox de un superior. */
    public function isMarked(): bool
    {
        return in_array($this, [self::Concluido, self::NoCompletada], true);
    }

    /** Sin marcar todavía y sin suspender: cuentan como abiertas. */
    public function isOpen(): bool
    {
        return in_array($this, [self::NoIniciado, self::EnProceso], true);
    }

    /** @return array<string, string> */
    public static function options(): array
    {
        $options = [];
        foreach (self::cases() as $case) {
            $options[$case->value] = $case->label();
        }

        return $options;
    }

    /** Estados que se eligen a mano (los otros dos se ponen con el checkbox). */
    public static function editableOptions(): array
    {
        return [
            self::NoIniciado->value => self::NoIniciado->label(),
            self::EnProceso->value => self::EnProceso->label(),
            self::Suspendida->value => self::Suspendida->label(),
        ];
    }
}
