<?php

namespace Tests\Feature;

use App\Enums\ActivityStatus;
use App\Models\Activity;
use App\Models\ActivityType;
use App\Models\PresidentActivityType;
use App\Models\Community;
use App\Models\PresidentActivity;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Agenda del Presidente: mismos campos que Actividades, pero aparte y solo para administradores y gerentes. */
class PresidentAgendaTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private User $gerente;
    private PresidentActivityType $type;
    private Community $community;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::factory()->role('admin')->create();
        $this->gerente = User::factory()->role('gerente')->create();
        $this->type = PresidentActivityType::create(['name' => 'Gira de trabajo', 'active' => true]);
        $this->community = Community::create(['name' => 'Huimilpan', 'active' => true]);
    }

    private function payload(array $extra = []): array
    {
        return array_merge([
            'activity_type_id' => $this->type->id, 'community_id' => $this->community->id, 'assigned_to' => $this->gerente->id,
            'start_date' => '2026-12-01', 'end_date' => '2026-12-02', 'evidence_text' => 'Reunión con ejidatarios',
        ], $extra);
    }

    public function test_only_admins_and_managers_and_it_is_separate_from_activities(): void
    {
        $encargado = User::factory()->role('encargado')->create();
        $this->actingAs($encargado)->get('/agenda-presidente')->assertForbidden();
        $this->actingAs($encargado)->get('/dashboard')->assertDontSee('Agenda del Presidente');

        $this->actingAs($this->gerente)->get('/agenda-presidente')->assertOk()->assertSee('Agenda del Presidente');
        $this->actingAs($this->gerente)->post('/agenda-presidente', $this->payload())->assertRedirect();

        $a = PresidentActivity::firstOrFail();
        $this->assertSame('AP-0001', $a->folio);
        $this->assertSame(0, Activity::count());   // no se mezcla con Actividades
        $this->actingAs($this->gerente)->get('/agenda-presidente');   // consume el aviso de «agregada»
        $this->actingAs($this->gerente)->get('/activities')->assertDontSee('AP-0001');
        $this->actingAs($this->gerente)->get('/calendario?mes=2026-12')->assertDontSee('AP-0001');
        $this->actingAs($this->gerente)->get('/agenda-presidente')->assertSee('AP-0001')->assertSee('Gira de trabajo')->assertSee('Huimilpan');

        // Se asigna solo a administradores o gerentes
        $this->actingAs($this->gerente)->post('/agenda-presidente', $this->payload(['assigned_to' => $encargado->id]))->assertSessionHasErrors('assigned_to');
        // Tipo y comunidad nuevos se agregan al catálogo
        $this->actingAs($this->admin)->post('/agenda-presidente', $this->payload(['activity_type_id' => 'otro', 'activity_type_new' => 'Audiencia pública']))->assertSessionHasNoErrors();
        $this->assertTrue(PresidentActivityType::where('name', 'Audiencia pública')->exists());
        $this->assertFalse(ActivityType::where('name', 'Audiencia pública')->exists());   // no llega al catálogo general
        $this->assertSame('AP-0002', PresidentActivity::latest('id')->first()->folio);
    }

    public function test_status_marking_and_concluded_is_final(): void
    {
        $this->actingAs($this->gerente)->post('/agenda-presidente', $this->payload(['status' => 'en_proceso']))->assertRedirect();
        $a = PresidentActivity::firstOrFail();
        $this->assertSame(ActivityStatus::EnProceso, $a->status);

        $this->actingAs($this->gerente)->get("/agenda-presidente/{$a->id}")->assertOk()->assertSee('ya no podrás hacerle ningún cambio');
        $this->actingAs($this->gerente)->post("/agenda-presidente/{$a->id}/marcar", ['marca' => 'no_completada', 'mark_notes' => 'Se canceló'])->assertRedirect();
        $this->assertSame(ActivityStatus::NoCompletada, $a->fresh()->status);
        $this->actingAs($this->gerente)->post("/agenda-presidente/{$a->id}/marcar", ['marca' => 'sin_marcar'])->assertRedirect();
        $this->actingAs($this->gerente)->post("/agenda-presidente/{$a->id}/marcar", ['marca' => 'concluido'])->assertRedirect();
        $a->refresh();
        $this->assertSame(ActivityStatus::Concluido, $a->status);
        $this->assertNotNull($a->completed_at);

        // Concluida: no se edita ni se desmarca; solo el admin la puede borrar
        $this->actingAs($this->gerente)->get("/agenda-presidente/{$a->id}/editar")->assertForbidden();
        $this->actingAs($this->gerente)->put("/agenda-presidente/{$a->id}", $this->payload())->assertForbidden();
        $this->actingAs($this->gerente)->post("/agenda-presidente/{$a->id}/marcar", ['marca' => 'sin_marcar'])->assertForbidden();
        $this->actingAs($this->gerente)->delete("/agenda-presidente/{$a->id}")->assertForbidden();
        $this->actingAs($this->admin)->delete("/agenda-presidente/{$a->id}")->assertRedirect();
        $this->assertSame(0, PresidentActivity::count());
    }

    public function test_filters_and_overdue(): void
    {
        $this->actingAs($this->gerente)->post('/agenda-presidente', $this->payload(['start_date' => today()->subDays(5)->toDateString(), 'end_date' => today()->subDays(2)->toDateString()]));
        $this->actingAs($this->gerente)->post('/agenda-presidente', $this->payload(['start_date' => '2027-01-10', 'end_date' => '2027-01-10', 'evidence_text' => 'Informe de gobierno']));

        $this->actingAs($this->gerente)->get('/agenda-presidente');   // consume el aviso de «agregada»
        $this->actingAs($this->gerente)->get('/agenda-presidente?status=vencidas')->assertSee('AP-0001')->assertDontSee('AP-0002');
        $this->actingAs($this->gerente)->get('/agenda-presidente?mes=2027-01')->assertSee('AP-0002')->assertDontSee('AP-0001');
        $this->actingAs($this->gerente)->get('/agenda-presidente?q=Informe')->assertSee('AP-0002')->assertDontSee('AP-0001');
    }

    public function test_own_calendar_shows_each_day_of_the_activity(): void
    {
        $this->actingAs($this->gerente)->post('/agenda-presidente', $this->payload(['start_date' => '2026-12-30', 'end_date' => '2027-01-02']));
        $this->actingAs($this->gerente)->get('/agenda-presidente');

        $dic = $this->actingAs($this->gerente)->get('/agenda-presidente/calendario?mes=2026-12')->assertOk()->assertSee('Gira de trabajo · Huimilpan')->assertSee('1</b> actividad', false);
        $this->actingAs($this->gerente)->get('/agenda-presidente/calendario?mes=2027-01')->assertOk()->assertSee('Gira de trabajo · Huimilpan');
        $this->actingAs($this->gerente)->get('/agenda-presidente/calendario?mes=2027-02')->assertOk()->assertSee('0</b> actividades', false);
        $this->actingAs(User::factory()->role('encargado')->create())->get('/agenda-presidente/calendario')->assertForbidden();
        // El calendario general no la muestra
        $this->actingAs($this->gerente)->get('/calendario?mes=2026-12')->assertDontSee('Gira de trabajo · Huimilpan');
    }

    public function test_type_list_is_private_both_ways(): void
    {
        // Lo que escriben los demás en Actividades no aparece en la Agenda del Presidente
        ActivityType::create(['name' => 'Platica con Jacinto', 'active' => true]);
        $this->actingAs($this->gerente)->get('/agenda-presidente/nueva')->assertOk()->assertSee('Gira de trabajo')->assertDontSee('Platica con Jacinto');

        // Y lo que se escribe en la Agenda del Presidente no aparece en Actividades ni en Catálogos
        $this->actingAs($this->gerente)->post('/agenda-presidente', $this->payload(['activity_type_id' => 'otro', 'activity_type_new' => 'Reunión privada con el gobernador']))->assertSessionHasNoErrors();
        $this->actingAs($this->gerente)->get('/agenda-presidente');
        $this->actingAs($this->gerente)->get('/activities/create')->assertDontSee('Reunión privada con el gobernador');
        $this->actingAs($this->admin)->get('/catalogos')->assertDontSee('Reunión privada con el gobernador');
        $this->actingAs($this->gerente)->get('/agenda-presidente')->assertSee('Reunión privada con el gobernador');
    }
}
