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
        $this->act($emp, $leader, 'completada', '2026-11-10', '2026-11-09 10:00:00');
        $this->act($emp, $leader, 'completada', '2026-11-09', '2026-11-10 10:00:00');
        $this->act($emp, $leader, 'pendiente', '2026-11-10');
        $this->act($emp, $leader, 'cancelada', null);

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
}
