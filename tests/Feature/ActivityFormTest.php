<?php

namespace Tests\Feature;

use App\Models\Activity;
use App\Models\ActivityType;
use App\Models\Community;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\MakesActivities;
use Tests\TestCase;

/** Nuevo formulario: ID automático, tipo, comunidad, evidencia (foto o texto), inicio y fin. */
class ActivityFormTest extends TestCase
{
    use MakesActivities;
    use RefreshDatabase;

    private User $gerente;
    private User $encargado;
    private User $empleado;

    protected function setUp(): void
    {
        parent::setUp();

        $this->gerente = User::factory()->role('gerente')->create();
        $this->encargado = User::factory()->role('encargado')->create();
        $team = Team::create(['name' => 'Norte', 'leader_id' => $this->encargado->id]);
        $this->encargado->update(['team_id' => $team->id]);
        $this->empleado = User::factory()->create(['team_id' => $team->id]);
    }

    public function test_form_shows_automatic_id_and_fields_in_order(): void
    {
        $this->typeId();
        $this->communityId();

        $html = $this->actingAs($this->encargado)->get('/activities/create')->assertOk()->getContent();
        $this->assertStringContainsString('ACT-0001', $html);

        $positions = array_map(fn ($needle) => strpos($html, $needle), [
            'for="code"', 'name="activity_type_id"', 'name="community_id"', 'name="evidence_mode"', 'name="start_date"', 'name="end_date"', 'name="status"',
        ]);
        $this->assertSame($positions, array_values(array_filter($positions)), 'Todos los campos existen');
        $sorted = $positions;
        sort($sorted);
        $this->assertSame($sorted, $positions, 'Orden: ID, tipo, comunidad, evidencia, inicio, fin, estado');

        $this->actingAs($this->encargado)->post('/activities', $this->payload(['assigned_to' => $this->empleado->id]))
            ->assertSessionHas('status', 'Actividad ACT-0001 creada y asignada.');
        $this->actingAs($this->encargado)->get('/activities/create')->assertSee('ACT-0002');
    }

    public function test_required_fields_and_date_order(): void
    {
        $this->actingAs($this->encargado)->post('/activities', ['assigned_to' => $this->empleado->id])
            ->assertSessionHasErrors(['activity_type_id', 'community_id', 'start_date', 'end_date']);

        $this->actingAs($this->encargado)->post('/activities', $this->payload([
            'assigned_to' => $this->empleado->id,
            'start_date' => '2026-11-10',
            'end_date' => '2026-11-09',
        ]))->assertSessionHasErrors('end_date');

        $this->assertSame(0, Activity::count());
    }

    public function test_text_evidence_is_saved_and_shown(): void
    {
        $this->actingAs($this->empleado)->post('/activities', $this->payload([
            'evidence_text' => 'Se visitaron 18 domicilios.',
        ]))->assertRedirect();

        $a = Activity::firstOrFail();
        $this->assertSame('Se visitaron 18 domicilios.', $a->evidence_text);
        $this->actingAs($this->encargado)->get("/activities/{$a->id}")
            ->assertSee('Se visitaron 18 domicilios.')
            ->assertSee('ACT-0001')
            ->assertSee('Visita domiciliaria')
            ->assertSee('San Isidro');
    }

    public function test_photo_evidence_must_be_an_image(): void
    {
        $this->actingAs($this->empleado)->post('/activities', $this->payload([
            'evidence' => \Illuminate\Http\UploadedFile::fake()->create('reporte.pdf', 50, 'application/pdf'),
        ]))->assertSessionHasErrors('evidence');
    }

    public function test_inactive_catalog_items_cannot_be_used_but_old_activities_keep_them(): void
    {
        $a = $this->makeActivity(['assigned_to' => $this->empleado->id, 'created_by' => $this->encargado->id]);
        ActivityType::query()->update(['active' => false]);

        $this->actingAs($this->encargado)->post('/activities', $this->payload(['assigned_to' => $this->empleado->id]))
            ->assertSessionHasErrors('activity_type_id');

        // Al editar una actividad vieja se conserva su tipo aunque esté desactivado
        $this->actingAs($this->encargado)->put("/activities/{$a->id}", $this->payload([
            'assigned_to' => $this->empleado->id,
            'activity_type_id' => $a->activity_type_id,
        ]))->assertSessionHasNoErrors();
    }

    public function test_search_by_code_type_and_community(): void
    {
        $uno = $this->makeActivity(['assigned_to' => $this->empleado->id, 'created_by' => $this->encargado->id], 'El Mirador');
        $dos = $this->makeActivity(['assigned_to' => $this->empleado->id, 'created_by' => $this->encargado->id], 'Los Pinos');

        // Los filtros listan todas las comunidades, así que se compara por folio
        $this->actingAs($this->gerente)->get('/activities?q=ACT-0002')->assertSee('ACT-0002')->assertDontSee('ACT-0001');
        $this->actingAs($this->gerente)->get('/activities?q=2')->assertSee('ACT-0002')->assertDontSee('ACT-0001');
        $this->actingAs($this->gerente)->get('/activities?q=mirador')->assertSee('ACT-0001')->assertDontSee('ACT-0002');
        $this->actingAs($this->gerente)->get('/activities?community_id='.$uno->community_id)->assertSee('ACT-0001')->assertDontSee('ACT-0002');
    }

    public function test_managers_administer_catalogs(): void
    {
        $this->actingAs($this->gerente)->post('/catalogos/tipos', ['name' => 'Reunión vecinal'])->assertRedirect();
        $this->actingAs($this->gerente)->post('/catalogos/comunidades', ['name' => 'La Joya'])->assertRedirect();
        $this->actingAs($this->gerente)->post('/catalogos/comunidades', ['name' => 'La Joya'])->assertSessionHasErrors('name', null, 'comunidades');

        $type = ActivityType::where('name', 'Reunión vecinal')->firstOrFail();
        $this->actingAs($this->gerente)->patch("/catalogos/tipos/{$type->id}", ['name' => 'Reunión con vecinos'])->assertRedirect();
        $this->assertSame('Reunión con vecinos', $type->fresh()->name);

        // Desactivado: ya no sale en el formulario
        $this->actingAs($this->gerente)->patch("/catalogos/tipos/{$type->id}", ['active' => 0])->assertRedirect();
        $this->actingAs($this->encargado)->get('/activities/create')->assertDontSee('>Reunión con vecinos</option>', false);

        // Con actividades no se borra; sin actividades sí
        $community = Community::where('name', 'La Joya')->firstOrFail();
        $this->makeActivity(['assigned_to' => $this->empleado->id, 'created_by' => $this->encargado->id], 'La Joya');
        $this->actingAs($this->gerente)->delete("/catalogos/comunidades/{$community->id}")->assertSessionHasErrors('name', null, 'comunidades');
        $this->assertNotNull($community->fresh());

        $this->actingAs($this->gerente)->delete("/catalogos/tipos/{$type->id}")->assertRedirect();
        $this->assertNull($type->fresh());

        $this->actingAs($this->gerente)->get('/catalogos')->assertOk()->assertSee('La Joya');
    }
}
