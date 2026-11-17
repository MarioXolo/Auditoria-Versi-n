<?php

namespace App\Support;

/**
 * Escritura de CSV protegida contra «inyección de fórmulas»: si alguien captura
 * en un campo algo como =HYPERLINK(...) o @SUM(...), Excel lo ejecutaría al abrir la
 * exportación. Esas celdas se escriben con un apóstrofo delante para que Excel las
 * muestre como texto. Números y teléfonos (+52 442 123 4567, -150) no se tocan.
 */
class Csv
{
    public static function put($handle, array $row): void
    {
        fputcsv($handle, array_map([self::class, 'cell'], $row));
    }

    public static function cell(mixed $value): mixed
    {
        if (! is_string($value) || $value === '') {
            return $value;
        }

        $risky = in_array($value[0], ['=', '+', '-', '@', "\t", "\r"], true)
            && ! preg_match('/^[+\-]?[\d\s().,\-]+$/', $value);

        return $risky ? "'".$value : $value;
    }
}
