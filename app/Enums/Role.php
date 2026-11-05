<?php

namespace App\Enums;

enum Role: string
{
    case Admin = 'admin';
    case Gerente = 'gerente';
    case Encargado = 'encargado';
    case Empleado = 'empleado';

    public function label(): string
    {
        return match ($this) {
            self::Admin => 'Administrador',
            self::Gerente => 'Gerente',
            self::Encargado => 'Encargado',
            self::Empleado => 'Empleado',
        };
    }

    /** Nivel jerárquico: mayor número, más permisos. */
    public function level(): int
    {
        return match ($this) {
            self::Admin => 4,
            self::Gerente => 3,
            self::Encargado => 2,
            self::Empleado => 1,
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
