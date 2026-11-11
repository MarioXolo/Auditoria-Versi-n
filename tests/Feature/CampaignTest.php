<?php

namespace Tests\Feature;

use App\Models\Campaign;
use App\Models\CampaignPromoter;
use App\Models\CampaignVoter;
use App\Models\Community;
use App\Models\Team;
use App\Models\User;
use App\Services\CampaignMetrics;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** V2 · Cuaderno Maestro de Campaña (etapa 1). */
class CampaignTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private User $gerente;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::factory()->role('admin')->create();
        $this->gerente = User::factory()->role('gerente')->create();
    }

    /** Datos del Excel original: 30,000 · 55 % · 50 % · 10 % · 1×10. */
    private function excelCampaign(array $over = []): Campaign
    {
        $this->actingAs($this->gerente)->post('/campanas', array_merge([
            'name' => 'Reelección Huimilpan 2027', 'municipality' => 'Huimilpan', 'election_date' => '2027-06-06',
            'listado_nominal' => 30000, 'participacion' => 55, 'objetivo' => 50, 'colchon' => 10, 'votantes_por_promotor' => 10,
        ], $over))->assertRedirect();

        return Campaign::latest('id')->first();
    }

    public function test_only_admins_and_managers_can_access(): void
    {
        $campaign = $this->excelCampaign();
        $encargado = User::factory()->role('encargado')->create();
        $empleado = User::factory()->create();

        foreach ([$encargado, $empleado] as $u) {
            $this->actingAs($u)->get('/campanas')->assertForbidden();
            $this->actingAs($u)->get("/campanas/{$campaign->id}/votantes")->assertForbidden();
            $this->actingAs($u)->post("/campanas/{$campaign->id}/votantes", ['nombre' => 'X'])->assertForbidden();
            $this->actingAs($u)->get('/activities')->assertDontSee('Campañas');
        }

        foreach ([$this->admin, $this->gerente] as $u) {
            $this->actingAs($u)->get('/campanas')->assertOk()->assertSee('Reelección Huimilpan 2027');
            foreach (['', '/estructura', '/votantes', '/votantes/create', '/promotores', '/promotores/create', '/kpi', '/edit'] as $page) {
                $this->actingAs($u)->get("/campanas/{$campaign->id}{$page}")->assertOk();
            }
        }
    }

    public function test_vote_goal_calculator_matches_the_excel(): void
    {
        $c = $this->excelCampaign();

        $this->assertSame(0.55, $c->participacion);
        $this->assertEqualsWithDelta(16500, $c->votosEsperados(), 0.001);
        $this->assertEqualsWithDelta(8250, $c->metaBase(), 0.001);
        $this->assertEqualsWithDelta(9075, $c->metaConColchon(), 0.001);
        $this->assertSame(908, $c->promotoresNecesarios());

        $this->actingAs($this->gerente)->get("/campanas/{$c->id}")->assertSee('9,075')->assertSee('908');

        // Cada campaña nueva trae los 3 indicadores manuales del Excel
        $this->assertSame(['Aprobación del candidato (encuesta)', 'Alcance digital semanal', 'Incidencias jurídicas abiertas'], $c->kpis->pluck('nombre')->all());
    }

    public function test_territorial_structure_counts_voters_and_promoters_automatically(): void
    {
        $c = $this->excelCampaign();
        $cabecera = Community::create(['name' => 'Cabecera']);

        $this->actingAs($this->gerente)->post("/campanas/{$c->id}/estructura", [
            'community_id' => $cabecera->id, 'responsable' => 'Ana', 'listado_nominal' => 4500, 'meta_votos' => 2100,
        ])->assertRedirect();
        // «Otra» escrita a mano se agrega al catálogo
        $this->actingAs($this->gerente)->post("/campanas/{$c->id}/estructura", [
            'community_id' => 'otro', 'community_new' => 'San Pedro', 'listado_nominal' => 1000, 'meta_votos' => 30,
        ])->assertRedirect();
        $this->assertNotNull(Community::where('name', 'San Pedro')->first());
        // No se repite la misma comunidad
        $this->actingAs($this->gerente)->post("/campanas/{$c->id}/estructura", [
            'community_id' => $cabecera->id, 'listado_nominal' => 1, 'meta_votos' => 1,
        ])->assertSessionHasErrors('community_id');

        $promoter = CampaignPromoter::create(['campaign_id' => $c->id, 'name' => 'Juan', 'community_id' => $cabecera->id, 'meta_votos' => 10]);
        foreach (range(1, 3) as $i) {
            CampaignVoter::create(['campaign_id' => $c->id, 'nombre' => "Votante {$i}", 'community_id' => $cabecera->id, 'promoter_id' => $promoter->id]);
        }

        $data = (new CampaignMetrics($c))->territoryRows();
        $row = $data['rows']->firstWhere(fn ($r) => $r->model->community_id === $cabecera->id);
        $this->assertSame(3, $row->identificados);
        $this->assertSame(210, $row->necesarios);   // REDONDEAR.MAS(2100 / 10)
        $this->assertSame(1, $row->reclutados);
        $this->assertSame(209, $row->faltan);
        $this->assertSame(2130, $data['totals']->meta);
        $this->assertSame(213, $data['totals']->necesarios);
    }

    public function test_voters_and_promoters_belong_to_their_campaign(): void
    {
        $uno = $this->excelCampaign(['name' => 'Campaña uno']);
        $dos = $this->excelCampaign(['name' => 'Campaña dos']);
        $promotorDos = CampaignPromoter::create(['campaign_id' => $dos->id, 'name' => 'Promotor de dos', 'meta_votos' => 10]);

        // No se puede asignar un promotor de otra campaña
        $this->actingAs($this->gerente)->post("/campanas/{$uno->id}/votantes", ['nombre' => 'María López', 'promoter_id' => $promotorDos->id])
            ->assertSessionHasErrors('promoter_id');

        $this->actingAs($this->gerente)->post("/campanas/{$uno->id}/votantes", [
            'nombre' => 'María López', 'seccion' => '0512', 'casilla' => 'B1', 'telefono' => '442-000-0000',
            'community_id' => 'otro', 'community_new' => 'Cabecera', 'afinidad' => 'Duro', 'recibe_apoyo' => '0',
        ])->assertRedirect("/campanas/{$uno->id}/votantes");

        $voter = CampaignVoter::where('nombre', 'María López')->first();
        $this->assertSame($uno->id, $voter->campaign_id);
        $this->assertFalse($voter->recibe_apoyo);
        $this->assertSame('Cabecera', $voter->community->name);

        // Rutas anidadas: un votante de la campaña uno no existe dentro de la campaña dos
        $this->actingAs($this->gerente)->get("/campanas/{$dos->id}/votantes/{$voter->id}/edit")->assertNotFound();
        $this->actingAs($this->gerente)->get("/campanas/{$uno->id}/votantes/{$voter->id}/edit")->assertOk()->assertSee('María López');
        $this->actingAs($this->gerente)->get("/campanas/{$dos->id}/votantes")->assertDontSee('María López');

        // «Guardar y capturar otro» conserva comunidad y sección
        $this->actingAs($this->gerente)->post("/campanas/{$uno->id}/votantes", [
            'nombre' => 'Pedro Ruiz', 'seccion' => '0513', 'community_id' => $voter->community_id, 'otro' => '1',
        ])->assertRedirect("/campanas/{$uno->id}/votantes/create?community_id={$voter->community_id}&seccion=0513");
    }

    public function test_promoter_progress_and_csv_exports(): void
    {
        $c = $this->excelCampaign();
        $this->actingAs($this->admin)->post("/campanas/{$c->id}/promotores", [
            'name' => 'Juan Pérez', 'phone' => '442', 'secciones' => '0512', 'meta_votos' => 10, 'verificado' => '1',
        ])->assertRedirect();
        $promoter = CampaignPromoter::first();
        $this->assertTrue($promoter->verificado);

        foreach (range(1, 4) as $i) {
            CampaignVoter::create(['campaign_id' => $c->id, 'nombre' => "Votante {$i}", 'promoter_id' => $promoter->id, 'recibe_apoyo' => true]);
        }

        $this->actingAs($this->admin)->get("/campanas/{$c->id}/promotores")->assertSee('Juan Pérez')->assertSee('40.0%');

        $csv = $this->actingAs($this->admin)->get("/campanas/{$c->id}/promotores/exportar")->assertOk()->streamedContent();
        $this->assertStringContainsString('1,"Juan Pérez",442,,0512,,10,4,40,Sí', $csv);

        $csv = $this->actingAs($this->admin)->get("/campanas/{$c->id}/votantes/exportar")->assertOk()->streamedContent();
        $this->assertStringContainsString('¿Recibe apoyo gob?', $csv);
        $this->assertSame(5, substr_count(trim($csv), "\n") + 1);

        // Al eliminar al promotor, sus votantes quedan sin promotor
        $this->actingAs($this->admin)->delete("/campanas/{$c->id}/promotores/{$promoter->id}")->assertRedirect();
        $this->assertSame(4, CampaignVoter::whereNull('promoter_id')->count());
    }

    public function test_kpi_board_traffic_lights(): void
    {
        $this->assertSame('—', CampaignMetrics::status(0, 5)['label']);
        $this->assertSame('Verde', CampaignMetrics::status(0.8, 0.82)['label']);
        $this->assertSame('Ámbar', CampaignMetrics::status(100, 60)['label']);
        $this->assertSame('Rojo', CampaignMetrics::status(100, 59)['label']);

        $c = $this->excelCampaign();
        $kpi = $c->kpis()->first();
        $this->actingAs($this->gerente)->put("/campanas/{$c->id}/kpi/{$kpi->id}", [
            'nombre' => $kpi->nombre, 'meta' => 80, 'valor' => 82, 'es_porcentaje' => '1', 'fecha_corte' => 'jun-2026 (B&M)',
        ])->assertRedirect();
        $this->assertEqualsWithDelta(0.82, $kpi->fresh()->valor, 0.0001);

        $this->actingAs($this->gerente)->get("/campanas/{$c->id}/kpi")
            ->assertSee('Votos identificados (base de votantes)')
            ->assertSee('Verde')
            ->assertSee('jun-2026 (B&amp;M)', false);
    }

    public function test_campaign_lifecycle_and_used_communities_are_protected(): void
    {
        $c = $this->excelCampaign();
        $community = Community::create(['name' => 'Usada en campaña']);
        CampaignVoter::create(['campaign_id' => $c->id, 'nombre' => 'X', 'community_id' => $community->id]);

        // La comunidad usada en la campaña no se puede borrar del catálogo
        $this->actingAs($this->gerente)->delete("/catalogos/comunidades/{$community->id}")->assertSessionHasErrors('name', null, 'comunidades');
        $this->assertNotNull($community->fresh());

        // Con votantes no se elimina: se archiva
        $this->actingAs($this->gerente)->delete("/campanas/{$c->id}")->assertSessionHasErrors('name');
        $this->actingAs($this->gerente)->patch("/campanas/{$c->id}/archivar")->assertRedirect();
        $this->assertFalse($c->fresh()->active);

        $vacia = $this->excelCampaign(['name' => 'Vacía']);
        $this->actingAs($this->gerente)->delete("/campanas/{$vacia->id}")->assertRedirect('/campanas');
        $this->assertNull($vacia->fresh());
    }
}
