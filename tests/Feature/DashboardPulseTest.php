<?php

namespace Tests\Feature;

use App\Models\Community;
use App\Models\Minute;
use App\Models\User;
use App\Services\DashboardMetrics;
use App\Services\DashboardPulse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\MakesActivities;
use Tests\TestCase;

/** Paneles nuevos del dashboard: filtro por área/encargado, cumplimiento por área, ubicación, fantasmas, comunidades y compromisos. */
class DashboardPulseTest extends TestCase
{
    use MakesActivities;
    use RefreshDatabase;

    private User $admin;
    private User $tecnica;
    private User $territorial;
    private User $enc1;
    private User $enc2;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::factory()->role('admin')->create();
        $this->tecnica = User::factory()->role('gerente')->create(['name' => 'Juanita', 'area' => 'Técnica']);
        $this->territorial = User::factory()->role('gerente')->create(['name' => 'Lalo', 'area' => 'Territorial']);
        $this->enc1 = User::factory()->role('encargado')->create(['name' => 'Pedro Enc']);
        $this->enc2 = User::factory()->role('encargado')->create(['name' => 'Rosa Enc']);
    }

    public function test_by_area_and_area_filter(): void
    {
        $this->makeActivity(['created_by' => $this->tecnica->id, 'assigned_to' => $this->enc1->id, 'status' => 'concluido', 'completed_at' => now(), 'end_date' => today()->addDay()->toDateString()]);
        $this->makeActivity(['created_by' => $this->tecnica->id, 'assigned_to' => $this->enc2->id]); // vencida
        $this->makeActivity(['created_by' => $this->territorial->id, 'assigned_to' => $this->enc1->id]);
        $this->makeActivity(['created_by' => $this->enc2->id, 'assigned_to' => $this->enc2->id]);

        $rows = collect((new DashboardMetrics('30'))->byArea())->keyBy('label');
        $this->assertSame(2, $rows['Técnica']->total);
        $this->assertEquals(50.0, $rows['Técnica']->cumplimiento);
        $this->assertSame(1, $rows['Técnica']->overdue);
        $this->assertSame(1, $rows['Territorial']->total);
        $this->assertSame(1, $rows['Cargadas por el propio encargado']->total);
        $this->assertSame(['Territorial', 'Técnica'], collect(DashboardMetrics::areas())->sort()->values()->all());

        $tec = new DashboardMetrics('30', 'Técnica');
        $this->assertSame(2, $tec->summary()['now']['total']);
        $this->assertSame(1, (new DashboardMetrics('30', null, $this->enc1->id))->summary()['now']['total'] - 1);

        $this->actingAs($this->admin)->get('/dashboard?area=T%C3%A9cnica')->assertOk()
            ->assertSee('Cumplimiento por área')->assertSee('Todas las áreas')->assertDontSee('Todos los equipos');
    }

    public function test_location_control(): void
    {
        $base = ['created_by' => $this->tecnica->id, 'assigned_to' => $this->enc1->id, 'requiere_ubicacion' => true, 'destino_lat' => 20.4, 'destino_lng' => -100.1, 'destino_radio' => 100];
        $this->makeActivity($base + ['ubicacion_at' => now(), 'ubicacion_distancia' => 30]);
        $this->makeActivity($base + ['ubicacion_at' => now(), 'ubicacion_distancia' => 900]);
        $this->makeActivity($base);
        $this->makeActivity($base)->forceFill(['ubicacion_validada_por' => $this->admin->id, 'ubicacion_validada_at' => now()])->save();

        $loc = (new DashboardMetrics('30'))->location();
        $this->assertSame([4, 1, 1, 1, 1], [$loc['total'], $loc['verificada'], $loc['manual'], $loc['fuera'], $loc['pendiente']]);
        $this->assertEquals(50.0, $loc['pct_comprobadas']);
    }

    public function test_ghosts_forgotten_communities_and_commitments(): void
    {
        // Pedro trabajó hoy; Rosa no ha hecho nada desde que se creó hace 10 días
        $this->makeActivity(['created_by' => $this->enc1->id, 'assigned_to' => $this->enc1->id], 'Huimilpan');
        $this->enc2->forceFill(['created_at' => now()->subDays(10)])->save();
        Community::firstOrCreate(['name' => 'Lagunillas']);

        $metrics = new DashboardMetrics('30');
        $pulse = new DashboardPulse($this->admin, $metrics);

        $ghosts = $pulse->ghosts();
        $this->assertSame(['Rosa Enc'], $ghosts->pluck('user.name')->all());
        $this->assertTrue($ghosts->first()->nunca);

        $forgotten = $pulse->forgottenCommunities();
        $this->assertContains('Lagunillas', $forgotten->olvidadas->pluck('community.name')->all());
        $this->assertNotContains('Huimilpan', $forgotten->olvidadas->pluck('community.name')->all());

        $minute = Minute::create(['titulo' => 'Junta semanal', 'fecha' => today()->subDays(5), 'created_by' => $this->admin->id]);
        $minute->commitments()->create(['actividad' => 'Mandar lonas', 'responsable' => 'Lalo', 'fecha_limite' => today()->subDays(2)->toDateString(), 'estatus' => 'En proceso']);
        $minute->commitments()->create(['actividad' => 'Ya hecho', 'fecha_limite' => today()->subDays(2)->toDateString(), 'estatus' => 'Concluida']);
        $minute->commitments()->create(['actividad' => 'Futuro', 'fecha_limite' => today()->addDays(2)->toDateString()]);
        $this->assertSame(['Mandar lonas'], $pulse->commitments()->pluck('actividad')->all());

        $this->actingAs($this->admin)->get('/dashboard')->assertOk()
            ->assertSee('Encargados sin movimiento')->assertSee('Rosa Enc')
            ->assertSee('Comunidades olvidadas')->assertSee('Lagunillas')
            ->assertSee('Compromisos de minutas vencidos')->assertSee('Mandar lonas')
            ->assertSee('Próximos 7 días')->assertSee('Control de ubicación');
    }

    public function test_upcoming_activities_and_personal_dashboard_shows_area(): void
    {
        $this->makeActivity(['created_by' => $this->tecnica->id, 'assigned_to' => $this->enc1->id, 'start_date' => today()->toDateString(), 'end_date' => today()->addDays(2)->toDateString()]);

        $pulse = new DashboardPulse($this->admin, new DashboardMetrics('30'));
        $this->assertSame(1, $pulse->upcoming()->actividades->count());

        $this->actingAs($this->enc1)->get('/dashboard')->assertOk()->assertSee('Técnica (1)')->assertDontSee('Juanita');
    }
}
