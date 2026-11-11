<?php

namespace Tests\Feature;

use App\Enums\ActivityStatus;
use App\Models\Activity;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\MakesActivities;
use Tests\TestCase;

class ActivityMarkingTest extends TestCase
{
    use MakesActivities;
    use RefreshDatabase;

    private User $admin;
    private User $gerente;
    private User $encargado;
    private User $otroEncargado;
    private User $empleado;
    private User $companero;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->role('admin')->create();
        $this->gerente = User::factory()->role('gerente')->create();
        $this->encargado = User::factory()->role('encargado')->create();
        $this->otroEncargado = User::factory()->role('encargado')->create();
        $team = Team::create(['name' => 'Norte', 'leader_id' => $this->encargado->id]);
        $this->encargado->update(['team_id' => $team->id]);
        $this->empleado = User::factory()->create(['team_id' => $team->id]);
        $this->companero = User::factory()->create(['team_id' => $team->id]);
    }

    private function selfLoaded(string $community = 'Visita a cliente propia'): Activity
    {
        $this->actingAs($this->empleado)->post('/activities', $this->payload([
            'community_id' => $this->communityId($community),
            // aunque intente asignarla a otro, queda a su nombre
            'assigned_to' => $this->companero->id,
        ]))->assertRedirect();

        return Activity::latest('id')->firstOrFail();
    }

    public function test_employee_loads_activities_only_for_himself(): void
    {
        $a = $this->selfLoaded();

        $this->assertSame($this->empleado->id, $a->assigned_to);
        $this->assertSame($this->empleado->id, $a->created_by);
        $this->assertSame($this->empleado->team_id, $a->team_id);
        $this->assertSame(ActivityStatus::NoIniciado, $a->status);

        // No puede editarla, borrarla ni marcarla
        $this->actingAs($this->empleado)->get("/activities/{$a->id}/edit")->assertForbidden();
        $this->actingAs($this->empleado)->delete("/activities/{$a->id}")->assertForbidden();
        $this->actingAs($this->empleado)->post("/activities/{$a->id}/marcar", ['marca' => 'concluido'])->assertForbidden();

        // Su compañero no la ve; su encargado sí; otro encargado no
        $this->actingAs($this->companero)->get("/activities/{$a->id}")->assertForbidden();
        $this->actingAs($this->encargado)->get('/activities')->assertSee('Visita a cliente propia');
        $this->actingAs($this->otroEncargado)->get("/activities/{$a->id}")->assertForbidden();
    }

    public function test_superiors_mark_with_checkbox_and_incomplete_turns_red(): void
    {
        $a = $this->selfLoaded();

        $this->actingAs($this->encargado)->post("/activities/{$a->id}/marcar", ['marca' => 'concluido'])->assertRedirect();
        $a->refresh();
        $this->assertSame(ActivityStatus::Concluido, $a->status);
        $this->assertSame($this->encargado->id, $a->marked_by);
        $this->assertNotNull($a->completed_at);

        // Incompleta: fila roja en el listado y comentario visible para el empleado
        $this->actingAs($this->gerente)->post("/activities/{$a->id}/marcar", ['marca' => 'no_completada', 'mark_notes' => 'Falta la evidencia'])->assertRedirect();
        $a->refresh();
        $this->assertSame(ActivityStatus::NoCompletada, $a->status);
        $this->assertNull($a->completed_at);
        $this->assertSame($this->gerente->id, $a->marked_by);

        $this->actingAs($this->encargado)->get('/activities')->assertSee('bg-rose-100', false);
        $this->actingAs($this->empleado)->get("/activities/{$a->id}")->assertSee('Falta la evidencia')->assertSee('No completada');

        // Volver a tocar la marca la quita
        $this->actingAs($this->admin)->post("/activities/{$a->id}/marcar", ['marca' => 'sin_marcar'])->assertRedirect();
        $a->refresh();
        $this->assertSame(ActivityStatus::EnProceso, $a->status);
        $this->assertNull($a->marked_by);
    }

    public function test_nobody_marks_their_own_activity_and_employees_cannot_mark(): void
    {
        // Actividad del gerente para el encargado
        $this->actingAs($this->gerente)->post('/activities', $this->payload([
            'assigned_to' => $this->encargado->id,
        ]))->assertRedirect();
        $a = Activity::latest('id')->firstOrFail();

        $this->actingAs($this->encargado)->post("/activities/{$a->id}/marcar", ['marca' => 'concluido'])->assertForbidden();
        $this->actingAs($this->gerente)->post("/activities/{$a->id}/marcar", ['marca' => 'concluido'])->assertRedirect();

        // El empleado no ve controles de marcado
        $b = $this->selfLoaded('Otra propia');
        $this->actingAs($this->empleado)->get('/activities')->assertDontSee('Marcar como concluido');
        $this->actingAs($this->encargado)->get('/activities')->assertSee('Marcar como concluido');
    }

    public function test_status_cannot_be_set_to_completed_through_edit(): void
    {
        $data = $this->payload(['assigned_to' => $this->empleado->id, 'status' => 'concluido']);
        $this->actingAs($this->encargado)->post('/activities', $data)->assertRedirect();
        $a = Activity::latest('id')->firstOrFail();
        $this->assertSame(ActivityStatus::NoIniciado, $a->status);

        $this->actingAs($this->encargado)->put("/activities/{$a->id}", $data)->assertSessionHasErrors('status');

        $this->actingAs($this->encargado)->put("/activities/{$a->id}", ['status' => 'suspendida'] + $data)->assertRedirect();
        $this->assertSame(ActivityStatus::Suspendida, $a->fresh()->status);
    }

    public function test_assigned_employee_changes_manual_status_and_green_sets_concluido(): void
    {
        $a = $this->makeActivity(['assigned_to' => $this->empleado->id, 'created_by' => $this->encargado->id, 'team_id' => $this->empleado->team_id]);

        // Las tres opciones manuales
        $this->actingAs($this->empleado)->get("/activities/{$a->id}")
            ->assertSee('No iniciado')->assertSee('En proceso')->assertSee('Suspendida');
        $this->actingAs($this->empleado)->patch("/activities/{$a->id}/estado", ['status' => 'en_proceso'])->assertRedirect();
        $this->assertSame(ActivityStatus::EnProceso, $a->fresh()->status);

        // Concluido y No completada no se eligen a mano
        $this->actingAs($this->empleado)->patch("/activities/{$a->id}/estado", ['status' => 'concluido'])->assertSessionHasErrors('status');
        $this->actingAs($this->encargado)->patch("/activities/{$a->id}/estado", ['status' => 'no_completada'])->assertSessionHasErrors('status');

        // Un compañero no puede cambiarlo
        $this->actingAs($this->companero)->patch("/activities/{$a->id}/estado", ['status' => 'suspendida'])->assertForbidden();

        // Verde: Concluido, y cuenta en el dashboard
        $this->actingAs($this->encargado)->post("/activities/{$a->id}/marcar", ['marca' => 'concluido'])->assertRedirect();
        $this->assertSame(ActivityStatus::Concluido, $a->fresh()->status);
        $this->actingAs($this->empleado)->get("/activities/{$a->id}")->assertSee('Concluido');
        $metrics = new \App\Services\DashboardMetrics('30', null);
        $this->assertSame(1, $metrics->summary()['now']['completed']);

        // Ya marcada, el empleado no la cambia
        $this->actingAs($this->empleado)->patch("/activities/{$a->id}/estado", ['status' => 'en_proceso'])->assertForbidden();

        // Rojo: No completada
        $this->actingAs($this->encargado)->post("/activities/{$a->id}/marcar", ['marca' => 'no_completada'])->assertRedirect();
        $this->assertSame(ActivityStatus::NoCompletada, $a->fresh()->status);
        $this->assertSame(1, (new \App\Services\DashboardMetrics('30', null))->summary()['now']['incompletas']);
    }
}
