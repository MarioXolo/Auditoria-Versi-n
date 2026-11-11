<?php

namespace App\Enums;

enum Priority: string
{
    case Baja = 'baja';
    case Media = 'media';
    case Alta = 'alta';

    public function label(): string
    {
        return match ($this) {
            self::Baja => 'Baja',
            self::Media => 'Media',
            self::Alta => 'Alta',
        };
    }

    public function badge(): string
    {
        return match ($this) {
            self::Baja => 'bg-slate-100 text-slate-700',
            self::Media => 'bg-orange-100 text-orange-800',
            self::Alta => 'bg-rose-100 text-rose-800',
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
