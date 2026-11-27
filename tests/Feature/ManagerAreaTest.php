<?php

namespace Tests\Feature;

use App\Models\Activity;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\MakesActivities;
use Tests\TestCase;

/** El encargado ve el área del gerente que le asignó la actividad («Técnica te encomendó…»), no su nombre. */
class ManagerAreaTest extends TestCase
{
    use MakesActivities;
    use RefreshDatabase;

    public function test_encargado_sees_the_area_not_the_name(): void
    {
        $juanita = User::factory()->role('gerente')->create(['name' => 'Juanita Pérez', 'area' => 'Técnica']);
        $enc = User::factory()->role('encargado')->create();
        $otro = User::factory()->role('encargado')->create();

        $this->actingAs($juanita)->post('/activities', $this->payload(['assigned_to' => $enc->id]))->assertRedirect();
        $a = Activity::firstOrFail();
        $this->actingAs($juanita)->post("/activities/{$a->id}/marcar", ['marca' => 'no_completada'])->assertRedirect();

        // El encargado: área, nunca el nombre
        $this->actingAs($enc)->get("/activities/{$a->id}")->assertOk()
            ->assertSee('Técnica te encomendó esta actividad')->assertSee('marcada por Técnica')->assertDontSee('Juanita Pérez');
        $this->actingAs($enc)->get('/activities')->assertSee('Técnica te encomendó esta actividad')->assertDontSee('Juanita Pérez');

        // El gerente sí ve el nombre (y el área)
        $this->actingAs($juanita)->get("/activities/{$a->id}")->assertSee('Juanita Pérez (Técnica)');

        // Los encargados siguen sin ver las actividades de otros
        $this->actingAs($otro)->get("/activities/{$a->id}")->assertForbidden();

        // El área es obligatoria para un gerente
        $admin = User::factory()->role('admin')->create();
        $this->actingAs($admin)->post('/users', ['name' => 'Lalo', 'email' => 'lalo@demo.test', 'role' => 'gerente', 'password' => 'secreto123', 'password_confirmation' => 'secreto123', 'active' => '1'])
            ->assertSessionHasErrors('area');
        $this->actingAs($admin)->post('/users', ['name' => 'Lalo', 'email' => 'lalo@demo.test', 'role' => 'gerente', 'area' => 'Territorial', 'password' => 'secreto123', 'password_confirmation' => 'secreto123', 'active' => '1'])
            ->assertSessionHasNoErrors();
        $this->actingAs($admin)->get('/users')->assertSee('Territorial')->assertSee('ID'.$enc->folio_number);
    }
}
