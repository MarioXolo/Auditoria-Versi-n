<?php

namespace Tests\Feature;

use App\Models\Activity;
use App\Models\Attendance;
use App\Models\Team;
use App\Models\User;
use App\Services\DashboardMetrics;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\Concerns\MakesActivities;
use Tests\TestCase;

class DashboardMetricsTest extends TestCase
{
    use MakesActivities;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        // Miércoles a mediodía, para tener días laborales previsibles
        Carbon::setTestNow(Carbon::parse('2026-11-11 12:00:00', config('app.timezone')));
        config(['auditoria.dias_laborales' => [1, 2, 3, 4, 5, 6], 'auditoria.hora_entrada' => '09:00', 'auditoria.tolerancia_minutos' => 10]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function act(User $to, User $by, string $status, ?string $due, ?string $completed = null): Activity
    {
        $a = new Activity([
            'activity_type_id' => $this->typeId(), 'community_id' => $this->communityId(),
            'assigned_to' => $to->id, 'created_by' => $by->id, 'team_id' => $to->team_id,
            'status' => $status, 'start_date' => '2026-11-08', 'end_date' => $due ?? '2026-11-20', 'completed_at' => $completed,
        ]);
        $a->created_at = now()->subDays(3);
        $a->updated_at = now()->subDays(3);
        $a->save();

        return $a;
    }

    public function test_kpis_follow_documented_formulas(): void
    {
        $leader = User::factory()->role('encargado')->create();
        $team = Team::create(['name' => 'Norte', 'leader_id' => $leader->id]);
        $leader->update(['team_id' => $team->id]);
        $emp = User::factory()->create(['team_id' => $team->id]);

        // 2 completadas (1 a tiempo, 1 tarde), 1 vencida, 1 cancelada
        $this->act($emp, $leader, 'concluido', '2026-11-10', '2026-11-09 10:00:00');
        $this->act($emp, $leader, 'concluido', '2026-11-09', '2026-11-10 10:00:00');
        $this->act($emp, $leader, 'no_iniciado', '2026-11-10');
        $this->act($emp, $leader, 'suspendida', null);

        // El checador ya no interviene: un registro de asistencia no cambia ningún indicador
        Attendance::create(['user_id' => $emp->id, 'work_date' => '2026-11-09', 'check_in_at' => '2026-11-09 09:30:00']);

        $m = new DashboardMetrics('7');
        $a = $m->activityKpis($m->from, $m->to);

        $this->assertSame(4, $a['total']);
        $this->assertSame(2, $a['completed']);
        $this->assertSame(1, $a['overdue']);
        $this->assertEqualsWithDelta(66.7, $a['cumplimiento'], 0.05); // 2 / (4 - 1)
        $this->assertEqualsWithDelta(50.0, $a['a_tiempo'], 0.05);     // 1 / 2

        $kpis = $m->summary()['now'];
        $this->assertArrayNotHasKey('asistencia', $kpis);
        $this->assertArrayNotHasKey('puntualidad', $kpis);

        // Indicadores por encargado: cuántas tiene y cuántas se cargó él mismo
        $row = $m->people()->firstWhere('user.id', $emp->id);
        $this->assertSame(4, $row->assigned);
        $this->assertSame(0, $row->propias);

        $this->assertSame('ok', DashboardMetrics::status('cumplimiento', 90.0));
        $this->assertSame('warn', DashboardMetrics::status('cumplimiento', 70.0));
    }

    public function test_dashboard_renders_and_exports_csv(): void
    {
        $admin = User::factory()->role('admin')->create();
        $this->seed(\Database\Seeders\DemoSeeder::class);

        foreach (['7', '30', '90', 'all'] as $period) {
            $this->actingAs($admin)->get('/dashboard?period='.$period)
                ->assertOk()
                ->assertSee('Cumplimiento')
                ->assertSee('Cómo se calcula cada indicador');
        }

        $response = $this->actingAs($admin)->get('/dashboard/exportar?period=30');
        $response->assertOk();
        $csv = $response->streamedContent();
        $this->assertStringContainsString('Cumplimiento %', $csv);
        $this->assertStringContainsString('promotor.norte@demo.test', $csv);
        $this->assertStringNotContainsString('Asistencia %', $csv);

        $encargado = User::where('email', 'promotor.norte@demo.test')->first();
        $this->actingAs($encargado)->get('/dashboard/exportar')->assertForbidden();
    }

    public function test_dashboard_shows_every_status_and_no_team_comparison(): void
    {
        $gerente = User::factory()->role('gerente')->create();
        $empleado = User::factory()->create();
        foreach (['no_iniciado', 'en_proceso', 'en_proceso', 'suspendida', 'concluido', 'no_completada'] as $status) {
            $this->act($empleado, $gerente, $status, '2026-11-20', $status === 'concluido' ? now()->subDay() : null);
        }

        $response = $this->actingAs($gerente)->get('/dashboard?period=30')->assertOk()
            ->assertSee('Actividades por estado')
            ->assertSeeInOrder(['No iniciado', 'En proceso', 'Suspendida', 'Concluido', 'No completada'])
            ->assertSee(route('activities.index', ['status' => 'en_proceso']), false)
            ->assertSee('33.3 %')
            ->assertDontSee('Comparativo por equipo')
            ->assertDontSee('chart-teams');

        $this->assertSame([1, 2, 1, 1, 1], array_column((new DashboardMetrics('30'))->byStatus(), 'value'));
    }

    public function test_evidence_quality_and_review_speed(): void
    {
        $gerente = User::factory()->role('gerente')->create();
        $encargado = User::factory()->role('encargado')->create(['name' => 'Elena Encargada']);
        $empleado = User::factory()->create();

        // Concluidas: una con foto, una con texto, una sin nada. Revisadas 0, 2 y 4 días después de su fecha de fin.
        foreach ([['evidence_path' => 'evidencias/a.jpg', 'marked_at' => '2026-11-09 10:00'], ['evidence_text' => 'Se visitó la comunidad', 'marked_at' => '2026-11-11 10:00'], ['marked_at' => '2026-11-13 10:00']] as $extra) {
            $a = $this->act($empleado, $gerente, 'concluido', '2026-11-09', '2026-11-09 09:00');
            $a->forceFill($extra + ['marked_by' => $encargado->id])->save();
        }
        // Dos abiertas con fecha de fin pasada: esperan revisión (la más antigua, 6 días)
        $this->act($empleado, $gerente, 'en_proceso', '2026-11-05');
        $this->act($empleado, $gerente, 'no_iniciado', '2026-11-10');

        $q = (new DashboardMetrics('30'))->evidenceAndReview();
        $this->assertSame([3, 1, 1, 1], [$q['concluded'], $q['photo'], $q['text'], $q['none']]);
        $this->assertSame(66.7, $q['with_pct']);
        $this->assertSame(2.0, $q['review_days']);
        $this->assertSame(2, $q['pending']);
        $this->assertSame(6, $q['pending_max_days']);
        $this->assertSame('Elena Encargada', $q['reviewers'][0]->name);
        $this->assertSame(3, $q['reviewers'][0]->count);

        $this->actingAs($gerente)->get('/dashboard?period=30')->assertOk()
            ->assertSee('Calidad de la evidencia')->assertSee('Velocidad de revisión')
            ->assertSee('Elena Encargada')->assertSee('Ver las concluidas sin evidencia');

        // La liga abre solo las concluidas sin evidencia
        $this->actingAs($gerente)->get('/activities?status=concluido&evidencia=sin')->assertOk()
            ->assertSee('sin evidencia')->assertViewHas('activities', fn ($p) => $p->total() === 1);
    }

    public function test_duration_and_before_or_after_deadline(): void
    {
        $gerente = User::factory()->role('gerente')->create();
        $enc = User::factory()->role('encargado')->create(['name' => 'Elena']);

        // Inicio 8 nov. Fin 10 nov. Terminada el 9 (1 día antes), el 10 (en la fecha) y el 11 (1 día después)
        $antes = $this->act($enc, $gerente, 'concluido', '2026-11-10', '2026-11-09 10:00');
        $enFecha = $this->act($enc, $gerente, 'concluido', '2026-11-10', '2026-11-10 18:00');
        $despues = $this->act($enc, $gerente, 'concluido', '2026-11-10', '2026-11-11 09:00');
        $abierta = $this->act($enc, $gerente, 'en_proceso', '2026-11-10');

        $this->assertSame([2, 3, 4], [$antes->durationDays(), $enFecha->durationDays(), $despues->durationDays()]);
        $this->assertSame(3, $antes->plannedDays());
        $this->assertSame([-1, 0, 1], [$antes->deadlineDelta(), $enFecha->deadlineDelta(), $despues->deadlineDelta()]);
        $this->assertSame(['antes', 'a_tiempo', 'despues'], [$antes->deadlineResult(), $enFecha->deadlineResult(), $despues->deadlineResult()]);
        $this->assertSame('Terminó 1 día después', $despues->deadlineLabel());
        $this->assertSame('Terminó 1 día antes', $antes->deadlineLabel());
        $this->assertNull($abierta->durationDays());

        // Mismo día de inicio y fin, terminada ese día: dura 1 día y queda «en la fecha»
        $rapida = $this->act($enc, $gerente, 'concluido', '2026-11-08', '2026-11-08 12:00');
        $this->assertSame(1, $rapida->durationDays());

        $d = (new DashboardMetrics('30'))->durations();
        $this->assertSame(4, $d['total']);
        $this->assertSame([1, 2, 1], [$d['antes'], $d['en_fecha'], $d['despues']]);
        $this->assertEqualsWithDelta(2.5, $d['duracion'], 0.05);     // (2 + 3 + 4 + 1) / 4
        $this->assertEqualsWithDelta(75.0, $d['a_tiempo_pct'], 0.05); // 3 de 4 antes o en la fecha
        $this->assertEqualsWithDelta(1.0, $d['dias_retraso'], 0.05);

        $this->actingAs($gerente)->get('/dashboard?period=30')->assertOk()
            ->assertSee('Duración de las actividades')->assertSee('Terminó 1 día después')->assertSee('Elena');
        $this->actingAs($gerente)->get("/activities/{$despues->id}")->assertSee('Duró')->assertSee('Terminó 1 día después');
    }

    public function test_encargado_has_his_own_dashboard_with_only_his_activities(): void
    {
        $gerente = User::factory()->role('gerente')->create(['name' => 'Gerardo Gerente']);
        $yo = User::factory()->role('encargado')->create();
        $otro = User::factory()->role('encargado')->create();

        // Me asignaron 3 (una concluida antes de tiempo, una vencida) y me cargué 2
        $this->act($yo, $gerente, 'concluido', '2026-11-10', '2026-11-09 10:00');
        $this->act($yo, $gerente, 'no_iniciado', '2026-11-10');
        $this->act($yo, $gerente, 'en_proceso', '2026-11-14');
        $this->act($yo, $yo, 'en_proceso', '2026-11-20');
        $this->act($yo, $yo, 'suspendida', '2026-11-20');
        // Del otro encargado: no debe contar ni verse
        $ajena = $this->act($otro, $gerente, 'no_iniciado', '2026-11-05');

        $html = $this->actingAs($yo)->get('/dashboard')->assertOk()
            ->assertSee('Mi dashboard')->assertSee('Me las asignaron')->assertSee('Me las cargué yo')
            ->assertSee('Gerardo Gerente (3)')
            ->assertSee('Estado de mis actividades')
            ->assertSee('Duración de las actividades')->assertSee('Terminó 1 día antes')
            ->assertDontSee($ajena->code)
            ->viewData('total');
        $this->assertSame(5, $html);

        $v = $this->actingAs($yo)->get('/dashboard');
        $this->assertSame(3, $v->viewData('asignadas'));
        $this->assertSame(2, $v->viewData('propias'));
        $this->assertSame([1, 2, 1, 1, 0], array_column($v->viewData('byStatus'), 'value'));
        $this->assertCount(1, $v->viewData('overdue'));                       // la que vencía el 10
        $this->assertSame(1, $v->viewData('durations')['antes']);
        $this->assertEqualsWithDelta(25.0, $v->viewData('cumplimiento'), 0.05); // 1 / (5 − 1 suspendida)

        // El dashboard de auditoría no es para él
        $this->actingAs($yo)->get('/dashboard')->assertDontSee('Dashboard de auditoría');
    }
}
