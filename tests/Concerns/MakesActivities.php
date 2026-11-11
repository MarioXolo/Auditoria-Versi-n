<?php

namespace Tests\Concerns;

use App\Models\Activity;
use App\Models\ActivityType;
use App\Models\Community;

/** Ayudas para crear actividades con catálogos en las pruebas. */
trait MakesActivities
{
    protected function typeId(string $name = 'Visita domiciliaria'): int
    {
        return ActivityType::firstOrCreate(['name' => $name])->id;
    }

    protected function communityId(string $name = 'San Isidro'): int
    {
        return Community::firstOrCreate(['name' => $name])->id;
    }

    /** Datos válidos para el formulario de actividad. */
    protected function payload(array $overrides = []): array
    {
        return array_merge([
            'activity_type_id' => $this->typeId(),
            'community_id' => $this->communityId(),
            'start_date' => today()->toDateString(),
            'end_date' => today()->addDays(3)->toDateString(),
        ], $overrides);
    }

    /** Crea una actividad directo en la base; la comunidad sirve como etiqueta visible. */
    protected function makeActivity(array $attributes, string $community = 'San Isidro'): Activity
    {
        return Activity::create(array_merge([
            'activity_type_id' => $this->typeId(),
            'community_id' => $this->communityId($community),
            'status' => 'pendiente',
            'start_date' => today()->subDays(5)->toDateString(),
            'end_date' => today()->subDay()->toDateString(),
        ], $attributes));
    }
}
