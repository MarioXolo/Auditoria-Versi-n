<?php

namespace Tests\Feature;

use App\Models\AccessLog;
use App\Models\AccessProfile;
use App\Models\Campaign;
use App\Models\CampaignPromoter;
use App\Models\Community;
use App\Models\Minute;
use App\Models\StrategyCatalogItem;
use App\Models\StrategyRecord;
use App\Models\User;
use App\Services\StrategyBooks;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Puntos 12 a 18: cada uno es una burbuja del Cuaderno con sus libros y todos sus campos. */
class Points12To18Test extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private Campaign $campaign;
    private Community $huimilpan;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::factory()->role('admin')->create();
        $this->actingAs($this->admin)->post('/campanas', [
            'name' => 'Huimilpan 2027', 'municipality' => 'Huimilpan', 'listado_nominal' => 30000,
            'participacion' => 55, 'objetivo' => 50, 'colchon' => 10, 'votantes_por_promotor' => 10,
        ]);
        $this->campaign = Campaign::first();
        $this->huimilpan = Community::create(['name' => 'Huimilpan', 'active' => true]);
    }

    private function url(string $path = ''): string
    {
        return "/campanas/{$this->campaign->id}{$path}";
    }

    private function common(array $extra = []): array
    {
        return array_merge(['community_id' => $this->huimilpan->id, 'responsable_id' => $this->admin->id, 'estatus' => 'Pendiente'], $extra);
    }

    private function casilla(string $sec = '0512', string $cas = 'B1'): int
    {
        $this->actingAs($this->admin)->post($this->url('/defensa-del-voto'), ['seccion' => $sec, 'casilla' => $cas])->assertSessionHasNoErrors();

        return $this->campaign->pollingStations()->where('seccion', $sec)->where('casilla', $cas)->value('id');
    }

    public function test_cover_has_bubbles_11_to_20_and_every_book_opens(): void
    {
        $cover = $this->actingAs($this->admin)->get($this->url())->assertOk()->assertSee('Puntos nuevos 11 a 20');
        foreach (StrategyBooks::PUNTOS as $n => [$name]) {
            $cover->assertSee($n.'. '.$name);
            $hub = $this->actingAs($this->admin)->get($n === 11 ? $this->url('/estrategia') : $this->url("/punto/{$n}"))->assertOk();
            foreach (StrategyBooks::ofPunto($n) as $key => $book) {
                $hub->assertSee($book['name']);
                $this->actingAs($this->admin)->get($this->url("/estrategia/{$key}"))->assertOk();
                $this->actingAs($this->admin)->get($this->url("/estrategia/{$key}/nuevo"))->assertOk();
            }
        }
        $cover->assertSee('19. Organigrama')->assertSee('20. Agenda IEEQ');
        $this->actingAs($this->admin)->get($this->url('/punto/19'))->assertNotFound();
    }

    public function test_dia_d_uses_the_casillas_list_and_estampida_board_uses_latest_cut(): void
    {
        $b1 = $this->casilla('0512', 'B1');
        $c1 = $this->casilla('0512', 'C1');
        $promo = CampaignPromoter::create(['campaign_id' => $this->campaign->id, 'name' => 'Lupita', 'meta_votos' => 10]);

        $this->actingAs($this->admin)->post($this->url('/estrategia/dd_plan'), $this->common(['d' => ['casilla' => 99999, 'meta_votos' => 200]]))->assertSessionHasErrors('d.casilla');
        $this->actingAs($this->admin)->post($this->url('/estrategia/dd_plan'), $this->common(['d' => ['casilla' => $b1, 'meta_votos' => 200, 'votantes_asignados' => 180, 'promotor' => $promo->id]]))->assertSessionHasNoErrors();
        $this->actingAs($this->admin)->get($this->url('/estrategia/dd_plan'))->assertSee('0512 B1')->assertSee('Lupita');

        // Sala de situación: la hora es obligatoria
        $this->actingAs($this->admin)->post($this->url('/estrategia/dd_sala'), $this->common(['fecha' => '2027-06-06', 'd' => ['casilla' => $b1, 'tipo' => 'Incidencia']]))->assertSessionHasErrors('hora');

        // Estampida: el tablero toma el último corte de cada casilla (no la suma de todos)
        foreach ([['09:00', $b1, 10, 90], ['11:00', $b1, 40, 60], ['11:00', $c1, 5, 45]] as [$h, $cas, $ya, $faltan]) {
            $this->actingAs($this->admin)->post($this->url('/estrategia/dd_estampida'), $this->common(['fecha' => '2027-06-06', 'hora' => $h, 'd' => ['casilla' => $cas, 'promotor' => $promo->id, 'ya_votaron' => $ya, 'faltan' => $faltan]]))->assertSessionHasNoErrors();
        }
        $this->actingAs($this->admin)->get($this->url('/estrategia/dd_estampida'))->assertSee('Faltan (último corte)')->assertSee('105')->assertSee('45');

        // Encuesta de salida: resultado estimado
        $this->actingAs($this->admin)->post($this->url('/estrategia/dd_encuesta'), $this->common(['fecha' => '2027-06-06', 'hora' => '12:00', 'd' => ['casilla' => $b1, 'encuestador' => 'Ana', 'entrevistas' => 40, 'a_favor' => 22]]))->assertSessionHasNoErrors();
        $this->actingAs($this->admin)->get($this->url('/estrategia/dd_encuesta'))->assertSee('55.0 %');

        // Conteo propio: sin meta en el acta se toma la del plan
        $this->actingAs($this->admin)->post($this->url('/estrategia/dd_conteo'), $this->common(['d' => ['casilla' => $b1, 'votos_nuestros' => 150, 'votos_total' => 320]]))->assertSessionHasNoErrors();
        $this->actingAs($this->admin)->get($this->url('/estrategia/dd_conteo'))->assertSee('75.0 %')->assertSee('150 de 200');
    }

    public function test_apoyo_is_restricted_and_its_events_go_to_the_agenda(): void
    {
        $gerente = User::factory()->role('gerente')->create();
        $this->actingAs($gerente)->get($this->url('/punto/13'))->assertForbidden();
        $this->actingAs($gerente)->get($this->url())->assertDontSee('13. Campaña de Apoyo')->assertSee('12. Día D');
        $this->actingAs($gerente)->get($this->url('/estrategia/apoyo_agenda'))->assertForbidden();

        $equipo = User::factory()->role('encargado')->create(['access_profile_id' => AccessProfile::firstWhere('name', 'Equipo de campaña de apoyo')->id]);
        $this->actingAs($equipo)->post($this->url('/estrategia/apoyo_agenda'), $this->common([
            'responsable_id' => $equipo->id, 'fecha' => '2027-05-20', 'hora' => '10:00', 'costo_estimado' => 8000,
            'd' => ['tipo' => 'Visita', 'lugar' => 'Escuela primaria', 'publico' => 'Madres de familia'],
        ]))->assertSessionHasNoErrors();
        $e = StrategyRecord::firstOrFail();
        $this->assertTrue($e->es_evento);
        $this->actingAs($this->admin)->get($this->url('/agenda-ieeq'))->assertSee('Escuela primaria');
        $this->actingAs($equipo)->get($this->url('/punto/13'))->assertOk()->assertSee('$8,000');
    }

    public function test_cuartos_catalog_grows_bitacora_escalates_and_meetings_create_minutes(): void
    {
        // Catálogo inicial y «Otro…» que agrega uno nuevo
        $this->assertTrue(StrategyCatalogItem::where('catalog', 'cuartos')->where('name', 'Jurídico')->exists());
        $this->actingAs($this->admin)->post($this->url('/estrategia/cuartos'), $this->common([
            'community_id' => null, 'd' => ['cuarto' => '__otro', 'sede' => 'Casa de campaña'], 'nuevo' => ['cuarto' => 'Territorial zona 2'],
        ]))->assertSessionHasNoErrors();
        $this->assertTrue(StrategyCatalogItem::where('catalog', 'cuartos')->where('name', 'Territorial zona 2')->exists());
        $this->actingAs($this->admin)->post($this->url('/estrategia/cuartos'), $this->common(['d' => ['cuarto' => 'Inventado']]))->assertSessionHasErrors('d.cuarto');

        // Bitácora → subir al central
        $this->actingAs($this->admin)->post($this->url('/estrategia/bitacora'), $this->common(['community_id' => null, 'd' => ['cuarto' => 'Digital', 'tema' => 'Rumor en redes']]))->assertSessionHasNoErrors();
        $b = StrategyRecord::where('libro', 'bitacora')->firstOrFail();
        $this->actingAs($this->admin)->get($this->url('/estrategia/bitacora'))->assertSee('Subir al central');
        $this->actingAs($this->admin)->post($this->url("/estrategia/bitacora/{$b->id}/subir"))->assertRedirect();
        $b->refresh();
        $this->assertSame('Central', $b->dato('cuarto'));
        $this->assertSame('Digital', $b->dato('cuarto_origen'));
        $this->assertTrue($b->dato('escalado'));
        $this->actingAs($this->admin)->get($this->url('/estrategia/bitacora'))->assertSee('En el central (de Digital)');

        // Reunión → minuta
        $this->actingAs($this->admin)->post($this->url('/estrategia/cuarto_reuniones'), $this->common([
            'community_id' => null, 'fecha' => '2027-04-10', 'hora' => '18:00', 'd' => ['cuarto' => 'Jurídico', 'tema' => 'Revisión de quejas', 'lugar' => 'Oficina'],
        ]))->assertSessionHasNoErrors();
        $minute = Minute::firstOrFail();
        $this->assertSame('Cuarto Jurídico: Revisión de quejas', $minute->titulo);
        $this->assertSame($minute->id, StrategyRecord::where('libro', 'cuarto_reuniones')->first()->dato('minuta'));
        $this->actingAs($this->admin)->get($this->url('/estrategia/cuarto_reuniones'))->assertSee('Ver minuta');
    }

    public function test_messages_need_approval_and_only_approved_ones_are_offered(): void
    {
        $territorial = User::factory()->role('encargado')->create(['access_profile_id' => AccessProfile::firstWhere('name', 'Coordinación territorial')->id]);
        $profile = AccessProfile::firstWhere('name', 'Coordinación territorial');
        $profile->update(['permisos' => array_merge($profile->permisos, ['mensajes' => ['ver', 'capturar']])]);

        $msg = fn (string $status, User $u) => $this->actingAs($u)->post($this->url('/estrategia/mensajes'), $this->common([
            'community_id' => null, 'responsable_id' => $u->id, 'd' => ['texto' => "Agua para todos ({$status})", 'campana' => 'Candidato', 'estatus_mensaje' => $status],
        ]));
        $msg('Aprobado', $territorial)->assertSessionHasErrors('d.estatus_mensaje');   // no puede aprobar
        $msg('Borrador', $territorial)->assertSessionHasNoErrors();
        $msg('Aprobado', $this->admin)->assertSessionHasNoErrors();

        $aprobado = StrategyRecord::where('libro', 'mensajes')->get()->first(fn ($m) => $m->dato('estatus_mensaje') === 'Aprobado');
        $borrador = StrategyRecord::where('libro', 'mensajes')->get()->first(fn ($m) => $m->dato('estatus_mensaje') === 'Borrador');
        $this->assertSame($this->admin->name, $aprobado->dato('aprobado_por'));

        // En bardas solo se ofrecen los aprobados
        $this->actingAs($this->admin)->get($this->url('/estrategia/bardas/nuevo'))->assertSee('Agua para todos (Aprobado)')->assertDontSee('Agua para todos (Borrador)');
        $this->actingAs($this->admin)->post($this->url('/estrategia/bardas'), $this->common(['d' => ['direccion' => 'Calle 1', 'mensaje' => $borrador->id]]))->assertSessionHasErrors('d.mensaje');
        $this->actingAs($this->admin)->post($this->url('/estrategia/bardas'), $this->common(['d' => ['direccion' => 'Calle 1', 'mensaje' => $aprobado->id]]))->assertSessionHasNoErrors();

        // Propuestas y registro de qué se dijo dónde
        $this->actingAs($this->admin)->post($this->url('/estrategia/propuestas'), $this->common([
            'community_id' => null, 'costo_estimado' => 2500000,
            'd' => ['propuesta' => 'Pozo en San Pedrito', 'pilar' => '__otro', 'comunidades' => [$this->huimilpan->id], 'fuente' => 'Federal', 'orden_gobierno' => 'Concurrente'],
            'nuevo' => ['pilar' => 'Agua'],
        ]))->assertSessionHasNoErrors();
        $prop = StrategyRecord::where('libro', 'propuestas')->firstOrFail();
        $this->actingAs($this->admin)->get($this->url('/estrategia/propuestas'))->assertSee('Pozo en San Pedrito')->assertSee('Agua');
        $this->actingAs($this->admin)->post($this->url('/estrategia/propuesta_dicha'), $this->common(['d' => ['propuesta' => $prop->id, 'contexto' => 'Mitin']]))->assertSessionHasNoErrors();
        $this->actingAs($this->admin)->get($this->url('/estrategia/propuesta_dicha'))->assertSee('Pozo en San Pedrito');
    }

    public function test_statistics_are_restricted_logged_and_bases_are_locked(): void
    {
        $gerente = User::factory()->role('gerente')->create();
        $this->actingAs($gerente)->get($this->url('/punto/16'))->assertForbidden();

        $analisis = User::factory()->role('encargado')->create(['access_profile_id' => AccessProfile::firstWhere('name', 'Análisis')->id]);
        $this->actingAs($analisis)->get($this->url('/punto/16'))->assertOk()->assertSee('Base de datos Nica')->assertSee('Bloqueada para carga');
        $this->actingAs($analisis)->post($this->url('/estrategia/historicos'), $this->common([
            'community_id' => null, 'responsable_id' => $analisis->id, 'd' => ['anio' => '2024', 'listado_nominal' => 1000, 'votacion_total' => 600, 'votos_nuestros' => 330],
        ]))->assertSessionHasNoErrors();
        $this->actingAs($analisis)->get($this->url('/estrategia/historicos'))->assertSee('60.0 %')->assertSee('55.0 %');
        $this->actingAs($analisis)->get($this->url('/estrategia/historicos/exportar'))->assertOk();

        $this->assertTrue(AccessLog::where('module', 'estadisticas')->where('action', 'exportación')->where('user_id', $analisis->id)->exists());
        $this->assertTrue(AccessLog::where('module', 'estadisticas')->where('action', 'consulta')->exists());
        $this->actingAs($analisis)->get($this->url('/punto/16'))->assertSee('Registro de consultas')->assertSee($analisis->name);
    }

    public function test_cierre_deadline_and_sectores_with_patron_saint_party_in_calendar(): void
    {
        $this->actingAs($this->admin)->post($this->url('/estrategia/cierres'), $this->common(['fecha' => '2027-06-03', 'd' => ['tipo' => 'Cierre principal', 'sede' => 'Jardín']]))->assertSessionHasErrors('fecha');
        $this->actingAs($this->admin)->post($this->url('/estrategia/cierres'), $this->common(['fecha' => '2027-06-02', 'hora' => '19:00', 'd' => ['tipo' => 'Cierre principal', 'sede' => 'Jardín', 'aforo' => 3000, 'plan_b' => 'Auditorio']]))->assertSessionHasNoErrors();
        $this->actingAs($this->admin)->get($this->url('/agenda-ieeq'))->assertSee('Jardín');

        // Sectores: catálogo con taxistas, iglesias y mayordomos; la fiesta aparece en el calendario
        foreach (['Taxistas', 'Iglesias', 'Mayordomos'] as $s) {
            $this->assertTrue(StrategyCatalogItem::where('catalog', 'sectores')->where('name', $s)->exists());
        }
        $this->actingAs($this->admin)->post($this->url('/estrategia/sectores'), $this->common([
            'd' => ['sector' => 'Mayordomos', 'organizacion' => 'Mayordomía de San Miguel', 'lider' => 'Don Pedro', 'integrantes' => 25, 'votos_influye' => 300, 'postura' => 'Afín', 'fiesta' => 'San Miguel Arcángel', 'fecha_fiesta' => '2026-09-29'],
        ]))->assertSessionHasNoErrors();
        $this->actingAs($this->admin)->get('/calendario?mes=2027-09')->assertSee('San Miguel Arcángel');
        $this->actingAs($this->admin)->get('/calendario?mes=2027-08')->assertDontSee('San Miguel Arcángel');

        $this->actingAs($this->admin)->post($this->url('/estrategia/sector_eventos'), $this->common(['fecha' => '2027-05-01', 'd' => ['sector' => 'Taxistas', 'lugar' => 'Sitio de taxis centro']]))->assertSessionHasNoErrors();
        $this->actingAs($this->admin)->get($this->url('/agenda-ieeq'))->assertSee('Sitio de taxis centro');
    }
}
