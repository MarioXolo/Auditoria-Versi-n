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
        $this->empleado = User::factory()->create(['team_id' => $this->team->id]);
        $this->otroEmpleado = User::factory()->create(); // sin equipo
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
        $activity = $this->activityFor($this->empleado, $this->encargado);
        Attendance::create(['user_id' => $this->empleado->id, 'work_date' => today()->toDateString(), 'check_in_at' => now()]);

        foreach (['admin', 'gerente'] as $who) {
            $u = $this->{$who === 'admin' ? 'admin' : 'gerente'};
            $this->actingAs($u)->get('/')->assertRedirect(route('dashboard'));
            $this->actingAs($u)->get('/dashboard')->assertOk()->assertSee('Dashboard de auditoría');
            $this->actingAs($u)->get('/dashboard?period=7&team_id='.$this->team->id)->assertOk();
            $this->actingAs($u)->get('/dashboard?period=all')->assertOk();
        }

        foreach ([$this->admin, $this->gerente, $this->encargado, $this->empleado] as $u) {
            $this->actingAs($u)->get('/activities')->assertOk();
            $this->actingAs($u)->get('/activities?status=vencida')->assertOk();
            $this->actingAs($u)->get("/activities/{$activity->id}")->assertOk()->assertSee('Comunidad Prueba')->assertSee($activity->code);
            $this->actingAs($u)->get('/asistencia')->assertOk();
            $this->actingAs($u)->get('/perfil')->assertOk();
        }

        foreach (['/users', '/users/create', "/users/{$this->empleado->id}/edit", '/teams', '/teams/create', "/teams/{$this->team->id}/edit", '/catalogos'] as $url) {
            $this->actingAs($this->admin)->get($url)->assertOk();
        }

        $this->actingAs($this->encargado)->get('/activities/create')->assertOk();
        $this->actingAs($this->encargado)->get("/activities/{$activity->id}/edit")->assertOk();
    }

    public function test_role_restrictions(): void
    {
        $this->actingAs($this->empleado)->get('/')->assertRedirect(route('activities.index'));
        foreach ([$this->encargado, $this->empleado] as $u) {
            $this->actingAs($u)->get('/dashboard')->assertForbidden();
            $this->actingAs($u)->get('/users')->assertForbidden();
            $this->actingAs($u)->get('/teams')->assertForbidden();
            $this->actingAs($u)->get('/catalogos')->assertForbidden();
            $this->actingAs($u)->post('/catalogos/tipos', ['name' => 'X'])->assertForbidden();
        }
        // Gerente: ve usuarios y administra equipos, pero no crea ni edita usuarios
        $this->actingAs($this->gerente)->get('/users')->assertOk()->assertDontSee('Nuevo usuario');
        $this->actingAs($this->gerente)->get('/users/create')->assertForbidden();
        $this->actingAs($this->gerente)->get("/users/{$this->empleado->id}/edit")->assertForbidden();
        $this->actingAs($this->gerente)->post('/users', ['name' => 'X', 'email' => 'x@x.mx', 'role' => 'admin', 'password' => 'secreto123', 'password_confirmation' => 'secreto123'])->assertForbidden();
        $this->actingAs($this->gerente)->patch("/users/{$this->empleado->id}/toggle")->assertForbidden();
        $this->actingAs($this->gerente)->get('/teams')->assertOk();
        $this->actingAs($this->gerente)->post('/teams', ['name' => 'Centro'])->assertRedirect(route('teams.index'));
        $this->assertDatabaseHas('teams', ['name' => 'Centro']);
        $this->actingAs($this->gerente)->get("/teams/{$this->team->id}/edit")->assertOk();
        // El empleado sí puede cargar actividades (solo para sí mismo)
        $this->actingAs($this->empleado)->get('/activities/create')->assertOk();

        // Empleado no ve actividades ajenas
        $ajena = $this->activityFor($this->otroEmpleado, $this->gerente);
        $this->actingAs($this->empleado)->get("/activities/{$ajena->id}")->assertForbidden();
        // Encargado no ve actividades fuera de su equipo
        $this->actingAs($this->encargado)->get("/activities/{$ajena->id}")->assertForbidden();
    }

    public function test_teammates_do_not_see_each_others_activities(): void
    {
        $companero = User::factory()->create(['team_id' => $this->team->id]);
        $mia = $this->activityFor($this->empleado, $this->encargado, 'Actividad de Ana');
        $deEl = $this->activityFor($companero, $this->encargado, 'Actividad de Beto');

        $this->actingAs($this->empleado)->get('/activities')->assertSee($mia->code)->assertDontSee($deEl->code);
        $this->actingAs($this->empleado)->get("/activities/{$deEl->id}")->assertForbidden();

        // Otro encargado no ve lo que asignó este encargado
        $otroEncargado = User::factory()->role('encargado')->create();
        $this->actingAs($otroEncargado)->get('/activities')->assertDontSee($mia->code);
        $this->actingAs($otroEncargado)->get("/activities/{$mia->id}")->assertForbidden();

        // Un encargado no puede asignar a empleados que no son de su equipo
        $this->actingAs($otroEncargado)->post('/activities', $this->payload([
            'assigned_to' => $this->empleado->id,
        ]))->assertSessionHasErrors('assigned_to');
    }

    public function test_encargado_only_sees_activities_he_assigned(): void
    {
        $suya = $this->activityFor($this->empleado, $this->encargado, 'Asignada por encargado');
        // El gerente asigna directo a un empleado del equipo del encargado
        $delGerente = $this->activityFor($this->empleado, $this->gerente, 'Asignada por gerente');
        $paraEncargado = $this->activityFor($this->encargado, $this->gerente, 'Tarea del encargado');

        $this->actingAs($this->encargado)->get("/activities/{$suya->id}")->assertOk();
        $this->actingAs($this->encargado)->get("/activities/{$paraEncargado->id}")->assertOk();
        $this->actingAs($this->encargado)->get("/activities/{$delGerente->id}")->assertForbidden();

        $this->actingAs($this->encargado)->get('/activities')
            ->assertSee($suya->code)
            ->assertSee($paraEncargado->code)
            ->assertDontSee($delGerente->code);

        // El empleado ve todas las suyas, sin importar quién las asignó
        $this->actingAs($this->empleado)->get('/activities')
            ->assertSee($suya->code)
            ->assertSee($delGerente->code)
            ->assertDontSee($paraEncargado->code);

        // El encargado no edita lo que no asignó
        $this->actingAs($this->encargado)->post("/activities/{$delGerente->id}/marcar", ['marca' => 'concluido'])->assertForbidden();
        $this->actingAs($this->encargado)->post("/activities/{$suya->id}/marcar", ['marca' => 'concluido'])->assertRedirect();
    }

    public function test_encargado_assigns_only_to_own_team(): void
    {
        $this->actingAs($this->encargado)->post('/activities', $this->payload(['assigned_to' => $this->empleado->id]))
            ->assertRedirect();
        $this->assertDatabaseHas('activities', ['activity_type_id' => $this->typeId(), 'assigned_to' => $this->empleado->id, 'team_id' => $this->team->id]);

        $this->actingAs($this->encargado)->post('/activities', $this->payload(['assigned_to' => $this->otroEmpleado->id]))
            ->assertSessionHasErrors('assigned_to');
    }

    public function test_evidence_upload_and_protected_download(): void
    {
        $this->actingAs($this->encargado)->post('/activities', $this->payload([
            'assigned_to' => $this->empleado->id,
            'evidence' => UploadedFile::fake()->create('foto.jpg', 200, 'image/jpeg'),
        ]))->assertRedirect();

        $a = Activity::latest('id')->first();
        $this->assertNotNull($a->evidence_path);
        $this->actingAs($this->empleado)->get("/activities/{$a->id}/evidence")->assertOk();
        $this->actingAs($this->otroEmpleado)->get("/activities/{$a->id}/evidence")->assertForbidden();
    }

    public function test_check_in_and_check_out(): void
    {
        $this->actingAs($this->empleado)->post('/asistencia/entrada', ['photo' => UploadedFile::fake()->create('selfie.jpg', 200, 'image/jpeg')])
            ->assertSessionHasNoErrors();
        $this->actingAs($this->empleado)->post('/asistencia/entrada', ['photo' => UploadedFile::fake()->create('selfie.jpg', 200, 'image/jpeg')])
            ->assertSessionHasErrors('photo');
        $this->actingAs($this->empleado)->post('/asistencia/salida')->assertSessionHasNoErrors();

        $att = Attendance::where('user_id', $this->empleado->id)->first();
        $this->assertNotNull($att->check_out_at);
        $this->actingAs($this->empleado)->get("/asistencia/{$att->id}/foto/entrada")->assertOk();
        $this->actingAs($this->gerente)->get("/asistencia/{$att->id}/foto/entrada")->assertOk();
        $this->actingAs($this->encargado)->get("/asistencia/{$att->id}/foto/entrada")->assertOk();
        $this->actingAs($this->otroEmpleado)->get("/asistencia/{$att->id}/foto/entrada")->assertForbidden();

        // Encargados ven la asistencia de su equipo; otro encargado no ve las fotos de este equipo
        $this->actingAs($this->encargado)->get('/asistencia')->assertOk()->assertSee('Asistencia del personal')->assertSee($this->empleado->name);
        $this->actingAs($this->gerente)->get('/asistencia')->assertOk()->assertSee('Asistencia del personal');
        $otroEncargado = User::factory()->role('encargado')->create();
        $this->actingAs($otroEncargado)->get("/asistencia/{$att->id}/foto/entrada")->assertForbidden();
        $this->actingAs($this->empleado)->get('/asistencia')->assertOk()->assertDontSee('Asistencia del personal');
    }

    public function test_admin_manages_users_and_teams(): void
    {
        $this->actingAs($this->admin)->post('/users', [
            'name' => 'Nuevo', 'email' => 'nuevo@test.mx', 'role' => 'empleado', 'team_id' => $this->team->id,
            'password' => 'secreto123', 'password_confirmation' => 'secreto123', 'active' => '1',
        ])->assertRedirect(route('users.index'));
        $this->assertDatabaseHas('users', ['email' => 'nuevo@test.mx', 'role' => 'empleado']);

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
