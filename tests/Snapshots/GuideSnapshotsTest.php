<?php

namespace Tests\Snapshots;

use App\Models\Activity;
use App\Models\Attendance;
use App\Models\User;
use Database\Seeders\DemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Guarda el HTML de cada pantalla, por rol, para armar la guía de uso.
 * Solo corre a propósito: SNAPSHOTS=1 php artisan test --filter=GuideSnapshots
 */
class GuideSnapshotsTest extends TestCase
{
    use RefreshDatabase;

    private string $dir;

    public function test_capture_guide_screens(): void
    {
        if (! getenv('SNAPSHOTS')) {
            $this->markTestSkipped('Solo con SNAPSHOTS=1');
        }

        $this->dir = base_path('docs/capturas-html');
        @mkdir($this->dir, 0775, true);

        User::factory()->role('admin')->create(['name' => 'Administrador General', 'email' => 'admin@demo.test']);
        $this->seed(DemoSeeder::class);

        $admin = User::where('email', 'admin@demo.test')->first();
        $gerente = User::where('email', 'gerente@demo.test')->first();
        $encargado = User::where('email', 'encargado.norte@demo.test')->first();
        $empleado = User::where('email', 'empleado.norte@demo.test')->first();

        $this->snap('01-login', $this->get('/login'));

        $this->actingAs($admin);
        $this->snap('02-admin-dashboard', $this->get('/dashboard'));
        $this->snap('03-admin-usuarios', $this->get('/users'));
        $this->snap('04-admin-usuario-nuevo', $this->get('/users/create'));
        $this->snap('05-admin-equipos', $this->get('/teams'));
        $this->snap('06-admin-equipo-editar', $this->get('/teams/'.$encargado->team_id.'/edit'));

        $this->actingAs($gerente);
        $this->snap('07-gerente-dashboard', $this->get('/dashboard?period=30'));
        $this->snap('08-gerente-actividades', $this->get('/activities'));
        $this->snap('09-gerente-vencidas', $this->get('/activities?status=vencida'));

        $this->actingAs($encargado);
        $this->snap('10-encargado-actividades', $this->get('/activities'));
        $this->snap('11-encargado-nueva-actividad', $this->get('/activities/create'));
        $created = $this->followingRedirects()->post('/activities', [
            'title' => 'Auditoría de sucursal Centro',
            'description' => "Revisar inventario, caja y orden de la sucursal Centro.\nLevantar evidencia fotográfica de cada área.",
            'assigned_to' => $empleado->id,
            'priority' => 'alta',
            'status' => 'pendiente',
            'due_date' => now()->addDays(2)->toDateString(),
        ]);
        $this->snap('12-encargado-actividad-creada', $created);
        $this->snap('13-encargado-asistencia', $this->get('/asistencia'));
        $nueva = Activity::where('title', 'Auditoría de sucursal Centro')->first();
        $this->post('/activities/'.$nueva->id.'/marcar', ['marca' => 'incompleta', 'mark_notes' => 'Faltan las fotos del almacén.']);
        $this->snap('19-encargado-marcada-incompleta', $this->get('/activities/'.$nueva->id));
        $this->snap('20-encargado-lista-marcado', $this->get('/activities'));

        Attendance::where('user_id', $empleado->id)->whereDate('work_date', today())->delete();
        $this->actingAs($empleado);
        $this->snap('14-empleado-actividades', $this->get('/activities'));
        $activity = Activity::where('title', 'Auditoría de sucursal Centro')->first();
        $this->snap('15-empleado-detalle', $this->get('/activities/'.$activity->id));
        $this->snap('16-empleado-asistencia', $this->get('/asistencia'));
        $this->snap('21-empleado-cargar-actividad', $this->get('/activities/create'));
        Attendance::create([
            'user_id' => $empleado->id,
            'work_date' => today()->toDateString(),
            'check_in_at' => today()->setTime(8, 47),
        ]);
        $this->snap('17-empleado-asistencia-registrada', $this->get('/asistencia'));
        $this->snap('18-empleado-sin-permiso', $this->get('/dashboard'));

        $this->assertFileExists($this->dir.'/01-login.html');
    }

    private function snap(string $name, $response): void
    {
        file_put_contents($this->dir."/{$name}.html", $response->getContent());
    }
}
