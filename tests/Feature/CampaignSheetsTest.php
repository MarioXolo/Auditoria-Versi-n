<?php

namespace Tests\Feature;

use App\Models\Campaign;
use App\Models\Community;
use App\Models\User;
use App\Services\CampaignMetrics;
use App\Services\CampaignSheets;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** V2 · etapa 2: pestañas 5 (actores), 6 (defensa del voto), 7 (presupuesto), 8 (cronograma) y 10 (logros). */
class CampaignSheetsTest extends TestCase
{
    use RefreshDatabase;

    private User $gerente;
    private Campaign $campaign;

    protected function setUp(): void
    {
        parent::setUp();
        $this->gerente = User::factory()->role('gerente')->create();
        $this->actingAs($this->gerente)->post('/campanas', [
            'name' => 'Huimilpan 2027', 'municipality' => 'Huimilpan', 'listado_nominal' => 30000,
            'participacion' => 55, 'objetivo' => 50, 'colchon' => 10, 'votantes_por_promotor' => 10,
        ]);
        $this->campaign = Campaign::first();
    }

    private function url(string $path = ''): string
    {
        return "/campanas/{$this->campaign->id}{$path}";
    }

    public function test_every_tab_is_enabled_and_restricted_to_admins_and_managers(): void
    {
        $admin = User::factory()->role('admin')->create();
        foreach ([$this->gerente, $admin] as $u) {
            $this->actingAs($u)->get($this->url())->assertOk()->assertDontSee('Etapa 2');
            foreach (CampaignSheets::all() as $key => $sheet) {
                $this->actingAs($u)->get($this->url("/{$key}"))->assertOk()->assertSee($sheet['tab']);
                $this->actingAs($u)->get($this->url("/{$key}/exportar"))->assertOk();
            }
        }

        foreach ([User::factory()->role('encargado')->create(), User::factory()->create()] as $u) {
            $this->actingAs($u)->get($this->url('/actores'))->assertForbidden();
            $this->actingAs($u)->post($this->url('/logros'), ['logro' => 'X'])->assertForbidden();
        }

        $this->actingAs($this->gerente)->get($this->url('/otra-cosa'))->assertNotFound();
    }

    public function test_actors_are_captured_with_excel_columns_and_typed_community(): void
    {
        $this->actingAs($this->gerente)->post($this->url('/actores'), [
            'nombre' => 'Comisariado ejidal X', 'tipo' => 'Comisariado ejidal', 'community_id' => 'otro', 'community_new' => 'Comunidad Y',
            'postura' => 'Afín', 'votos_influye' => 150, 'contactado' => '1', 'responsable' => 'Coord. territorial', 'notas' => 'Pide apoyo a caminos',
        ])->assertSessionHasNoErrors()->assertRedirect();

        $actor = $this->campaign->actors()->first();
        $this->assertSame('Comunidad Y', $actor->community->name);
        $this->assertTrue($actor->contactado);
        $this->assertSame(150, $actor->votos_influye);
        $this->assertTrue(Community::find($actor->community_id)->inUse());

        $this->actingAs($this->gerente)->put($this->url("/actores/{$actor->id}"), [
            'nombre' => 'Comisariado ejidal X', 'community_id' => $actor->community_id, 'postura' => 'Adverso', 'votos_influye' => 90,
        ])->assertSessionHasNoErrors()->assertRedirect();
        $actor->refresh();
        $this->assertSame('Adverso', $actor->postura);
        $this->assertFalse($actor->contactado, 'la casilla sin marcar debe guardarse como No');

        $this->actingAs($this->gerente)->post($this->url('/actores'), ['nombre' => 'Y', 'postura' => 'Simpatizante'])->assertSessionHasErrors('postura');
        $this->actingAs($this->gerente)->get($this->url('/actores?postura=Adverso'))->assertSee('Comisariado ejidal X');
        $this->actingAs($this->gerente)->get($this->url('/actores?postura=Afín'))->assertSee('Ningún registro coincide');
    }

    public function test_polling_stations_feed_the_rc_kpi_and_reject_duplicates(): void
    {
        $post = fn (array $d) => $this->actingAs($this->gerente)->post($this->url('/defensa-del-voto'), $d);
        $post(['seccion' => '0512', 'casilla' => 'B1', 'rc_propietario' => 'Pedro Sánchez', 'rc_suplente' => 'Rosa Díaz', 'acreditado' => '1'])->assertSessionHasNoErrors()->assertRedirect();
        $post(['seccion' => '0512', 'casilla' => 'C1', 'rc_propietario' => 'Juan'])->assertSessionHasNoErrors()->assertRedirect();
        $post(['seccion' => '0512', 'casilla' => 'B1'])->assertSessionHasErrors('seccion');

        $rc = (new CampaignMetrics($this->campaign))->kpis()->firstWhere('nombre', '% casillas con RC propietario + suplente');
        $this->assertSame(2, $rc->meta);
        $this->assertSame(1, $rc->valor);
        $this->assertSame('Rojo', $rc->status['label']); // 50 %

        $this->actingAs($this->gerente)->get($this->url('/defensa-del-voto?acreditado=si'))->assertSee('Pedro Sánchez')->assertDontSee('value="Juan"', false);
    }

    public function test_budget_starts_with_excel_items_and_warns_when_over_the_cap(): void
    {
        $items = $this->campaign->budgetItems()->orderBy('id')->get();
        $this->assertSame(array_column(Campaign::DEFAULT_BUDGET, 'rubro'), $items->pluck('rubro')->all());
        $this->assertEqualsWithDelta(1.0, $items->sum('porcentaje'), 0.0001);

        $this->actingAs($this->gerente)->put($this->url('/presupuesto/tope'), ['tope_gastos' => 1000000])->assertSessionHasNoErrors()->assertRedirect();
        $first = $items->first();
        $this->actingAs($this->gerente)->put($this->url("/presupuesto/{$first->id}"), [
            'rubro' => $first->rubro, 'porcentaje' => 40, 'asignado' => 400000, 'ejercido' => 150000,
        ])->assertSessionHasNoErrors()->assertRedirect();
        $first->refresh();
        $this->assertEqualsWithDelta(0.40, $first->porcentaje, 0.0001);
        $this->assertSame(250000.0, $first->disponible());

        $this->actingAs($this->gerente)->get($this->url('/presupuesto'))->assertSee('SÍ ✓')->assertSee('$250,000');

        $gasto = (new CampaignMetrics($this->campaign->fresh()))->kpis()->firstWhere('nombre', 'Gasto ejercido (% del tope)');
        $this->assertSame('Verde', $gasto->status['label']); // 15 % del tope: gastar poco es bueno

        $this->actingAs($this->gerente)->put($this->url("/presupuesto/{$first->id}"), ['rubro' => $first->rubro, 'porcentaje' => 40, 'asignado' => 1200000, 'ejercido' => 1100000]);
        $this->actingAs($this->gerente)->get($this->url('/presupuesto'))->assertSee('rebasa el tope legal');
        $gasto = (new CampaignMetrics($this->campaign->fresh()))->kpis()->firstWhere('nombre', 'Gasto ejercido (% del tope)');
        $this->assertSame('Rebasa tope', $gasto->status['label']);
    }

    public function test_schedule_template_loads_once_and_flags_overdue_tasks(): void
    {
        $this->actingAs($this->gerente)->get($this->url('/cronograma'))->assertSee('tareas sugeridas del Excel');
        $this->actingAs($this->gerente)->post($this->url('/cronograma/plantilla'))->assertSessionHasNoErrors()->assertRedirect();
        $this->assertSame(count(CampaignSheets::TASK_TEMPLATE), $this->campaign->tasks()->count());
        $this->actingAs($this->gerente)->post($this->url('/cronograma/plantilla'))->assertSessionHasErrors('tarea');
        $this->assertSame(count(CampaignSheets::TASK_TEMPLATE), $this->campaign->tasks()->count());

        $this->actingAs($this->gerente)->post($this->url('/cronograma'), [
            'fase' => '0. Arranque', 'tarea' => 'Tarea atrasada', 'fecha_limite' => today()->subDay()->toDateString(), 'estatus' => 'En curso',
        ])->assertSessionHasNoErrors()->assertRedirect();
        $task = $this->campaign->tasks()->latest('id')->first();
        $this->assertTrue($task->vencida());

        $this->actingAs($this->gerente)->get($this->url('/cronograma?vencidas=1'))->assertSee('Tarea atrasada')->assertDontSee('Encargar encuesta basal');

        $this->actingAs($this->gerente)->put($this->url("/cronograma/{$task->id}"), ['tarea' => 'Tarea atrasada', 'fecha_limite' => $task->fecha_limite->toDateString(), 'estatus' => 'Hecho']);
        $this->assertFalse($task->fresh()->vencida());
    }

    public function test_achievements_and_rows_stay_inside_their_campaign(): void
    {
        $this->actingAs($this->gerente)->post($this->url('/logros'), [
            'logro' => 'Nuevas patrullas + subcomandancia', 'pilar' => 'Seguridad', 'lugar' => 'Cabecera y delegaciones',
            'beneficiados' => 'Todo el municipio', 'evidencia' => '1', 'frase' => 'Hoy Huimilpan tiene más patrullas que nunca',
        ])->assertSessionHasNoErrors()->assertRedirect();
        $logro = $this->campaign->achievements()->first();
        $this->assertNull($logro->recurso);

        $csv = $this->actingAs($this->gerente)->get($this->url('/logros/exportar'))->streamedContent();
        $this->assertStringContainsString('Logro / Obra / Programa', $csv);
        $this->assertStringContainsString('Seguridad', $csv);

        // Otra campaña no puede tocar las filas de esta
        $this->actingAs($this->gerente)->post('/campanas', ['name' => 'Otra', 'municipality' => 'X', 'listado_nominal' => 1, 'participacion' => 50, 'objetivo' => 50, 'colchon' => 0, 'votantes_por_promotor' => 10]);
        $other = Campaign::latest('id')->first();
        $this->actingAs($this->gerente)->delete("/campanas/{$other->id}/logros/{$logro->id}")->assertNotFound();
        $this->actingAs($this->gerente)->delete($this->url("/logros/{$logro->id}"))->assertRedirect();
        $this->assertSame(0, $this->campaign->achievements()->count());

        // Con información capturada la campaña ya no se puede borrar, solo archivar
        $this->actingAs($this->gerente)->post($this->url('/actores'), ['nombre' => 'Líder']);
        $this->actingAs($this->gerente)->delete($this->url())->assertSessionHasErrors('name');
    }
}
