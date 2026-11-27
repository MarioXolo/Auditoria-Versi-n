<?php

namespace Tests\Feature;

use App\Models\Campaign;
use App\Models\User;
use App\Services\CuadernoSemaforo;
use App\Services\StrategyBooks;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Semáforo automático del Cuaderno Maestro: nadie lo captura, sale de fechas, estatus y capturas. */
class CuadernoSemaforoTest extends TestCase
{
    use RefreshDatabase;

    private User $gerente;
    private Campaign $campaign;
    private string $libro;

    protected function setUp(): void
    {
        parent::setUp();
        $this->gerente = User::factory()->role('gerente')->create();
        $this->actingAs($this->gerente)->post('/campanas', [
            'name' => 'Huimilpan 2027', 'municipality' => 'Huimilpan', 'listado_nominal' => 30000,
            'participacion' => 55, 'objetivo' => 50, 'colchon' => 10, 'votantes_por_promotor' => 10,
        ]);
        $this->campaign = Campaign::first();
        $this->libro = array_key_first(StrategyBooks::ofPunto(12));
    }

    private function record(array $attrs)
    {
        return $this->campaign->strategyRecords()->create(['libro' => $this->libro, 'estatus' => 'Pendiente', 'datos' => [], 'created_by' => $this->gerente->id] + $attrs);
    }

    private function color(int $num): array
    {
        return (new CuadernoSemaforo($this->campaign->fresh()))->burbujas([$num => true])[$num];
    }

    public function test_empty_bubble_is_gray(): void
    {
        $this->assertSame('gris', (new CuadernoSemaforo($this->campaign))->burbujas([12 => false])[12]['color']);
    }

    public function test_past_date_not_done_turns_red_and_done_turns_it_off(): void
    {
        $r = $this->record(['fecha' => today()->subDays(2)]);
        $this->assertSame('vencido', $r->semaforo());
        $this->assertSame('rojo', $this->color(12)['color']);
        $this->assertStringContainsString('1 vencido', $this->color(12)['motivo']);

        $r->update(['estatus' => 'Hecho']);
        $this->assertSame('hecho', $r->fresh()->semaforo());
        $this->assertSame('verde', $this->color(12)['color']);
    }

    public function test_upcoming_date_is_yellow_and_far_date_is_green(): void
    {
        $r = $this->record(['fecha' => today()->addDays(3)]);
        $this->assertSame('amarillo', $this->color(12)['color']);

        $r->update(['fecha' => today()->addDays(40)]);
        $this->assertSame('en_tiempo', $r->fresh()->semaforo());
        $this->assertSame('verde', $this->color(12)['color']);
    }

    public function test_no_captures_for_many_days_turns_yellow(): void
    {
        $r = $this->record(['fecha' => today()->addDays(40)]);
        $r->timestamps = false;
        $r->forceFill(['updated_at' => now()->subDays(20)])->save();

        $sem = $this->color(12);
        $this->assertSame('amarillo', $sem['color']);
        $this->assertStringContainsString('Sin capturas hace 20 días', $sem['motivo']);
    }

    public function test_overdue_schedule_task_turns_bubble_8_red(): void
    {
        $this->campaign->tasks()->create(['tarea' => 'Registrar planilla', 'fecha_limite' => today()->subDay(), 'estatus' => 'Pendiente']);
        $this->assertSame('rojo', $this->color(8)['color']);
    }

    public function test_cuaderno_page_and_book_list_show_the_semaphore(): void
    {
        $this->record(['fecha' => today()->subDays(2)]);

        $this->actingAs($this->gerente)->get("/campanas/{$this->campaign->id}")
            ->assertOk()->assertSee('Semáforo')->assertSee('data-semaforo="rojo"', false)->assertSee('1 vencido');

        $this->actingAs($this->gerente)->get("/campanas/{$this->campaign->id}/estrategia/{$this->libro}?estatus=vencidos")
            ->assertOk()->assertSee('Vencido');
    }
}
