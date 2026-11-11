<?php

namespace Tests\Feature;

use App\Enums\ActivityStatus;
use App\Models\Activity;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ActivityMarkingTest extends TestCase
{
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

    private function selfLoaded(string $title = 'Visita a cliente propia'): Activity
    {
        $this->actingAs($this->empleado)->post('/activities', [
            'title' => $title, 'priority' => 'media',
            // aunque intente asignarla a otro, queda a su nombre
            'assigned_to' => $this->companero->id,
        ])->assertRedirect();

        return Activity::where('title', $title)->firstOrFail();
    }

    public function test_employee_loads_activities_only_for_himself(): void
    {
        $a = $this->selfLoaded();

        $this->assertSame($this->empleado->id, $a->assigned_to);
        $this->assertSame($this->empleado->id, $a->created_by);
        $this->assertSame($this->empleado->team_id, $a->team_id);
        $this->assertSame(ActivityStatus::Pendiente, $a->status);

        // No puede editarla, borrarla ni marcarla
        $this->actingAs($this->empleado)->get("/activities/{$a->id}/edit")->assertForbidden();
        $this->actingAs($this->empleado)->delete("/activities/{$a->id}")->assertForbidden();
        $this->actingAs($this->empleado)->post("/activities/{$a->id}/marcar", ['marca' => 'completada'])->assertForbidden();

        // Su compañero no la ve; su encargado sí; otro encargado no
        $this->actingAs($this->companero)->get("/activities/{$a->id}")->assertForbidden();
        $this->actingAs($this->encargado)->get('/activities')->assertSee('Visita a cliente propia');
        $this->actingAs($this->otroEncargado)->get("/activities/{$a->id}")->assertForbidden();
    }

    public function test_superiors_mark_with_checkbox_and_incomplete_turns_red(): void
    {
        $a = $this->selfLoaded();

        $this->actingAs($this->encargado)->post("/activities/{$a->id}/marcar", ['marca' => 'completada'])->assertRedirect();
        $a->refresh();
        $this->assertSame(ActivityStatus::Completada, $a->status);
        $this->assertSame($this->encargado->id, $a->marked_by);
        $this->assertNotNull($a->completed_at);

        // Incompleta: fila roja en el listado y comentario visible para el empleado
        $this->actingAs($this->gerente)->post("/activities/{$a->id}/marcar", ['marca' => 'incompleta', 'mark_notes' => 'Falta la evidencia'])->assertRedirect();
        $a->refresh();
        $this->assertSame(ActivityStatus::Incompleta, $a->status);
        $this->assertNull($a->completed_at);
        $this->assertSame($this->gerente->id, $a->marked_by);

        $this->actingAs($this->encargado)->get('/activities')->assertSee('bg-rose-100', false);
        $this->actingAs($this->empleado)->get("/activities/{$a->id}")->assertSee('Falta la evidencia')->assertSee('Incompleta');

        // Volver a tocar la marca la quita
        $this->actingAs($this->admin)->post("/activities/{$a->id}/marcar", ['marca' => 'sin_marcar'])->assertRedirect();
        $a->refresh();
        $this->assertSame(ActivityStatus::Pendiente, $a->status);
        $this->assertNull($a->marked_by);
    }

    public function test_nobody_marks_their_own_activity_and_employees_cannot_mark(): void
    {
        // Actividad del gerente para el encargado
        $this->actingAs($this->gerente)->post('/activities', [
            'title' => 'Reporte del encargado', 'assigned_to' => $this->encargado->id, 'priority' => 'media',
        ]);
        $a = Activity::where('title', 'Reporte del encargado')->firstOrFail();

        $this->actingAs($this->encargado)->post("/activities/{$a->id}/marcar", ['marca' => 'completada'])->assertForbidden();
        $this->actingAs($this->gerente)->post("/activities/{$a->id}/marcar", ['marca' => 'completada'])->assertRedirect();

        // El empleado no ve controles de marcado
        $b = $this->selfLoaded('Otra propia');
        $this->actingAs($this->empleado)->get('/activities')->assertDontSee('Marcar como completada');
        $this->actingAs($this->encargado)->get('/activities')->assertSee('Marcar como completada');
    }

    public function test_status_cannot_be_set_to_completed_through_edit(): void
    {
        $this->actingAs($this->encargado)->post('/activities', [
            'title' => 'Inventario', 'assigned_to' => $this->empleado->id, 'priority' => 'media', 'status' => 'completada',
        ]);
        $a = Activity::where('title', 'Inventario')->firstOrFail();
        $this->assertSame(ActivityStatus::Pendiente, $a->status);

        $this->actingAs($this->encargado)->put("/activities/{$a->id}", [
            'title' => 'Inventario', 'assigned_to' => $this->empleado->id, 'priority' => 'media', 'status' => 'completada',
        ])->assertSessionHasErrors('status');

        $this->actingAs($this->encargado)->put("/activities/{$a->id}", [
            'title' => 'Inventario', 'assigned_to' => $this->empleado->id, 'priority' => 'media', 'status' => 'cancelada',
        ])->assertRedirect();
        $this->assertSame(ActivityStatus::Cancelada, $a->fresh()->status);
    }
}
