<?php

namespace Database\Seeders;

use App\Enums\ActivityStatus;
use App\Enums\Priority;
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
    public function run(): void
    {
        $gerente = User::factory()->role('gerente')->create([
            'name' => 'Gerente Demo',
            'email' => 'gerente@demo.test',
        ]);

        $teams = collect(['Equipo Norte', 'Equipo Sur'])->map(function (string $name) {
            $leader = User::factory()->role('encargado')->create([
                'name' => 'Encargado '.str_replace('Equipo ', '', $name),
                'email' => 'encargado.'.strtolower(str_replace('Equipo ', '', $name)).'@demo.test',
            ]);

            $team = Team::create(['name' => $name, 'leader_id' => $leader->id]);
            $leader->update(['team_id' => $team->id]);

            $slug = strtolower(str_replace('Equipo ', '', $name));

            // Un empleado con correo fijo para poder entrar con ese rol
            User::factory()->create([
                'name' => 'Empleado '.str_replace('Equipo ', '', $name),
                'email' => "empleado.{$slug}@demo.test",
                'team_id' => $team->id,
            ]);
            User::factory()->count(3)->create(['team_id' => $team->id]);

            return $team;
        });

        $titles = [
            'Recorrido por colonia asignada',
            'Entrega de volantes',
            'Reunión con líderes vecinales',
            'Registro de simpatizantes',
            'Colocación de lonas',
            'Reporte semanal de avance',
            'Visita a mercado municipal',
            'Seguimiento de solicitudes ciudadanas',
        ];

        foreach ($teams as $team) {
            $members = $team->members()->where('role', 'empleado')->get();

            foreach ($members as $member) {
                foreach (range(1, rand(3, 7)) as $i) {
                    $status = fake()->randomElement(ActivityStatus::cases());
                    $due = now()->addDays(rand(-10, 10));

                    Activity::create([
                        'title' => fake()->randomElement($titles),
                        'description' => fake()->sentence(12),
                        'assigned_to' => $member->id,
                        'created_by' => fake()->randomElement([$team->leader_id, $gerente->id]),
                        'team_id' => $team->id,
                        'priority' => fake()->randomElement(Priority::cases())->value,
                        'status' => $status->value,
                        'due_date' => $due->toDateString(),
                        'completed_at' => $status === ActivityStatus::Completada ? $due->copy()->subDay() : null,
                    ]);
                }

                foreach (range(0, 6) as $daysAgo) {
                    if (rand(1, 10) > 8) {
                        continue; // simula faltas
                    }
                    $date = now()->subDays($daysAgo)->startOfDay();
                    $in = $date->copy()->setTime(8, rand(30, 59));

                    Attendance::create([
                        'user_id' => $member->id,
                        'work_date' => $date->toDateString(),
                        'check_in_at' => $in,
                        'check_out_at' => $daysAgo === 0 ? null : $in->copy()->addHours(rand(7, 9)),
                    ]);
                }
            }
        }

        $this->command?->info('Datos demo creados. Contraseña de todos los usuarios demo: password');
    }
}
