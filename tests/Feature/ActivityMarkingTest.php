<?php

namespace Tests\Feature;

use App\Enums\ActivityStatus;
use App\Models\Activity;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\MakesActivities;
use Tests\TestCase;

/** Versión 3: el encargado se carga sus actividades; gerentes y administradores las revisan. */
class ActivityMarkingTest extends TestCase
{
    use MakesActivities;
    use RefreshDatabase;

    private User $admin;
    private User $gerente;
    private User $encargado;
    private User $otroEncargado;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->role('admin')->create();
        $this->gerente = User::factory()->role('gerente')->create();
        $this->encargado = User::factory()->role('encargado')->create();
        $this->otroEncargado = User::factory()->role('encargado')->create();
        $team = Team::create(['name' => 'Norte', 'leader_id' => $this->encargado->id]);
        $this->encargado->update(['team_id' => $team->id]);
        $this->otroEncargado->update(['team_id' => $team->id]);
    }

    private function selfLoaded(string $community = 'Visita a cliente propia'): Activity
    {
        $this->actingAs($this->encargado)->post('/activities', $this->payload([
            'community_id' => $this->communityId($community),
            // aunque intente asignarla a otro, queda a su nombre
            'assigned_to' => $this->otroEncargado->id,
        ]))->assertRedirect();

        return Activity::latest('id')->firstOrFail();
    }

    public function test_encargado_loads_activities_only_for_himself_and_others_cannot_see_them(): void
    {
        $a = $this->selfLoaded();

        $this->assertSame($this->encargado->id, $a->assigned_to);
        $this->assertSame($this->encargado->id, $a->created_by);
        $this->assertSame($this->encargado->team_id, $a->team_id);
        $this->assertSame(ActivityStatus::NoIniciado, $a->status);

        // La puede editar mientras no esté marcada, pero no ponerla en rojo
        $this->actingAs($this->encargado)->get("/activities/{$a->id}/edit")->assertOk();
        $this->actingAs($this->encargado)->post("/activities/{$a->id}/marcar", ['marca' => 'no_completada'])->assertForbidden();
        $this->assertSame(ActivityStatus::NoIniciado, $a->fresh()->status);

        // Otro encargado (aunque sea del mismo equipo) no la ve; el gerente sí
        $this->actingAs($this->otroEncargado)->get("/activities/{$a->id}")->assertForbidden();
        // (el nombre de la comunidad sí sale en el filtro del catálogo; por eso se compara por folio)
        $this->actingAs($this->otroEncargado)->get('/activities')->assertDontSee($a->code);
        $this->actingAs($this->gerente)->get('/activities')->assertSee($a->code);

        // Ya revisada, el encargado ya no la edita
        $this->actingAs($this->gerente)->post("/activities/{$a->id}/marcar", ['marca' => 'concluido'])->assertRedirect();
        $this->actingAs($this->encargado)->get("/activities/{$a->id}/edit")->assertForbidden();
    }

    public function test_managers_mark_with_checkbox_and_incomplete_turns_red(): void
    {
        $a = $this->selfLoaded();

        // Incompleta: fila roja en el listado y comentario visible para el encargado
        $this->actingAs($this->admin)->post("/activities/{$a->id}/marcar", ['marca' => 'no_completada', 'mark_notes' => 'Falta la evidencia'])->assertRedirect();
        $a->refresh();
        $this->assertSame(ActivityStatus::NoCompletada, $a->status);
        $this->assertNull($a->completed_at);
        $this->assertSame($this->admin->id, $a->marked_by);

        $this->actingAs($this->gerente)->get('/activities')->assertSee('bg-rose-100', false);
        $this->actingAs($this->encargado)->get("/activities/{$a->id}")->assertSee('Falta la evidencia')->assertSee('No completada');

        // El rojo sí se puede quitar
        $this->actingAs($this->admin)->post("/activities/{$a->id}/marcar", ['marca' => 'sin_marcar'])->assertRedirect();
        $a->refresh();
        $this->assertSame(ActivityStatus::EnProceso, $a->status);
        $this->assertNull($a->marked_by);

        // Verde: Concluido pide confirmación y es definitivo
        $this->actingAs($this->gerente)->get("/activities/{$a->id}")->assertSee('ya no podrás hacerle ningún cambio');
        $this->actingAs($this->gerente)->post("/activities/{$a->id}/marcar", ['marca' => 'concluido'])->assertRedirect();
        $a->refresh();
        $this->assertSame(ActivityStatus::Concluido, $a->status);
        $this->assertSame($this->gerente->id, $a->marked_by);
        $this->assertNotNull($a->completed_at);
    }

    public function test_concluded_is_final_for_everyone(): void
    {
        $a = $this->selfLoaded();
        $this->actingAs($this->gerente)->post("/activities/{$a->id}/marcar", ['marca' => 'concluido'])->assertRedirect();

        foreach ([$this->admin, $this->gerente] as $user) {
            $this->actingAs($user)->post("/activities/{$a->id}/marcar", ['marca' => 'sin_marcar'])->assertForbidden();
            $this->actingAs($user)->post("/activities/{$a->id}/marcar", ['marca' => 'no_completada'])->assertForbidden();
            $this->actingAs($user)->get("/activities/{$a->id}/edit")->assertForbidden();
            $this->actingAs($user)->put("/activities/{$a->id}", $this->payload(['assigned_to' => $this->encargado->id]))->assertForbidden();
            $this->actingAs($user)->patch("/activities/{$a->id}/estado", ['status' => 'en_proceso'])->assertForbidden();
        }
        $this->assertSame(ActivityStatus::Concluido, $a->fresh()->status);
        $this->actingAs($this->gerente)->get("/activities/{$a->id}")->assertSee('ya no se puede modificar')->assertDontSee('Editar</a>', false);

        // Solo el administrador la puede borrar (por si se cargó por error); el gerente no
        $this->actingAs($this->gerente)->delete("/activities/{$a->id}")->assertForbidden();
        $this->actingAs($this->admin)->delete("/activities/{$a->id}")->assertRedirect();
        $this->assertNull(Activity::find($a->id));
    }

    public function test_everyone_concludes_their_own_activities_but_only_managers_mark_red(): void
    {
        // Actividad que el administrador se asignó a sí mismo: él mismo la puede concluir
        $this->actingAs($this->admin)->post('/activities', $this->payload(['assigned_to' => $this->admin->id]))->assertRedirect();
        $a = Activity::latest('id')->firstOrFail();
        $this->actingAs($this->admin)->get('/activities')->assertSee('Marcar como concluido');
        $this->actingAs($this->admin)->post("/activities/{$a->id}/marcar", ['marca' => 'concluido'])->assertRedirect();
        $this->assertSame(ActivityStatus::Concluido, $a->fresh()->status);

        // Y el gerente la que le asignaron
        $this->actingAs($this->admin)->post('/activities', $this->payload(['assigned_to' => $this->gerente->id]))->assertRedirect();
        $b = Activity::latest('id')->firstOrFail();
        $this->actingAs($this->gerente)->post("/activities/{$b->id}/marcar", ['marca' => 'concluido'])->assertRedirect();

        // El encargado concluye las suyas (con confirmación), pero no ve el rojo; el gerente ve ambos
        $c = $this->selfLoaded('Otra propia');
        $this->actingAs($this->encargado)->get('/activities')->assertSee('Marcar como concluido')->assertDontSee('Marcar como no completada');
        $this->actingAs($this->gerente)->get('/activities')->assertSee('Marcar como concluido')->assertSee('Marcar como no completada');
        $this->actingAs($this->encargado)->post("/activities/{$c->id}/marcar", ['marca' => 'concluido'])->assertRedirect();
        $c->refresh();
        $this->assertSame(ActivityStatus::Concluido, $c->status);
        $this->assertSame($this->encargado->id, $c->marked_by);
        // y ya no la puede cambiar
        $this->actingAs($this->encargado)->get("/activities/{$c->id}/edit")->assertForbidden();
        $this->actingAs($this->encargado)->post("/activities/{$c->id}/marcar", ['marca' => 'sin_marcar'])->assertForbidden();

        // Un encargado no marca las de otro
        $d = $this->selfLoaded('Una más');
        $this->actingAs($this->otroEncargado)->post("/activities/{$d->id}/marcar", ['marca' => 'concluido'])->assertForbidden();
    }

    public function test_gerente_assigns_only_to_encargados(): void
    {
        $this->actingAs($this->gerente)->post('/activities', $this->payload(['assigned_to' => $this->encargado->id]))
            ->assertSessionHas('status', fn ($s) => str_contains($s, 'creada y asignada'));
        $this->assertSame($this->encargado->id, Activity::latest('id')->first()->assigned_to);

        $this->actingAs($this->gerente)->post('/activities', $this->payload(['assigned_to' => $this->admin->id]))
            ->assertSessionHasErrors('assigned_to');
    }

    public function test_status_cannot_be_set_to_completed_through_create_or_edit(): void
    {
        $data = $this->payload(['assigned_to' => $this->encargado->id, 'status' => 'concluido']);
        $this->actingAs($this->gerente)->post('/activities', $data)->assertSessionHasErrors('status');

        // Al crear se puede elegir cualquiera de los tres estados manuales
        $this->actingAs($this->encargado)->post('/activities', $this->payload(['status' => 'en_proceso']))->assertRedirect();
        $this->assertSame(ActivityStatus::EnProceso, Activity::latest('id')->first()->status);
        $this->actingAs($this->gerente)->post('/activities', ['status' => 'no_iniciado'] + $data)->assertRedirect();
        $a = Activity::latest('id')->firstOrFail();
        $this->assertSame(ActivityStatus::NoIniciado, $a->status);

        $this->actingAs($this->gerente)->put("/activities/{$a->id}", $data)->assertSessionHasErrors('status');
        $this->actingAs($this->gerente)->put("/activities/{$a->id}", ['status' => 'suspendida'] + $data)->assertRedirect();
        $this->assertSame(ActivityStatus::Suspendida, $a->fresh()->status);
    }

    public function test_assigned_encargado_changes_manual_status_and_green_sets_concluido(): void
    {
        $a = $this->makeActivity(['assigned_to' => $this->encargado->id, 'created_by' => $this->gerente->id, 'team_id' => $this->encargado->team_id]);

        // Las tres opciones manuales
        $this->actingAs($this->encargado)->get("/activities/{$a->id}")
            ->assertSee('No iniciado')->assertSee('En proceso')->assertSee('Suspendida');
        $this->actingAs($this->encargado)->patch("/activities/{$a->id}/estado", ['status' => 'en_proceso'])->assertRedirect();
        $this->assertSame(ActivityStatus::EnProceso, $a->fresh()->status);

        // Concluido y No completada no se eligen a mano
        $this->actingAs($this->encargado)->patch("/activities/{$a->id}/estado", ['status' => 'concluido'])->assertSessionHasErrors('status');
        $this->actingAs($this->gerente)->patch("/activities/{$a->id}/estado", ['status' => 'no_completada'])->assertSessionHasErrors('status');

        // Otro encargado no puede cambiarlo ni editar lo que le asignó el gerente
        $this->actingAs($this->otroEncargado)->patch("/activities/{$a->id}/estado", ['status' => 'suspendida'])->assertForbidden();
        $this->actingAs($this->encargado)->get("/activities/{$a->id}/edit")->assertForbidden();

        // Verde: Concluido, y cuenta en el dashboard
        $this->actingAs($this->gerente)->post("/activities/{$a->id}/marcar", ['marca' => 'concluido'])->assertRedirect();
        $this->assertSame(ActivityStatus::Concluido, $a->fresh()->status);
        $this->actingAs($this->encargado)->get("/activities/{$a->id}")->assertSee('Concluido');
        $this->assertSame(1, (new \App\Services\DashboardMetrics('30', null))->summary()['now']['completed']);

        // Ya marcada, el encargado no la cambia
        $this->actingAs($this->encargado)->patch("/activities/{$a->id}/estado", ['status' => 'en_proceso'])->assertForbidden();

        // Concluida ya no pasa a rojo
        $this->actingAs($this->gerente)->post("/activities/{$a->id}/marcar", ['marca' => 'no_completada'])->assertForbidden();
        $this->assertSame(ActivityStatus::Concluido, $a->fresh()->status);
    }
}
