<?php

namespace App\Enums;

enum ValidationStatus: string
{
    case Pendiente = 'pendiente';
    case Validada = 'validada';
    case Rechazada = 'rechazada';

    public function label(): string
    {
        return match ($this) {
            self::Pendiente => 'Por validar',
            self::Validada => 'Validada',
            self::Rechazada => 'Rechazada',
        };
    }

    public function badge(): string
    {
        return match ($this) {
            self::Pendiente => 'bg-violet-100 text-violet-800',
            self::Validada => 'bg-emerald-600 text-white',
            self::Rechazada => 'bg-rose-100 text-rose-800',
        };
    }
}
