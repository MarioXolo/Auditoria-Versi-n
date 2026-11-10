<?php

namespace Database\Seeders;

use App\Enums\ActivityStatus;
use App\Enums\Priority;
use App\Enums\ValidationStatus;
use App\Models\Activity;
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

    /** Actividades típicas con su descripción. */
    private const ACTIVITIES = [
        'Recorrido por colonia asignada' => 'Visitar los domicilios de la colonia asignada y registrar comentarios de los vecinos.',
        'Entrega de volantes' => 'Repartir volantes en cruceros y zonas comerciales del sector.',
        'Reunión con líderes vecinales' => 'Reunión para conocer necesidades de la zona y acordar próximos pasos.',
        'Registro de simpatizantes' => 'Capturar datos de contacto de simpatizantes con su autorización.',
        'Colocación de lonas' => 'Colocar lonas en los puntos autorizados y tomar foto de cada una.',
        'Reporte semanal de avance' => 'Entregar el resumen de actividades realizadas en la semana.',
        'Visita a mercado municipal' => 'Recorrer los locales del mercado y platicar con los comerciantes.',
        'Seguimiento de solicitudes ciudadanas' => 'Dar respuesta a las solicitudes recibidas en recorridos anteriores.',
    ];

    public function run(): void
    {
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
                'description' => "Personal de campo de la zona {$slug}.",
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
                    $this->createActivity($member, $team, [$team->leader_id, $gerente->id]);
                }

                $this->createAttendance($member);
            }

            $this->createAttendance($team->leader);
        }

        $this->command?->info('Datos demo creados. Contraseña de todos los usuarios demo: password');
    }

    private function createActivity(User $member, Team $team, array $creators): void
    {
        $title = array_rand(self::ACTIVITIES);
        $status = fake()->randomElement(ActivityStatus::cases());

        // Creada en los últimos 45 días, en horario laboral
        $created = now()->subDays(rand(1, 45))->setTime(rand(8, 17), rand(0, 59));
        $due = $created->copy()->addDays(rand(2, 12))->startOfDay();

        $completed = null;
        $updated = $created->copy()->addHours(rand(1, 48));

        if ($status === ActivityStatus::Completada) {
            $completed = now()->setTimestamp(rand($created->timestamp, now()->subHour()->timestamp));
            $updated = $completed;
        }

        if ($updated->isFuture()) {
            $updated = now()->subMinutes(rand(5, 300));
        }

        $creator = fake()->randomElement($creators);

        // Validación: la mayoría de las completadas ya revisadas; algunas en progreso fueron rechazadas antes
        $validation = ['validation_status' => null, 'validated_by' => null, 'validated_at' => null, 'validation_notes' => null];
        if ($status === ActivityStatus::Completada) {
            $reviewedAt = $completed->copy()->addHours(rand(2, 30));
            $validation = rand(1, 100) <= 70 && $reviewedAt->isPast()
                ? ['validation_status' => ValidationStatus::Validada->value, 'validated_by' => $creator, 'validated_at' => $reviewedAt, 'validation_notes' => null]
                : ['validation_status' => ValidationStatus::Pendiente->value, 'validated_by' => null, 'validated_at' => null, 'validation_notes' => null];
        } elseif ($status === ActivityStatus::EnProgreso && rand(1, 100) <= 20) {
            $validation = [
                'validation_status' => ValidationStatus::Rechazada->value,
                'validated_by' => $creator,
                'validated_at' => $updated,
                'validation_notes' => fake()->randomElement([
                    'Falta la foto de evidencia.',
                    'El reporte está incompleto, agrega los domicilios visitados.',
                    'Las lonas no están en los puntos autorizados.',
                ]),
            ];
        }

        $activity = new Activity([
            'title' => $title,
            'description' => self::ACTIVITIES[$title],
            'assigned_to' => $member->id,
            'created_by' => $creator,
            'team_id' => $team->id,
            'priority' => fake()->randomElement(Priority::cases())->value,
            'status' => $status->value,
            'due_date' => $due->toDateString(),
            'completed_at' => $completed,
            ...$validation,
        ]);
        $activity->created_at = $created;
        $activity->updated_at = $updated;
        $activity->save();
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
