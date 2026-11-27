<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Valor de un catálogo ampliable de los puntos nuevos (cuartos, sectores, pilares, personas monitoreadas). */
class StrategyCatalogItem extends Model
{
    protected $fillable = ['catalog', 'name', 'active'];

    protected function casts(): array
    {
        return ['active' => 'boolean'];
    }

    /** Nombres activos de un catálogo (más el valor actual aunque esté inactivo). */
    public static function names(string $catalog, ?string $current = null): array
    {
        $names = self::where('catalog', $catalog)->where('active', true)->orderBy('name')->pluck('name')->all();
        if ($current !== null && $current !== '' && ! in_array($current, $names, true)) {
            $names[] = $current;
        }

        return $names;
    }
}
