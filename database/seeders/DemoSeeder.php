<?php

namespace Database\Seeders;

use App\Enums\ActivityStatus;
use App\Models\Activity;
use App\Models\ActivityType;
use App\Models\Community;
use App\Models\Attendance;
use App\Models\Team;
use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * Datos de prueba para ver el sistema funcionando.
 * Uso: php artisan db:seed --class=DemoSeeder
 * Todos los usuarios demo tienen la contraseña "password".
 */
class DemoSeeder extends Seeder
{
    /** Nombres para los empleados demo (3 por equipo). */
    private const PEOPLE = [
        'Norte' => ['Lucía Hernández', 'Diego Ramírez', 'Fernanda Torres'],
        'Sur' => ['Jorge Castillo', 'Ana Sofía Morales', 'Ricardo Vega'],
    ];

    /** Catálogo demo de tipos de actividad. */
    private const TYPES = [
        'Visita domiciliaria', 'Reunión vecinal', 'Recorrido de zona', 'Entrega de material',
        'Capacitación', 'Levantamiento de encuesta', 'Evento comunitario', 'Reporte de incidencias',
    ];

    /** Catálogo demo de comunidades. */
    private const COMMUNITIES = [
        'San Isidro', 'El Mirador', 'La Esperanza', 'Santa Cruz', 'Los Pinos',
        'Valle Verde', 'Lomas del Sol', 'Centro', 'San Miguel', 'La Joya',
    ];

    /** Evidencias en texto de ejemplo. */
    private const EVIDENCE = [
        'Se visitaron 18 domicilios; 12 personas atendieron y se registraron sus datos de contacto.',
        'Reunión con 25 vecinos en el salón comunal. Se acordó una segunda reunión en dos semanas.',
        'Recorrido completo de la zona; se detectaron 3 calles sin alumbrado y se reportaron.',
        'Se entregaron 150 folletos y 40 carteles en los comercios de la colonia.',
        'Capacitación a 8 personas del equipo sobre el uso del sistema y el llenado de reportes.',
        'Se levantaron 32 encuestas de satisfacción en la plaza principal.',
    ];

    /** @var array<int, array> Actividades por crear, se guardan en orden cronológico */
    private array $pending = [];

    public function run(): void
    {
        $types = collect(self::TYPES)->map(fn ($name) => ActivityType::firstOrCreate(['name' => $name]))->pluck('id');
        $communities = collect(self::COMMUNITIES)->map(fn ($name) => Community::firstOrCreate(['name' => $name]))->pluck('id');
        ActivityType::firstOrCreate(['name' => 'Volanteo'], ['active' => false]); // ejemplo de tipo desactivado

        $gerente = User::factory()->role('gerente')->create([
            'name' => 'Gerente Demo',
            'email' => 'gerente@demo.test',
        ]);

        $teams = collect(['Norte', 'Sur'])->map(function (string $zone) {
            $slug = strtolower($zone);

            $leader = User::factory()->role('encargado')->create([
                'name' => "Encargado {$zone}",
                'email' => "encargado.{$slug}@demo.test",
            ]);

            $team = Team::create([
                'name' => "Equipo {$zone}",
                'description' => "Personal de la zona {$slug}.",
                'leader_id' => $leader->id,
            ]);
            $leader->update(['team_id' => $team->id]);

            // Un empleado con correo fijo para poder entrar con ese rol
            User::factory()->create([
                'name' => "Empleado {$zone}",
                'email' => "empleado.{$slug}@demo.test",
                'team_id' => $team->id,
            ]);

            foreach (self::PEOPLE[$zone] as $name) {
                User::factory()->create([
                    'name' => $name,
                    'email' => $this->emailFor($name),
                    'team_id' => $team->id,
                ]);
            }

            return $team;
        });

        foreach ($teams as $team) {
            $members = $team->members()->where('role', 'empleado')->get();

            foreach ($members as $member) {
                foreach (range(1, rand(6, 12)) as $i) {
                    $this->createActivity($member, $team, [$team->leader_id, $gerente->id], $types->random(), $communities->random());
                }

                $this->createAttendance($member);
            }

            $this->createAttendance($team->leader);
        }

        // Guardar en orden de creación para que los folios ACT-0001… sigan la cronología
        foreach (collect($this->pending)->sortBy(fn ($a) => $a->created_at->timestamp) as $activity) {
            $activity->save();
        }

        $this->command?->info('Datos demo creados. Contraseña de todos los usuarios demo: password');
    }

    private function createActivity(User $member, Team $team, array $creators, int $typeId, int $communityId): void
    {

        // Reparto realista: la mayoría ya revisadas por un superior
        $status = fake()->randomElement([
            ActivityStatus::Completada, ActivityStatus::Completada, ActivityStatus::Completada, ActivityStatus::Completada,
            ActivityStatus::Incompleta, ActivityStatus::Incompleta,
            ActivityStatus::Pendiente, ActivityStatus::Pendiente, ActivityStatus::EnProgreso, ActivityStatus::Cancelada,
        ]);

        // 1 de cada 4 la carga el propio empleado; el resto su encargado o el gerente
        $selfAssigned = rand(1, 4) === 1;
        $creator = $selfAssigned ? $member->id : fake()->randomElement($creators);
        $marker = $selfAssigned ? $team->leader_id : $creator;

        // Creada en los últimos 45 días, en horario laboral
        $created = now()->subDays(rand(1, 45))->setTime(rand(8, 17), rand(0, 59));
        $start = $created->copy()->addDays(rand(0, 2))->startOfDay();
        $end = $start->copy()->addDays(rand(1, 10));
        $updated = $created->copy()->addHours(rand(1, 48));

        $completed = null;
        $mark = ['marked_by' => null, 'marked_at' => null, 'mark_notes' => null];

        if ($status->isMarked()) {
            $markedAt = now()->setTimestamp(rand($created->timestamp, now()->subHour()->timestamp));
            $completed = $status === ActivityStatus::Completada ? $markedAt : null;
            $updated = $markedAt;
            $mark = [
                'marked_by' => $marker,
                'marked_at' => $markedAt,
                'mark_notes' => $status === ActivityStatus::Incompleta ? fake()->randomElement([
                    'Falta la foto de evidencia.',
                    'El reporte está incompleto, faltan los hallazgos de la revisión.',
                    'Los datos del inventario no coinciden con el sistema.',
                    'No se presentó a la visita programada.',
                ]) : null,
            ];
        }

        if ($updated->isFuture()) {
            $updated = now()->subMinutes(rand(5, 300));
        }

        // Las revisadas casi siempre traen evidencia: texto o foto (las fotos demo no existen, se omiten)
        $evidenceText = $status->isMarked() || rand(1, 3) === 1 ? fake()->randomElement(self::EVIDENCE) : null;
        if ($status === ActivityStatus::Incompleta && rand(1, 2) === 1) {
            $evidenceText = null;
        }

        $activity = new Activity([
            'activity_type_id' => $typeId,
            'community_id' => $communityId,
            'assigned_to' => $member->id,
            'created_by' => $creator,
            'team_id' => $team->id,
            'status' => $status->value,
            'start_date' => $start->toDateString(),
            'end_date' => $end->toDateString(),
            'evidence_text' => $evidenceText,
            'completed_at' => $completed,
            ...$mark,
        ]);
        $activity->created_at = $created;
        $activity->updated_at = $updated;
        $this->pending[] = $activity;
    }

    private function createAttendance(User $member): void
    {
        foreach (range(0, 59) as $daysAgo) {
            $date = now()->subDays($daysAgo)->startOfDay();

            if ($date->isSunday() || rand(1, 100) > 93) {
                continue; // domingos y faltas ocasionales
            }

            // La mayoría llega a tiempo; algunos con retardo
            $in = rand(1, 100) > 12
                ? $date->copy()->setTime(8, 30)->addMinutes(rand(0, 38))
                : $date->copy()->setTime(9, 12)->addMinutes(rand(0, 35));

            if ($in->isFuture()) {
                continue;
            }

            Attendance::create([
                'user_id' => $member->id,
                'work_date' => $date->toDateString(),
                'check_in_at' => $in,
                'check_out_at' => $daysAgo === 0 ? null : $in->copy()->addHours(rand(7, 9))->addMinutes(rand(0, 59)),
            ]);
        }
    }

    private function emailFor(string $name): string
    {
        $ascii = strtolower(\Illuminate\Support\Str::ascii($name));
        $parts = explode(' ', $ascii);

        return $parts[0].'.'.end($parts).'@demo.test';
    }
}
