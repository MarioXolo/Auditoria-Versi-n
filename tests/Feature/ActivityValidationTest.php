<?php

namespace Tests\Feature;

use App\Enums\ActivityStatus;
use App\Enums\ValidationStatus;
use App\Models\Activity;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ActivityValidationTest extends TestCase
{
    use RefreshDatabase;

    private User $gerente;
    private User $encargado;
    private User $otroEncargado;
    private User $empleado;
    private Activity $activity;

    protected function setUp(): void
    {
        parent::setUp();

        $this->gerente = User::factory()->role('gerente')->create();
        $this->encargado = User::factory()->role('encargado')->create();
        $this->otroEncargado = User::factory()->role('encargado')->create();
        $team = Team::create(['name' => 'Norte', 'leader_id' => $this->encargado->id]);
        $this->encargado->update(['team_id' => $team->id]);
        $this->empleado = User::factory()->create(['team_id' => $team->id]);

        $this->actingAs($this->encargado)->post('/activities', [
            'title' => 'Volanteo', 'assigned_to' => $this->empleado->id, 'priority' => 'media', 'status' => 'pendiente',
        ]);
        $this->activity = Activity::where('title', 'Volanteo')->firstOrFail();
    }

    public function test_completing_an_activity_leaves_it_pending_validation(): void
    {
        $this->assertNull($this->activity->validation_status);

        $this->actingAs($this->encargado)->patch("/activities/{$this->activity->id}/status", ['status' => 'completada']);

        $a = $this->activity->fresh();
        $this->assertSame(ValidationStatus::Pendiente, $a->validation_status);
        $this->assertTrue($a->isPendingValidation());

        $this->actingAs($this->encargado)->get('/activities?status=por_validar')->assertSee('Volanteo');
        $this->actingAs($this->encargado)->get("/activities/{$a->id}")->assertSee('Validar')->assertSee('Rechazar');
        // El empleado ve que está por validar, pero no tiene botones
        $this->actingAs($this->empleado)->get("/activities/{$a->id}")->assertSee('Por validar')->assertDontSee('name="decision"', false);
    }

    public function test_only_assigner_gerente_or_admin_can_validate(): void
    {
        $this->actingAs($this->encargado)->patch("/activities/{$this->activity->id}/status", ['status' => 'completada']);
        $url = "/activities/{$this->activity->id}/validacion";

        $this->actingAs($this->empleado)->post($url, ['decision' => 'validar'])->assertForbidden();
        $this->actingAs($this->otroEncargado)->post($url, ['decision' => 'validar'])->assertForbidden();

        $this->actingAs($this->encargado)->post($url, ['decision' => 'validar', 'validation_notes' => 'Todo bien'])->assertRedirect();

        $a = $this->activity->fresh();
        $this->assertSame(ValidationStatus::Validada, $a->validation_status);
        $this->assertSame($this->encargado->id, $a->validated_by);
        $this->assertNotNull($a->validated_at);

        // Ya validada: no se puede volver a validar
        $this->actingAs($this->gerente)->post($url, ['decision' => 'validar'])->assertForbidden();
    }

    public function test_rejecting_requires_a_reason_and_reopens_the_activity(): void
    {
        $this->actingAs($this->encargado)->patch("/activities/{$this->activity->id}/status", ['status' => 'completada']);
        $url = "/activities/{$this->activity->id}/validacion";

        $this->actingAs($this->gerente)->post($url, ['decision' => 'rechazar'])->assertSessionHasErrors('validation_notes');

        $this->actingAs($this->gerente)->post($url, ['decision' => 'rechazar', 'validation_notes' => 'Falta la foto'])->assertRedirect();

        $a = $this->activity->fresh();
        $this->assertSame(ValidationStatus::Rechazada, $a->validation_status);
        $this->assertSame(ActivityStatus::EnProgreso, $a->status);
        $this->assertNull($a->completed_at);
        $this->assertSame('Falta la foto', $a->validation_notes);

        $this->actingAs($this->empleado)->get("/activities/{$a->id}")->assertSee('Falta la foto');

        // Al completarla de nuevo, vuelve a quedar por validar
        $this->actingAs($this->encargado)->patch("/activities/{$a->id}/status", ['status' => 'completada']);
        $this->assertSame(ValidationStatus::Pendiente, $a->fresh()->validation_status);
    }

    public function test_nobody_validates_their_own_activity(): void
    {
        $this->actingAs($this->gerente)->post('/activities', [
            'title' => 'Reporte del encargado', 'assigned_to' => $this->encargado->id, 'priority' => 'media', 'status' => 'completada',
        ]);
        $a = Activity::where('title', 'Reporte del encargado')->firstOrFail();
        $this->assertSame(ValidationStatus::Pendiente, $a->validation_status);

        $this->actingAs($this->encargado)->post("/activities/{$a->id}/validacion", ['decision' => 'validar'])->assertForbidden();
        $this->actingAs($this->gerente)->post("/activities/{$a->id}/validacion", ['decision' => 'validar'])->assertRedirect();
    }
}
