<?php

namespace Tests\Feature;

use App\Models\Activity;
use App\Models\Attendance;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AppSmokeTest extends TestCase
{
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

    private function activityFor(User $assignee, User $creator): Activity
    {
        return Activity::create([
            'title' => 'Prueba', 'assigned_to' => $assignee->id, 'created_by' => $creator->id,
            'team_id' => $assignee->team_id, 'priority' => 'media', 'status' => 'pendiente',
            'due_date' => now()->subDay()->toDateString(),
        ]);
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
            $this->actingAs($u)->get("/activities/{$activity->id}")->assertOk()->assertSee('Prueba');
            $this->actingAs($u)->get('/asistencia')->assertOk();
            $this->actingAs($u)->get('/perfil')->assertOk();
        }

        foreach (['/users', '/users/create', "/users/{$this->empleado->id}/edit", '/teams', '/teams/create', "/teams/{$this->team->id}/edit"] as $url) {
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
        $this->actingAs($this->empleado)->get('/activities/create')->assertForbidden();

        // Empleado no ve actividades ajenas
        $ajena = $this->activityFor($this->otroEmpleado, $this->gerente);
        $this->actingAs($this->empleado)->get("/activities/{$ajena->id}")->assertForbidden();
        // Encargado no ve actividades fuera de su equipo
        $this->actingAs($this->encargado)->get("/activities/{$ajena->id}")->assertForbidden();
    }

    public function test_encargado_only_sees_activities_he_assigned(): void
    {
        $suya = $this->activityFor($this->empleado, $this->encargado);
        // El gerente asigna directo a un empleado del equipo del encargado
        $delGerente = $this->activityFor($this->empleado, $this->gerente);
        $paraEncargado = $this->activityFor($this->encargado, $this->gerente);
        $delGerente->update(['title' => 'Asignada por gerente']);
        $suya->update(['title' => 'Asignada por encargado']);
        $paraEncargado->update(['title' => 'Tarea del encargado']);

        $this->actingAs($this->encargado)->get("/activities/{$suya->id}")->assertOk();
        $this->actingAs($this->encargado)->get("/activities/{$paraEncargado->id}")->assertOk();
        $this->actingAs($this->encargado)->get("/activities/{$delGerente->id}")->assertForbidden();

        $this->actingAs($this->encargado)->get('/activities')
            ->assertSee('Asignada por encargado')
            ->assertSee('Tarea del encargado')
            ->assertDontSee('Asignada por gerente');

        // El empleado ve todas las suyas, sin importar quién las asignó
        $this->actingAs($this->empleado)->get('/activities')
            ->assertSee('Asignada por encargado')
            ->assertSee('Asignada por gerente')
            ->assertDontSee('Tarea del encargado');

        // El encargado no edita lo que no asignó
        $this->actingAs($this->encargado)->patch("/activities/{$delGerente->id}/status", ['status' => 'completada'])->assertForbidden();
        $this->actingAs($this->encargado)->patch("/activities/{$suya->id}/status", ['status' => 'completada'])->assertRedirect();
    }

    public function test_encargado_assigns_only_to_own_team(): void
    {
        $base = ['title' => 'Volanteo', 'priority' => 'alta', 'status' => 'pendiente'];

        $this->actingAs($this->encargado)->post('/activities', $base + ['assigned_to' => $this->empleado->id])
            ->assertRedirect();
        $this->assertDatabaseHas('activities', ['title' => 'Volanteo', 'assigned_to' => $this->empleado->id, 'team_id' => $this->team->id]);

        $this->actingAs($this->encargado)->post('/activities', $base + ['assigned_to' => $this->otroEmpleado->id])
            ->assertSessionHasErrors('assigned_to');
    }

    public function test_status_change_sets_completed_at(): void
    {
        $a = $this->activityFor($this->empleado, $this->encargado);
        $this->actingAs($this->encargado)->patch("/activities/{$a->id}/status", ['status' => 'completada'])->assertRedirect();
        $this->assertNotNull($a->fresh()->completed_at);
        $this->actingAs($this->empleado)->patch("/activities/{$a->id}/status", ['status' => 'pendiente'])->assertForbidden();
    }

    public function test_evidence_upload_and_protected_download(): void
    {
        $this->actingAs($this->encargado)->post('/activities', [
            'title' => 'Con evidencia', 'assigned_to' => $this->empleado->id, 'priority' => 'media',
            'status' => 'pendiente', 'evidence' => UploadedFile::fake()->create('foto.jpg', 200, 'image/jpeg'),
        ])->assertRedirect();

        $a = Activity::where('title', 'Con evidencia')->first();
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
        $this->actingAs($this->encargado)->get("/asistencia/{$att->id}/foto/entrada")->assertForbidden();
        $this->actingAs($this->otroEmpleado)->get("/asistencia/{$att->id}/foto/entrada")->assertForbidden();

        // Solo administradores y gerentes ven la asistencia del personal
        $this->actingAs($this->encargado)->get('/asistencia')->assertOk()->assertDontSee('Asistencia del personal');
        $this->actingAs($this->gerente)->get('/asistencia')->assertOk()->assertSee('Asistencia del personal');
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
