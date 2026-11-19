<?php

namespace Tests\Feature;

use App\Models\Activity;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\MakesActivities;
use Tests\TestCase;

/** Geolocalización por actividad: lugar fijo, validación con el GPS y alerta en rojo. */
class ActivityLocationTest extends TestCase
{
    use MakesActivities;
    use RefreshDatabase;

    private const LAT = 20.3739;
    private const LNG = -100.2732;

    private User $gerente;
    private User $encargado;

    protected function setUp(): void
    {
        parent::setUp();
        $this->gerente = User::factory()->role('gerente')->create();
        $this->encargado = User::factory()->role('encargado')->create();
    }

    private function withPlace(array $extra = []): array
    {
        return $this->payload(array_merge([
            'requiere_ubicacion' => '1',
            'destino_lat' => self::LAT,
            'destino_lng' => self::LNG,
            'destino_radio' => 300,
            'destino_nombre' => 'Plaza de Huimilpan',
        ], $extra));
    }

    private function assignedByGerente(): Activity
    {
        $this->actingAs($this->gerente)->post('/activities', $this->withPlace(['assigned_to' => $this->encargado->id]))->assertRedirect();

        return Activity::latest('id')->firstOrFail();
    }

    public function test_distance_is_computed_in_meters(): void
    {
        $this->assertSame(0, Activity::distanceMeters(self::LAT, self::LNG, self::LAT, self::LNG));
        // 0.01° de latitud ≈ 1.11 km
        $this->assertEqualsWithDelta(1112, Activity::distanceMeters(self::LAT, self::LNG, self::LAT + 0.01, self::LNG), 5);
    }

    public function test_place_is_required_when_the_box_is_checked_and_cleared_when_not(): void
    {
        $this->actingAs($this->gerente)
            ->post('/activities', $this->payload(['assigned_to' => $this->encargado->id, 'requiere_ubicacion' => '1']))
            ->assertSessionHasErrors('destino_lat');

        $this->actingAs($this->gerente)->post('/activities', $this->payload([
            'assigned_to' => $this->encargado->id,
            'requiere_ubicacion' => '0',
            'destino_lat' => self::LAT,
            'destino_lng' => self::LNG,
        ]))->assertRedirect();

        $a = Activity::latest('id')->first();
        $this->assertFalse($a->requiere_ubicacion);
        $this->assertNull($a->destino_lat);
        $this->assertNull($a->locationStatus());
        $this->assertFalse($a->locationAlert());
    }

    public function test_pending_validation_is_a_red_alert_everywhere(): void
    {
        $a = $this->assignedByGerente();

        $this->assertSame('pendiente', $a->locationStatus());
        $this->assertTrue($a->locationAlert());
        $this->assertSame(1, Activity::withLocationAlert()->count());

        $this->actingAs($this->encargado)->get("/activities/{$a->id}")
            ->assertOk()->assertSee('Ubicación no validada')->assertSee('Validar mi ubicación');
        $this->actingAs($this->encargado)->get('/activities?ubicacion=alerta')->assertSee($a->code)->assertSee('Ubicación sin validar');
        $this->actingAs($this->encargado)->get('/dashboard')->assertSee('validar tu ubicación');
        $this->actingAs($this->gerente)->get('/dashboard')->assertSee('requiere ubicación y no está validada');
        // El gerente ve el mapa pero no el botón (solo lo valida quien la tiene asignada)
        $this->actingAs($this->gerente)->get("/activities/{$a->id}")->assertDontSee('Validar mi ubicación');
    }

    public function test_encargado_validates_inside_the_radius(): void
    {
        $a = $this->assignedByGerente();

        $this->actingAs($this->encargado)->post("/activities/{$a->id}/ubicacion", [
            'lat' => self::LAT + 0.001, 'lng' => self::LNG, 'precision' => 15,
        ])->assertRedirect()->assertSessionHas('status');

        $a->refresh();
        $this->assertSame('verificada', $a->locationStatus());
        $this->assertFalse($a->locationAlert());
        $this->assertEqualsWithDelta(111, $a->ubicacion_distancia, 3);
        $this->assertSame(15, $a->ubicacion_precision);
        $this->assertSame(0, Activity::withLocationAlert()->count());
    }

    public function test_outside_the_radius_stays_in_alert(): void
    {
        $a = $this->assignedByGerente();

        $this->actingAs($this->encargado)->post("/activities/{$a->id}/ubicacion", [
            'lat' => self::LAT + 0.01, 'lng' => self::LNG, 'precision' => 10,
        ])->assertRedirect();

        $a->refresh();
        $this->assertSame('fuera', $a->locationStatus());
        $this->assertTrue($a->locationAlert());
        $this->assertStringContainsString('Fuera del lugar', $a->locationLabel());
    }

    public function test_imprecise_gps_is_rejected_and_only_the_assignee_can_validate(): void
    {
        $a = $this->assignedByGerente();

        $this->actingAs($this->encargado)->post("/activities/{$a->id}/ubicacion", [
            'lat' => self::LAT, 'lng' => self::LNG, 'precision' => 500,
        ])->assertSessionHasErrors('ubicacion');
        $this->assertNull($a->fresh()->ubicacion_at);

        $this->actingAs($this->gerente)->post("/activities/{$a->id}/ubicacion", [
            'lat' => self::LAT, 'lng' => self::LNG, 'precision' => 10,
        ])->assertForbidden();

        $otro = User::factory()->role('encargado')->create();
        $this->actingAs($otro)->post("/activities/{$a->id}/ubicacion", [
            'lat' => self::LAT, 'lng' => self::LNG, 'precision' => 10,
        ])->assertForbidden();
    }

    public function test_encargado_cannot_move_the_pin_after_creating_but_gerente_can(): void
    {
        $this->actingAs($this->encargado)->post('/activities', $this->withPlace(['destino_precision' => 12]))->assertRedirect();
        $a = Activity::latest('id')->firstOrFail();
        $this->assertTrue($a->requiere_ubicacion);

        // Intenta mover el lugar a donde está él, agrandar el radio y quitar el requisito
        $this->actingAs($this->encargado)->put("/activities/{$a->id}", $this->payload([
            'requiere_ubicacion' => '0',
            'destino_lat' => self::LAT + 0.5,
            'destino_lng' => self::LNG + 0.5,
            'destino_radio' => 20000,
        ]))->assertRedirect();

        $a->refresh();
        $this->assertTrue($a->requiere_ubicacion);
        $this->assertEqualsWithDelta(self::LAT, (float) $a->destino_lat, 0.0000001);
        $this->assertSame(300, $a->destino_radio);

        // El gerente sí lo corrige, y la distancia ya validada se recalcula
        $this->actingAs($this->encargado)->post("/activities/{$a->id}/ubicacion", ['lat' => self::LAT + 0.01, 'lng' => self::LNG, 'precision' => 10]);
        $this->assertSame('fuera', $a->fresh()->locationStatus());

        $this->actingAs($this->gerente)->put("/activities/{$a->id}", $this->withPlace([
            'assigned_to' => $this->encargado->id,
            'destino_lat' => self::LAT + 0.01,
        ]))->assertRedirect();
        $this->assertSame('verificada', $a->fresh()->locationStatus());
    }

    public function test_missing_location_does_not_block_the_review(): void
    {
        $a = $this->assignedByGerente();

        $this->actingAs($this->gerente)->post("/activities/{$a->id}/marcar", ['marca' => 'concluido'])->assertRedirect();
        $a->refresh();
        $this->assertTrue($a->status->isMarked());
        $this->assertTrue($a->locationAlert());

        // Ya revisada, ya no se puede validar
        $this->actingAs($this->encargado)->post("/activities/{$a->id}/ubicacion", [
            'lat' => self::LAT, 'lng' => self::LNG, 'precision' => 10,
        ])->assertSessionHasErrors('ubicacion');
    }

    public function test_own_location_activity_can_only_be_concluded_after_validating_inside(): void
    {
        $a = $this->assignedByGerente();

        // Sin validar: no la puede concluir él mismo
        $this->actingAs($this->encargado)->get("/activities/{$a->id}")->assertSee('primero valida tu ubicación');
        $this->actingAs($this->encargado)->post("/activities/{$a->id}/marcar", ['marca' => 'concluido'])->assertSessionHasErrors('marca');
        $this->assertFalse($a->fresh()->status->isMarked());

        // Fuera del radio: tampoco
        $this->actingAs($this->encargado)->post("/activities/{$a->id}/ubicacion", ['lat' => self::LAT + 0.01, 'lng' => self::LNG, 'precision' => 10]);
        $this->actingAs($this->encargado)->post("/activities/{$a->id}/marcar", ['marca' => 'concluido'])->assertSessionHasErrors('marca');

        // Dentro del radio: ya puede
        $this->actingAs($this->encargado)->post("/activities/{$a->id}/ubicacion", ['lat' => self::LAT, 'lng' => self::LNG, 'precision' => 10]);
        $this->actingAs($this->encargado)->post("/activities/{$a->id}/marcar", ['marca' => 'concluido'])->assertRedirect()->assertSessionHasNoErrors();
        $this->assertTrue($a->fresh()->status->isMarked());
    }

    public function test_activities_without_location_are_concluded_freely(): void
    {
        $this->actingAs($this->gerente)->post('/activities', $this->payload(['assigned_to' => $this->encargado->id]))->assertRedirect();
        $a = Activity::latest('id')->firstOrFail();

        $this->actingAs($this->encargado)->post("/activities/{$a->id}/marcar", ['marca' => 'concluido'])->assertRedirect()->assertSessionHasNoErrors();
        $this->assertTrue($a->fresh()->status->isMarked());
    }

    public function test_encargado_sets_the_place_only_with_his_gps(): void
    {
        // Sin lectura del GPS (punto puesto a mano) se rechaza
        $this->actingAs($this->encargado)->post('/activities', $this->withPlace())->assertSessionHasErrors('destino_precision');
        // Con señal imprecisa, también
        $this->actingAs($this->encargado)->post('/activities', $this->withPlace(['destino_precision' => 350]))->assertSessionHasErrors('destino_precision');
        $this->assertSame(0, Activity::count());

        // Su formulario no permite tocar el mapa; el del gerente sí
        $this->actingAs($this->encargado)->get('/activities/create')->assertSee('data-manual="0"', false)->assertSee('El punto se toma del GPS');
        $this->actingAs($this->gerente)->get('/activities/create')->assertSee('data-manual="1"', false);

        // El gerente lo marca en el mapa sin GPS para asignarlo a distancia
        $this->actingAs($this->gerente)->post('/activities', $this->withPlace(['assigned_to' => $this->encargado->id]))->assertRedirect()->assertSessionHasNoErrors();
        $this->assertSame(1, Activity::count());
    }
}
