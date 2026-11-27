<?php

namespace Tests\Feature;

use App\Models\Activity;
use App\Models\Attendance;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\MakesActivities;
use Tests\TestCase;

class AppSmokeTest extends TestCase
{
    use MakesActivities;
    use RefreshDatabase;

    private User $admin;
    private User $gerente;
    private User $encargado;
    private User $empleado;
    private User $otroEmpleado;
    private Team $team;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');

        $this->admin = User::factory()->role('admin')->create();
        $this->gerente = User::factory()->role('gerente')->create();
        $this->encargado = User::factory()->role('encargado')->create();
        $this->team = Team::create(['name' => 'Norte', 'leader_id' => $this->encargado->id]);
        $this->encargado->update(['team_id' => $this->team->id]);
        // Versión 3: solo admin, gerente y encargado. «otroEmpleado» es un encargado sin equipo.
        $this->empleado = User::factory()->role('encargado')->create(['team_id' => $this->team->id]);
        $this->otroEmpleado = User::factory()->role('encargado')->create();
    }

    private function activityFor(User $assignee, User $creator, string $community = 'Comunidad Prueba'): Activity
    {
        return $this->makeActivity([
            'assigned_to' => $assignee->id, 'created_by' => $creator->id, 'team_id' => $assignee->team_id,
        ], $community);
    }

    public function test_login_page_and_guest_redirect(): void
    {
        $this->get('/login')->assertOk()->assertSee('Entrar');
        $this->get('/dashboard')->assertRedirect('/login');
    }

    public function test_login_works_and_inactive_user_is_rejected(): void
    {
        $this->post('/login', ['email' => $this->empleado->email, 'password' => 'password'])
            ->assertRedirect(route('home'));
        $this->post('/logout');

        $this->empleado->update(['active' => false]);
        $this->post('/login', ['email' => $this->empleado->email, 'password' => 'password'])
            ->assertSessionHasErrors('email');
    }

    public function test_every_page_renders_for_each_role(): void
    {
        $activity = $this->activityFor($this->encargado, $this->gerente);
        Attendance::create(['user_id' => $this->encargado->id, 'work_date' => today()->toDateString(), 'check_in_at' => now()]);

        foreach ([$this->admin, $this->gerente] as $u) {
            $this->actingAs($u)->get('/')->assertRedirect(route('dashboard'));
            $this->actingAs($u)->get('/dashboard')->assertOk()->assertSee('Dashboard de auditoría')->assertDontSee('Asistencia y puntualidad');
            $this->actingAs($u)->get('/dashboard?period=7&team_id='.$this->team->id)->assertOk();
            $this->actingAs($u)->get('/dashboard?period=all')->assertOk();
        }

        // El encargado también llega a un dashboard: el suyo
        $this->actingAs($this->encargado)->get('/')->assertRedirect(route('dashboard'));
        $this->actingAs($this->encargado)->get('/dashboard')->assertOk()->assertSee('Mi dashboard')->assertDontSee('Dashboard de auditoría');

        foreach ([$this->admin, $this->gerente, $this->encargado] as $u) {
            $this->actingAs($u)->get('/activities')->assertOk();
            $this->actingAs($u)->get('/activities?status=vencida')->assertOk();
            $this->actingAs($u)->get("/activities/{$activity->id}")->assertOk()->assertSee('Comunidad Prueba')->assertSee($activity->code);
            $this->actingAs($u)->get('/perfil')->assertOk();
        }

        foreach (['/asistencia', '/users', '/users/create', "/users/{$this->encargado->id}/edit", '/teams', '/teams/create', "/teams/{$this->team->id}/edit", '/catalogos'] as $url) {
            $this->actingAs($this->admin)->get($url)->assertOk();
        }

        $this->actingAs($this->encargado)->get('/activities/create')->assertOk();
        $this->actingAs($this->gerente)->get("/activities/{$activity->id}/edit")->assertOk();
    }

    public function test_role_restrictions(): void
    {
        // Solo el administrador ve el checador; no aparece en el menú de los demás
        foreach ([$this->gerente, $this->encargado] as $u) {
            $this->actingAs($u)->get('/asistencia')->assertForbidden();
            $this->actingAs($u)->post('/asistencia/registrar')->assertForbidden();
            $this->actingAs($u)->get('/activities')->assertDontSee(route('attendance.index'), false);
        }
        $this->actingAs($this->admin)->get('/activities')->assertSee(route('attendance.index'), false);

        $this->actingAs($this->encargado)->get('/users')->assertForbidden();
        $this->actingAs($this->encargado)->get('/teams')->assertForbidden();
        $this->actingAs($this->encargado)->get('/catalogos')->assertForbidden();
        $this->actingAs($this->encargado)->get('/dashboard/exportar')->assertForbidden();
        $this->actingAs($this->encargado)->post('/catalogos/tipos', ['name' => 'X'])->assertForbidden();

        // Gerente: ve usuarios y administra equipos, pero no crea ni edita usuarios
        $this->actingAs($this->gerente)->get('/users')->assertOk()->assertDontSee('Nuevo usuario');
        $this->actingAs($this->gerente)->get('/users/create')->assertForbidden();
        $this->actingAs($this->gerente)->get("/users/{$this->encargado->id}/edit")->assertForbidden();
        $this->actingAs($this->gerente)->post('/users', ['name' => 'X', 'email' => 'x@x.mx', 'role' => 'admin', 'password' => 'secreto123', 'password_confirmation' => 'secreto123'])->assertForbidden();
        $this->actingAs($this->gerente)->patch("/users/{$this->encargado->id}/toggle")->assertForbidden();
        $this->actingAs($this->gerente)->get('/teams')->assertOk();
        $this->actingAs($this->gerente)->post('/teams', ['name' => 'Centro'])->assertRedirect(route('teams.index'));
        $this->assertDatabaseHas('teams', ['name' => 'Centro']);
        $this->actingAs($this->gerente)->get("/teams/{$this->team->id}/edit")->assertOk();
    }

    public function test_encargados_do_not_see_each_others_activities(): void
    {
        $companero = User::factory()->role('encargado')->create(['team_id' => $this->team->id]);
        $mia = $this->activityFor($this->encargado, $this->gerente, 'Actividad de Ana');
        $deEl = $this->activityFor($companero, $this->gerente, 'Actividad de Beto');
        $propia = $this->activityFor($this->encargado, $this->encargado, 'Me la cargué');

        $this->actingAs($this->encargado)->get('/activities')->assertSee($mia->code)->assertSee($propia->code)->assertDontSee($deEl->code);
        $this->actingAs($this->encargado)->get("/activities/{$deEl->id}")->assertForbidden();
        $this->actingAs($companero)->get("/activities/{$mia->id}")->assertForbidden();
        $this->actingAs($companero)->get("/activities/{$propia->id}")->assertForbidden();
        $this->actingAs($companero)->get('/calendario')->assertOk()->assertDontSee($mia->code);

        // El gerente ve todas
        $this->actingAs($this->gerente)->get('/activities')->assertSee($mia->code)->assertSee($deEl->code)->assertSee($propia->code);
    }

    public function test_encargado_only_loads_activities_for_himself(): void
    {
        $this->actingAs($this->encargado)->get('/activities/create')->assertOk()->assertDontSee('name="assigned_to"', false);
        $this->actingAs($this->encargado)->post('/activities', $this->payload(['assigned_to' => $this->otroEmpleado->id]))->assertRedirect();
        $this->assertDatabaseHas('activities', ['assigned_to' => $this->encargado->id, 'created_by' => $this->encargado->id, 'folio_group' => $this->encargado->folio_number]);
        $this->assertDatabaseMissing('activities', ['assigned_to' => $this->otroEmpleado->id]);
    }

    public function test_evidence_upload_and_protected_download(): void
    {
        $this->actingAs($this->encargado)->post('/activities', $this->payload([
            'evidence' => UploadedFile::fake()->create('foto.jpg', 200, 'image/jpeg'),
        ]))->assertRedirect();

        $a = Activity::latest('id')->first();
        $this->assertNotNull($a->evidence_path);
        $this->actingAs($this->encargado)->get("/activities/{$a->id}/evidence")->assertOk();
        $this->actingAs($this->gerente)->get("/activities/{$a->id}/evidence")->assertOk();
        $this->actingAs($this->otroEmpleado)->get("/activities/{$a->id}/evidence")->assertForbidden();
    }

    public function test_check_in_and_check_out(): void
    {
        $this->travelTo(now()->setTimezone('America/Mexico_City')->next('Wednesday')->setTime(9, 5));

        // Solo el administrador usa el checador: un solo botón, sin foto
        $this->actingAs($this->admin)->get('/asistencia')->assertOk()->assertSee('Registrar entrada')->assertDontSee('Tomar foto');
        $this->actingAs($this->admin)->post('/asistencia/registrar')->assertSessionHasNoErrors()->assertSessionHas('status', 'Entrada registrada a las 09:05.');
        $this->actingAs($this->admin)->get('/asistencia')->assertSee('Registrar salida');

        $this->travel(8)->hours();
        $this->actingAs($this->admin)->post('/asistencia/registrar', ['notes' => 'Termino en campo'])->assertSessionHasNoErrors();
        $att = Attendance::where('user_id', $this->admin->id)->first();
        $this->assertSame('09:05', $att->check_in_at->format('H:i'));
        $this->assertSame('17:05', $att->check_out_at->format('H:i'));
        $this->assertNull($att->check_in_photo);
        $this->assertSame('Termino en campo', $att->notes);
        $this->actingAs($this->admin)->get('/asistencia')->assertSee('Jornada completa');

        // Un tercer registro el mismo día se rechaza
        $this->actingAs($this->admin)->post('/asistencia/registrar')->assertSessionHasErrors('asistencia');
        $this->assertSame(1, Attendance::count());

        // Sábado y domingo no se puede checar
        Attendance::query()->delete();
        foreach (['Saturday', 'Sunday'] as $day) {
            $this->travelTo(now()->next($day)->setTime(10, 0));
            $this->actingAs($this->admin)->get('/asistencia')->assertSee('Hoy no es día laboral')->assertDontSee('Registrar entrada');
            $this->actingAs($this->admin)->post('/asistencia/registrar')->assertSessionHasErrors('asistencia');
        }
        $this->assertSame(0, Attendance::count());

        // El registro del checador no mueve ningún indicador del dashboard
        $this->travelBack();
        Attendance::create(['user_id' => $this->encargado->id, 'work_date' => today()->toDateString(), 'check_in_at' => now()]);
        $kpis = (new \App\Services\DashboardMetrics('30'))->summary()['now'];
        $this->assertArrayNotHasKey('asistencia', $kpis);
        $this->assertArrayNotHasKey('puntualidad', $kpis);

        // Las fotos de registros anteriores las ve solo el administrador
        Storage::disk('local')->put('asistencia/vieja.jpg', 'x');
        $old = Attendance::first();
        $old->update(['check_in_photo' => 'asistencia/vieja.jpg']);
        $this->actingAs($this->admin)->get("/asistencia/{$old->id}/foto/entrada")->assertOk();
        $this->actingAs($this->gerente)->get("/asistencia/{$old->id}/foto/entrada")->assertForbidden();
        $this->actingAs($this->encargado)->get("/asistencia/{$old->id}/foto/entrada")->assertForbidden();

        // El administrador ve la asistencia de gerentes y encargados
        $this->actingAs($this->admin)->get('/asistencia')->assertOk()->assertSee('Asistencia del personal')->assertSee($this->encargado->name);
    }

    public function test_admin_manages_users_and_teams(): void
    {
        $this->actingAs($this->admin)->get('/users/create')->assertOk()->assertDontSee('value="empleado"', false);
        $this->actingAs($this->admin)->post('/users', [
            'name' => 'Nuevo', 'email' => 'nuevo@test.mx', 'role' => 'encargado', 'team_id' => $this->team->id,
            'password' => 'secreto123', 'password_confirmation' => 'secreto123', 'active' => '1',
        ])->assertRedirect(route('users.index'));
        $this->assertDatabaseHas('users', ['email' => 'nuevo@test.mx', 'role' => 'encargado']);

        // Ya no se pueden crear empleados
        $this->actingAs($this->admin)->post('/users', [
            'name' => 'Otro', 'email' => 'otro@test.mx', 'role' => 'empleado',
            'password' => 'secreto123', 'password_confirmation' => 'secreto123', 'active' => '1',
        ])->assertSessionHasErrors('role');

        $this->actingAs($this->admin)->patch("/users/{$this->admin->id}/toggle")->assertSessionHasErrors();
        $this->assertTrue($this->admin->fresh()->active);

        $this->actingAs($this->admin)->post('/teams', ['name' => 'Sur'])->assertRedirect(route('teams.index'));
        $this->assertDatabaseHas('teams', ['name' => 'Sur']);
    }

    public function test_demo_seeder_runs(): void
    {
        $this->seed(\Database\Seeders\DemoSeeder::class);
        $this->assertGreaterThan(5, Activity::count());
        $this->actingAs($this->admin)->get('/dashboard')->assertOk();
    }
}
