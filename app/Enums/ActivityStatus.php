<?php

namespace App\Enums;

enum ActivityStatus: string
{
    case Pendiente = 'pendiente';
    case EnProgreso = 'en_progreso';
    case Completada = 'completada';
    case Incompleta = 'incompleta';
    case Cancelada = 'cancelada';

    public function label(): string
    {
        return match ($this) {
            self::Pendiente => 'Pendiente',
            self::EnProgreso => 'En progreso',
            self::Completada => 'Completada',
            self::Incompleta => 'Incompleta',
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
            self::Incompleta => 'bg-rose-600 text-white',
            self::Cancelada => 'bg-slate-200 text-slate-700',
        };
    }

    /** Completada e Incompleta solo se asignan al marcar (checkbox de un superior). */
    public function isMarked(): bool
    {
        return in_array($this, [self::Completada, self::Incompleta], true);
    }

    /** Sin marcar todavía: cuentan como abiertas. */
    public function isOpen(): bool
    {
        return in_array($this, [self::Pendiente, self::EnProgreso], true);
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

    /** Estados que se pueden elegir al editar (los demás se ponen marcando). */
    public static function editableOptions(): array
    {
        return [
            self::Pendiente->value => self::Pendiente->label(),
            self::EnProgreso->value => self::EnProgreso->label(),
            self::Cancelada->value => self::Cancelada->label(),
        ];
    }
}
