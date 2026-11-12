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

        // Lunes 9 y martes 10: el empleado llega a las 8:55 y 9:30 (retardo). El encargado solo el lunes.
        Attendance::create(['user_id' => $emp->id, 'work_date' => '2026-11-09', 'check_in_at' => '2026-11-09 08:55:00', 'check_out_at' => '2026-11-09 17:55:00']);
        Attendance::create(['user_id' => $emp->id, 'work_date' => '2026-11-10', 'check_in_at' => '2026-11-10 09:30:00', 'check_out_at' => '2026-11-10 17:30:00']);
        Attendance::create(['user_id' => $leader->id, 'work_date' => '2026-11-09', 'check_in_at' => '2026-11-09 09:05:00']);

        $m = new DashboardMetrics('7');
        $a = $m->activityKpis($m->from, $m->to);

        $this->assertSame(4, $a['total']);
        $this->assertSame(2, $a['completed']);
        $this->assertSame(1, $a['overdue']);
        $this->assertEqualsWithDelta(66.7, $a['cumplimiento'], 0.05); // 2 / (4 - 1)
        $this->assertEqualsWithDelta(50.0, $a['a_tiempo'], 0.05);     // 1 / 2

        // Últimos 7 días: jue 5 a mié 11 → 6 días laborales (sin domingo) × 2 personas
        $t = $m->attendanceKpis($m->from, $m->to);
        $this->assertSame(6, $t['workdays']);
        $this->assertSame(12, $t['expected']);
        $this->assertSame(3, $t['present']);
        $this->assertSame(1, $t['late']);
        $this->assertEqualsWithDelta(25.0, $t['asistencia'], 0.05);   // 3 / 12
        $this->assertEqualsWithDelta(66.7, $t['puntualidad'], 0.05);  // 2 / 3
        $this->assertEqualsWithDelta(8.5, $t['horas'], 0.05);         // (9 + 8) / 2

        $leaderRow = $m->leaders()->first();
        $this->assertSame(4, $leaderRow->assigned);

        $this->assertSame('bad', DashboardMetrics::status('asistencia', 25.0));
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
        $this->assertStringContainsString('empleado.norte@demo.test', $csv);

        $empleado = User::where('email', 'empleado.norte@demo.test')->first();
        $this->actingAs($empleado)->get('/dashboard/exportar')->assertForbidden();
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
}
