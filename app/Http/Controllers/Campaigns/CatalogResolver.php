<?php

namespace App\Http\Controllers\Campaigns;

use App\Models\Community;

/** Comunidad elegida de la lista u «otra» escrita a mano (se agrega al catálogo sin duplicar). */
class CatalogResolver
{
    public static function community(int|string|null $selected, ?string $typed): ?int
    {
        if ($selected === null || $selected === '') {
            return null;
        }
        if ($selected !== 'otro') {
            return (int) $selected;
        }

        $name = preg_replace('/\s+/', ' ', trim((string) $typed));
        $item = Community::whereRaw('LOWER(name) = ?', [mb_strtolower($name)])->first()
            ?? Community::create(['name' => $name, 'active' => true]);

        if (! $item->active) {
            $item->update(['active' => true]);
        }

        return $item->id;
    }
}
