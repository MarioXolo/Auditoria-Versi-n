<?php

namespace Tests\Feature;

use App\Models\Activity;
use App\Models\ActivityType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\MakesActivities;
use Tests\TestCase;

/** Lo que alguien escribe con «Otro…» en el tipo de actividad queda privado: no les aparece a los demás. */
class PrivateActivityTypesTest extends TestCase
{
    use MakesActivities;
    use RefreshDatabase;

    public function test_typed_types_are_private_to_their_author(): void
    {
        $ana = User::factory()->role('encargado')->create();
        $beto = User::factory()->role('encargado')->create();
        $gerente = User::factory()->role('gerente')->create();
        $admin = User::factory()->role('admin')->create();

        $this->actingAs($ana)->post('/activities', $this->payload(['activity_type_id' => 'otro', 'activity_type_new' => 'Platica con Jacinto']))->assertRedirect()->assertSessionHasNoErrors();
        $type = ActivityType::firstWhere('name', 'Platica con Jacinto');
        $this->assertSame($ana->id, $type->owner_id);

        // A Ana sí le aparece para su siguiente actividad; a Beto no, ni puede usarlo
        $this->actingAs($ana)->get('/activities/create')->assertSee('Platica con Jacinto');
        $this->actingAs($beto)->get('/activities/create')->assertDontSee('Platica con Jacinto');
        $this->actingAs($beto)->get('/activities')->assertDontSee('Platica con Jacinto');
        $this->actingAs($beto)->get('/calendario')->assertDontSee('Platica con Jacinto');
        $this->actingAs($beto)->post('/activities', $this->payload(['activity_type_id' => $type->id]))->assertSessionHasErrors('activity_type_id');

        // Si Beto escribe el mismo nombre, se le crea su propio tipo privado (no ve el de Ana)
        $this->actingAs($beto)->post('/activities', $this->payload(['activity_type_id' => 'otro', 'activity_type_new' => 'Platica con Jacinto']))->assertSessionHasNoErrors();
        $this->assertSame(2, ActivityType::where('name', 'Platica con Jacinto')->count());

        // Una lista común con ese nombre se reutiliza en vez de crear otro privado
        $comun = ActivityType::create(['name' => 'Reunión vecinal', 'active' => true]);
        $this->actingAs($beto)->post('/activities', $this->payload(['activity_type_id' => 'otro', 'activity_type_new' => 'reunión vecinal']))->assertSessionHasNoErrors();
        $this->assertSame($comun->id, Activity::latest('id')->first()->activity_type_id);

        // El gerente ve el tipo en la actividad de Ana (la supervisa), pero no en su lista para cargar
        $this->actingAs($gerente)->get('/activities')->assertSee('Platica con Jacinto');
        $this->actingAs($gerente)->get('/activities/create')->assertDontSee('Platica con Jacinto');

        // Catálogos: la lista común no lo muestra; el administrador lo ve aparte y lo puede compartir
        $this->actingAs($gerente)->get('/catalogos')->assertDontSee('Platica con Jacinto');
        $this->actingAs($admin)->get('/catalogos')->assertSee('Tipos de actividad privados')->assertSee('Platica con Jacinto');
        $this->actingAs($gerente)->patch("/catalogos/tipos/{$type->id}/compartir")->assertForbidden();
        $this->actingAs($admin)->patch("/catalogos/tipos/{$type->id}/compartir")->assertRedirect();
        $this->assertNull($type->fresh()->owner_id);
        $this->actingAs($beto)->get('/activities/create')->assertSee('Platica con Jacinto');
    }
}
