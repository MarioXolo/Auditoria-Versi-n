<?php

namespace Tests\Feature;

use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\Concerns\MakesActivities;
use Tests\TestCase;

class CalendarTest extends TestCase
{
    use MakesActivities;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow(Carbon::parse('2026-11-11 12:00:00', config('app.timezone')));
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_calendar_shows_visible_activities_by_date_and_is_printable(): void
    {
        $gerente = User::factory()->role('gerente')->create();
        $encargado = User::factory()->role('encargado')->create();
        $team = Team::create(['name' => 'Norte', 'leader_id' => $encargado->id]);
        $encargado->update(['team_id' => $team->id]);
        $ana = User::factory()->create(['team_id' => $team->id]);
        $beto = User::factory()->create(['team_id' => $team->id]);

        $deAna = $this->makeActivity(['assigned_to' => $ana->id, 'created_by' => $encargado->id, 'team_id' => $team->id,
            'start_date' => '2026-11-03', 'end_date' => '2026-11-06'], 'El Mirador');
        $deBeto = $this->makeActivity(['assigned_to' => $beto->id, 'created_by' => $encargado->id, 'team_id' => $team->id,
            'start_date' => '2026-11-20', 'end_date' => '2026-11-20'], 'Los Pinos');
        $diciembre = $this->makeActivity(['assigned_to' => $ana->id, 'created_by' => $encargado->id, 'team_id' => $team->id,
            'start_date' => '2026-12-02', 'end_date' => '2026-12-03'], 'Centro');

        foreach ([$gerente, $encargado, $ana] as $u) {
            $this->actingAs($u)->get('/calendario')->assertOk()->assertSee('Noviembre 2026')->assertSee('Imprimir / PDF');
        }

        $this->actingAs($gerente)->get('/calendario')
            ->assertSee($deAna->code)->assertSee($deBeto->code)->assertDontSee($diciembre->code)
            ->assertSee('Calendario de actividades · Noviembre 2026') // encabezado impreso
            ->assertSee('Detalle de actividades');

        // El empleado solo ve las suyas
        $this->actingAs($ana)->get('/calendario')->assertSee($deAna->code)->assertDontSee($deBeto->code);

        // Otro mes y filtros
        $this->actingAs($gerente)->get('/calendario?mes=2026-12')->assertSee('Diciembre 2026')->assertSee($diciembre->code)->assertDontSee($deAna->code);
        $this->actingAs($gerente)->get('/calendario?assigned_to='.$beto->id)->assertSee($deBeto->code)->assertDontSee($deAna->code);

        // «Todos los días que dura»: la de Ana (3 al 6) aparece 4 veces en la cuadrícula
        $html = $this->actingAs($gerente)->get('/calendario?modo=duracion')->getContent();
        $this->assertSame(4, substr_count($html, 'title="'.$deAna->code.' ·'));
        $html = $this->actingAs($gerente)->get('/calendario')->getContent();
        $this->assertSame(1, substr_count($html, 'title="'.$deAna->code.' ·'));

        // Mes inválido: regresa al actual
        $this->actingAs($gerente)->get('/calendario?mes=abc')->assertOk()->assertSee('Noviembre 2026');
    }
}
