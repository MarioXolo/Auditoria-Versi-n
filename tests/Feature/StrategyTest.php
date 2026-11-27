<?php

namespace Tests\Feature;

use App\Models\Campaign;
use App\Models\Community;
use App\Models\Section;
use App\Models\StrategyRecord;
use App\Models\User;
use App\Services\StrategyBooks;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/** Puntos nuevos · fase 1: secciones, libros de Estrategia, casillas por tipo, Agenda IEEQ y calendario. */
class StrategyTest extends TestCase
{
    use RefreshDatabase;

    private User $gerente;
    private Campaign $campaign;
    private Community $huimilpan;
    private Community $sanPedrito;

    protected function setUp(): void
    {
        parent::setUp();
        $this->gerente = User::factory()->role('gerente')->create();
        $this->actingAs($this->gerente)->post('/campanas', [
            'name' => 'Huimilpan 2027', 'municipality' => 'Huimilpan', 'listado_nominal' => 30000,
            'participacion' => 55, 'objetivo' => 50, 'colchon' => 10, 'votantes_por_promotor' => 10,
        ]);
        $this->campaign = Campaign::first();
        $this->huimilpan = Community::create(['name' => 'Huimilpan', 'active' => true]);
        $this->sanPedrito = Community::create(['name' => 'San Pedrito', 'active' => true]);
    }

    private function url(string $path = ''): string
    {
        return "/campanas/{$this->campaign->id}{$path}";
    }

    private function common(array $extra = []): array
    {
        return array_merge([
            'community_id' => $this->huimilpan->id,
            'responsable_id' => $this->gerente->id,
            'estatus' => 'Pendiente',
        ], $extra);
    }

    public function test_hub_shows_every_book_and_casillas_only_for_managers(): void
    {
        $page = $this->actingAs($this->gerente)->get($this->url('/estrategia'))->assertOk();
        foreach (StrategyBooks::ofPunto(11) as $key => $book) {
            $page->assertSee($book['name']);
            $this->actingAs($this->gerente)->get($this->url("/estrategia/{$key}"))->assertOk();
            $this->actingAs($this->gerente)->get($this->url("/estrategia/{$key}/nuevo"))->assertOk();
        }
        $page->assertSee('Casillas especiales')->assertSee('Casillas nuevas');
        $this->actingAs($this->gerente)->get($this->url())->assertSee('Estrategia de Campaña')->assertSee('Agenda IEEQ');

        $encargado = User::factory()->role('encargado')->create();
        $this->actingAs($encargado)->get($this->url('/estrategia'))->assertForbidden();
        $this->actingAs($encargado)->get($this->url('/agenda-ieeq'))->assertForbidden();
        $this->actingAs($this->gerente)->get($this->url('/estrategia/no-existe'))->assertNotFound();
    }

    public function test_section_catalog(): void
    {
        $this->actingAs($this->gerente)->post('/catalogos/secciones', ['clave' => '0512', 'community_id' => $this->sanPedrito->id])->assertRedirect();
        $this->actingAs($this->gerente)->post('/catalogos/secciones', ['clave' => '0512'])->assertSessionHasErrors('clave', null, 'secciones');
        $this->actingAs($this->gerente)->post('/catalogos/secciones', ['clave' => 'doce'])->assertSessionHasErrors('clave', null, 'secciones');
        $this->actingAs($this->gerente)->get('/catalogos')->assertSee('Secciones electorales')->assertSee('0512');
        $s = Section::firstWhere('clave', '0512');
        $this->assertSame($this->sanPedrito->id, $s->community_id);
        $this->assertSame('0512 · San Pedrito', $s->label());
    }

    public function test_event_goes_to_ieeq_agenda_with_deadline_and_changes_mark_it_modified(): void
    {
        $this->actingAs($this->gerente)->post($this->url('/estrategia/mitines'), $this->common([
            'fecha' => '2027-05-10', 'hora' => '17:30', 'costo_estimado' => 15000,
            'd' => ['lugar' => 'Jardín principal', 'aforo_esperado' => 800, 'oradores' => 'Jairo Morales', 'permisos' => 'En trámite'],
        ]))->assertRedirect()->assertSessionHasNoErrors();

        $m = StrategyRecord::firstOrFail();
        $this->assertTrue($m->es_evento);
        $this->assertSame('Por reportar', $m->ieeq_estatus);
        $this->assertSame('2027-05-03', $m->ieeq_fecha_limite->toDateString());
        $this->assertSame('Jardín principal', $m->title());

        $this->actingAs($this->gerente)->get($this->url('/agenda-ieeq'))->assertOk()->assertSee('Jardín principal')->assertSee('03/05/2027');
        $this->actingAs($this->gerente)->get($this->url('/estrategia/mitines'))->assertSee('Jardín principal')->assertSee('800');

        // Reportarlo exige la fecha en que se reportó
        $this->actingAs($this->gerente)->put($this->url("/agenda-ieeq/{$m->id}"), ['ieeq_estatus' => 'Reportado'])->assertSessionHasErrors('ieeq_reportado_el');
        $this->actingAs($this->gerente)->put($this->url("/agenda-ieeq/{$m->id}"), ['ieeq_estatus' => 'Reportado', 'ieeq_reportado_el' => '2027-05-01', 'ieeq_folio' => 'IEEQ-123'])->assertRedirect();
        $this->assertSame('Reportado', $m->fresh()->ieeq_estatus);

        // Cambia el lugar de un evento ya reportado → «Modificado» y nueva fecha límite al cambiar la fecha
        $this->actingAs($this->gerente)->put($this->url("/estrategia/mitines/{$m->id}"), $this->common([
            'fecha' => '2027-05-12', 'hora' => '17:30',
            'd' => ['lugar' => 'Auditorio municipal', 'permisos' => 'Obtenido'],
        ]))->assertRedirect()->assertSessionHasNoErrors();
        $m->refresh();
        $this->assertSame('Modificado', $m->ieeq_estatus);
        $this->assertSame('2027-05-05', $m->ieeq_fecha_limite->toDateString());
        $this->assertSame('IEEQ-123', $m->ieeq_folio);
    }

    public function test_government_acts_are_not_reported_and_overdue_events_show_red(): void
    {
        $this->actingAs($this->gerente)->post($this->url('/estrategia/metropolitana'), $this->common([
            'fecha' => '2027-03-01', 'd' => ['reunion' => 'Plan de agua con los 4 presidentes', 'tipo_acto' => 'Acto de gobierno'],
        ]))->assertSessionHasNoErrors();
        $this->assertFalse(StrategyRecord::firstOrFail()->es_evento);

        $this->actingAs($this->gerente)->post($this->url('/estrategia/desayunos'), $this->common([
            'fecha' => today()->addDays(3)->toDateString(), 'd' => ['sede' => 'Salón ejidal', 'asistentes' => 40],
        ]))->assertSessionHasNoErrors();
        $d = StrategyRecord::where('libro', 'desayunos')->firstOrFail();
        $this->assertSame('vencido', $d->agendaSemaforo());   // límite: 4 días antes de hoy
        $this->actingAs($this->gerente)->get($this->url('/agenda-ieeq'))->assertSee('Vencido')->assertDontSee('Plan de agua');
        $this->actingAs($this->gerente)->get($this->url('/estrategia'))->assertSee('falta de reportar al IEEQ');
    }

    public function test_required_fields_and_section_must_belong_to_the_community(): void
    {
        $this->actingAs($this->gerente)->post($this->url('/estrategia/mitines'), ['estatus' => 'Pendiente'])
            ->assertSessionHasErrors(['community_id', 'responsable_id', 'fecha', 'd.lugar']);

        $sec = Section::create(['clave' => '0600', 'community_id' => $this->sanPedrito->id, 'active' => true]);
        $this->actingAs($this->gerente)->post($this->url('/estrategia/puerta'), $this->common([
            'section_id' => $sec->id, 'd' => ['ruta' => 'Calle Hidalgo'],
        ]))->assertSessionHasErrors('section_id');

        $this->actingAs($this->gerente)->post($this->url('/estrategia/puerta'), $this->common([
            'community_id' => $this->sanPedrito->id, 'section_id' => $sec->id,
            'd' => ['ruta' => 'Calle Hidalgo', 'viviendas' => 60, 'contactadas' => 45, 'votos_identificados' => 20],
        ]))->assertSessionHasNoErrors();
        $this->actingAs($this->gerente)->get($this->url('/estrategia/puerta?section_id='.$sec->id))->assertSee('Calle Hidalgo')->assertSee('0600');

        // Prioridades: libros que aplican (multiselección) y colores por clasificación
        $this->actingAs($this->gerente)->post($this->url('/estrategia/prioridades'), $this->common([
            'd' => ['prioridad' => '1', 'clasificacion' => 'Bisagra', 'problema' => 'Falta de agua', 'libros' => ['bardas', 'puerta']],
        ]))->assertSessionHasNoErrors();
        $p = StrategyRecord::where('libro', 'prioridades')->first();
        $this->assertSame(['bardas', 'puerta'], $p->dato('libros'));
        $this->actingAs($this->gerente)->post($this->url('/estrategia/prioridades'), $this->common([
            'd' => ['prioridad' => '1', 'clasificacion' => 'Bisagra', 'problema' => 'X', 'libros' => ['inventado']],
        ]))->assertSessionHasErrors('d.libros.0');
    }

    public function test_barda_with_map_files_and_authorization_format(): void
    {
        Storage::fake('local');
        $this->actingAs($this->gerente)->post($this->url('/estrategia/bardas'), $this->common([
            'fecha' => '2027-04-20', 'lat' => 20.3739, 'lng' => -100.2732,
            'd' => ['direccion' => 'Av. Juárez 12', 'propietario' => 'María López', 'medidas' => '8 × 2.5 m'],
            'f' => ['autorizacion' => UploadedFile::fake()->create('firmado.pdf', 120, 'application/pdf'),
                'foto_antes' => UploadedFile::fake()->create('antes.jpg', 200, 'image/jpeg')],
        ]))->assertSessionHasNoErrors();

        $b = StrategyRecord::firstOrFail();
        $this->assertFalse($b->es_evento);
        $this->assertEqualsWithDelta(20.3739, (float) $b->lat, 0.000001);
        $this->assertNotNull($b->archivo('autorizacion'));
        Storage::disk('local')->assertExists($b->archivo('autorizacion'));

        $this->actingAs($this->gerente)->get($this->url("/estrategia/bardas/{$b->id}/archivo/autorizacion"))->assertOk();
        $this->actingAs($this->gerente)->get($this->url("/estrategia/bardas/{$b->id}/archivo/foto_despues"))->assertNotFound();
        $this->actingAs($this->gerente)->get($this->url('/estrategia/bardas'))->assertSee('Av. Juárez 12')->assertSee('✓ Ver');
        $this->actingAs($this->gerente)->get($this->url("/estrategia/bardas/formato/{$b->id}"))->assertOk()->assertSee('María López')->assertSee('8 × 2.5 m');
        $this->actingAs($this->gerente)->get($this->url('/estrategia/bardas/formato'))->assertOk()->assertSee('Autorización');
        $this->actingAs($this->gerente)->get($this->url('/estrategia/mitines/formato'))->assertNotFound();

        // Quitar el archivo lo borra del disco
        $path = $b->archivo('autorizacion');
        $this->actingAs($this->gerente)->put($this->url("/estrategia/bardas/{$b->id}"), $this->common([
            'd' => ['direccion' => 'Av. Juárez 12'], 'quitar' => ['autorizacion' => 1],
        ]))->assertSessionHasNoErrors();
        $this->assertNull($b->fresh()->archivo('autorizacion'));
        Storage::disk('local')->assertMissing($path);

        // Un registro de otra campaña o de otro libro no se alcanza por la URL
        $this->actingAs($this->gerente)->get($this->url("/estrategia/lonas/{$b->id}/editar"))->assertNotFound();

        $this->actingAs($this->gerente)->delete($this->url("/estrategia/bardas/{$b->id}"))->assertRedirect();
        $this->assertSame(0, StrategyRecord::count());
    }

    public function test_casillas_share_one_table_filtered_by_type(): void
    {
        $post = fn (array $d) => $this->actingAs($this->gerente)->post($this->url('/defensa-del-voto'), $d);
        $post(['seccion' => '0512', 'casilla' => 'B1', 'tipo' => 'Normal', 'listado_nominal' => 650, 'votos_2024' => 410])->assertSessionHasNoErrors();
        $post(['seccion' => '0512', 'casilla' => 'S1', 'tipo' => 'Especial', 'domicilio' => 'Escuela Benito Juárez'])->assertSessionHasNoErrors();
        $post(['seccion' => '0600', 'casilla' => 'B1', 'tipo' => 'Nueva'])->assertSessionHasErrors('motivo_nueva');
        $post(['seccion' => '0600', 'casilla' => 'B1', 'tipo' => 'Nueva', 'motivo_nueva' => 'Crecimiento del fraccionamiento'])->assertSessionHasNoErrors();

        $this->actingAs($this->gerente)->get($this->url('/defensa-del-voto?tipo=Especial'))
            ->assertSee('Casillas especiales')->assertSee('Escuela Benito Juárez')->assertDontSee('Crecimiento del fraccionamiento');
        $this->actingAs($this->gerente)->get($this->url('/estrategia'))->assertSee('1 casilla');
        $this->assertSame(3, $this->campaign->pollingStations()->count());
    }

    public function test_calendar_shows_campaign_events_to_managers_only(): void
    {
        $this->actingAs($this->gerente)->post($this->url('/estrategia/cabalgata'), $this->common([
            'fecha' => '2027-05-15', 'd' => ['ruta' => 'Huimilpan – Lagunillas', 'jinetes' => 120],
        ]))->assertSessionHasNoErrors();

        $this->actingAs($this->gerente)->get('/calendario?mes=2027-05')->assertOk()->assertSee('Huimilpan – Lagunillas')->assertSee('Eventos de campaña');
        $encargado = User::factory()->role('encargado')->create();
        $this->actingAs($encargado)->get('/calendario?mes=2027-05')->assertOk()->assertDontSee('Huimilpan – Lagunillas');
    }

    public function test_export_csv(): void
    {
        $this->actingAs($this->gerente)->post($this->url('/estrategia/kits'), $this->common([
            'd' => ['contenido' => 'Gorra, playera y calendario', 'cantidad' => 500, 'costo_unitario' => 85, 'entregados' => 120],
        ]))->assertSessionHasNoErrors();

        $csv = $this->actingAs($this->gerente)->get($this->url('/estrategia/kits/exportar'))->assertOk()->streamedContent();
        $this->assertStringContainsString('Gorra, playera y calendario', $csv);
        $this->assertStringContainsString('Costo unitario', $csv);
        $this->actingAs($this->gerente)->get($this->url('/estrategia/kits'))->assertSee('500')->assertSee('120');
    }
}
