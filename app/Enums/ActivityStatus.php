<?php

namespace App\Enums;

enum ActivityStatus: string
{
    case Pendiente = 'pendiente';
    case EnProgreso = 'en_progreso';
    case Completada = 'completada';
    case Cancelada = 'cancelada';

    public function label(): string
    {
        return match ($this) {
            self::Pendiente => 'Pendiente',
            self::EnProgreso => 'En progreso',
            self::Completada => 'Completada',
            self::Cancelada => 'Cancelada',
        };
    }

    /** Clases de Tailwind para la etiqueta de estado. */
    public function badge(): string
    {
        return match ($this) {
            self::Pendiente => 'bg-amber-100 text-amber-800',
            self::EnProgreso => 'bg-sky-100 text-sky-800',
            self::Completada => 'bg-emerald-100 text-emerald-800',
            self::Cancelada => 'bg-stone-200 text-stone-700',
        };
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
}
