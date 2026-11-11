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
        $this->assertStringContainsString('ID1.0001', $html);

        $positions = array_map(fn ($needle) => strpos($html, $needle), [
            'for="code"', 'name="activity_type_id"', 'name="community_id"', 'name="evidence_mode"', 'name="start_date"', 'name="end_date"', 'name="status"',
        ]);
        $this->assertSame($positions, array_values(array_filter($positions)), 'Todos los campos existen');
        $sorted = $positions;
        sort($sorted);
        $this->assertSame($sorted, $positions, 'Orden: ID, tipo, comunidad, evidencia, inicio, fin, estado');

        $this->actingAs($this->encargado)->post('/activities', $this->payload(['assigned_to' => $this->empleado->id]))
            ->assertSessionHas('status', 'Actividad ID1.0001 creada y asignada.');
        $this->actingAs($this->encargado)->get('/activities/create')->assertSee('ID1.0002');
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
            ->assertSee('ID1.0001')
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
        $team = $this->empleado->team_id;
        $uno = $this->makeActivity(['assigned_to' => $this->empleado->id, 'created_by' => $this->encargado->id, 'team_id' => $team], 'El Mirador');
        $dos = $this->makeActivity(['assigned_to' => $this->empleado->id, 'created_by' => $this->encargado->id, 'team_id' => $team], 'Los Pinos');
        $this->assertSame(['ID1.0001', 'ID1.0002'], [$uno->code, $dos->code]);

        // Los filtros listan todas las comunidades, así que se compara por folio
        $this->actingAs($this->gerente)->get('/activities?q=ID1.0002')->assertSee('ID1.0002')->assertDontSee('ID1.0001');
        $this->actingAs($this->gerente)->get('/activities?q=1.2')->assertSee('ID1.0002')->assertDontSee('ID1.0001');
        $this->actingAs($this->gerente)->get('/activities?q=ID1')->assertSee('ID1.0001')->assertSee('ID1.0002');
        $this->actingAs($this->gerente)->get('/activities?q=mirador')->assertSee('ID1.0001')->assertDontSee('ID1.0002');
        $this->actingAs($this->gerente)->get('/activities?community_id='.$uno->community_id)->assertSee('ID1.0001')->assertDontSee('ID1.0002');
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

    public function test_each_team_has_its_own_folio_sequence(): void
    {
        $leaderSur = User::factory()->role('encargado')->create();
        $sur = Team::create(['name' => 'Sur', 'leader_id' => $leaderSur->id]);
        $leaderSur->update(['team_id' => $sur->id]);
        $empSur = User::factory()->create(['team_id' => $sur->id]);
        $sinEquipo = User::factory()->create();

        $this->assertSame(2, $sur->number);

        $folios = [];
        foreach ([$this->empleado, $empSur, $this->empleado, $empSur, $empSur] as $person) {
            $this->actingAs($this->gerente)->post('/activities', $this->payload(['assigned_to' => $person->id]))->assertRedirect();
            $folios[] = Activity::latest('id')->first()->code;
        }
        $this->assertSame(['ID1.0001', 'ID2.0001', 'ID1.0002', 'ID2.0002', 'ID2.0003'], $folios);

        $this->actingAs($this->gerente)->post('/activities', $this->payload(['assigned_to' => $sinEquipo->id]))->assertRedirect();
        $this->assertSame('ID0.0001', Activity::latest('id')->first()->code);

        // El folio no cambia si después se reasigna a otro equipo
        $a = Activity::where('folio', 'ID1.0001')->first();
        $this->actingAs($this->gerente)->put("/activities/{$a->id}", $this->payload(['assigned_to' => $empSur->id]))->assertRedirect();
        $this->assertSame('ID1.0001', $a->fresh()->code);
        $this->assertSame($sur->id, $a->fresh()->team_id);

        // El empleado ve el folio de su equipo al cargar
        $this->actingAs($empSur)->get('/activities/create')->assertSee('ID2.0004');
    }

    public function test_maximum_of_thirty_teams(): void
    {
        foreach (range(2, 30) as $n) {
            Team::create(['name' => "Equipo {$n}"]);
        }
        $this->assertSame(30, (int) Team::query()->max('number'));

        $admin = User::factory()->role('admin')->create();
        $this->actingAs($admin)->get('/teams')->assertSee('Límite de 30 equipos');
        $this->actingAs($admin)->get('/teams/create')->assertRedirect(route('teams.index'));
        $this->actingAs($admin)->post('/teams', ['name' => 'Equipo 31'])->assertSessionHasErrors('name');
        $this->assertSame(30, Team::count());

        // Al borrar uno se libera su número y el nuevo continúa la secuencia de folios
        $this->makeActivity(['assigned_to' => $this->empleado->id, 'created_by' => $this->encargado->id, 'team_id' => Team::where('number', 5)->value('id')]);
        Team::where('number', 5)->first()->delete();
        $this->actingAs($admin)->post('/teams', ['name' => 'Nuevo 5'])->assertRedirect(route('teams.index'));
        $nuevo = Team::where('name', 'Nuevo 5')->first();
        $this->assertSame(5, $nuevo->number);
        $b = $this->makeActivity(['assigned_to' => $this->empleado->id, 'created_by' => $this->encargado->id, 'team_id' => $nuevo->id]);
        $this->assertSame('ID5.0002', $b->code);
    }

    public function test_type_and_community_can_be_typed_manually(): void
    {
        $this->typeId('Visita domiciliaria');

        $this->actingAs($this->empleado)->post('/activities', $this->payload([
            'activity_type_id' => 'otro', 'activity_type_new' => '  Brigada   de limpieza ',
            'community_id' => 'otro', 'community_new' => 'Colonia Nueva Esperanza',
        ]))->assertRedirect();

        $a = Activity::latest('id')->first();
        $this->assertSame('Brigada de limpieza', $a->type->name);
        $this->assertSame('Colonia Nueva Esperanza', $a->community->name);

        // Lo escrito queda en el catálogo y no se duplica aunque cambien mayúsculas
        $this->actingAs($this->empleado)->post('/activities', $this->payload([
            'activity_type_id' => 'otro', 'activity_type_new' => 'visita DOMICILIARIA',
        ]))->assertRedirect();
        $this->assertSame(1, ActivityType::whereRaw('LOWER(name) = ?', ['visita domiciliaria'])->count());
        $this->actingAs($this->encargado)->get('/activities/create')->assertSee('Brigada de limpieza')->assertSee('Colonia Nueva Esperanza');

        // Si elige «otro» tiene que escribirlo
        $this->actingAs($this->empleado)->post('/activities', $this->payload(['community_id' => 'otro']))
            ->assertSessionHasErrors('community_new');
    }
}
