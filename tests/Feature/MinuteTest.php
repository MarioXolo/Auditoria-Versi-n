<?php

namespace Tests\Feature;

use App\Models\Minute;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class MinuteTest extends TestCase
{
    use RefreshDatabase;

    private function form(array $over = []): array
    {
        return array_replace_recursive([
            'titulo' => '',
            'tema' => 'Sesión estratégica',
            'objetivo' => 'Definir responsables',
            'fecha' => '2026-11-10',
            'hora_inicio' => '16:00',
            'hora_fin' => '17:30',
            'lugar' => 'Sala de juntas',
            'participantes' => [
                ['nombre' => 'Lic. Karla', 'puesto' => 'Enlace', 'asistio' => '1', 'modalidad' => 'Presencial'],
                ['nombre' => '', 'puesto' => 'renglón vacío'],
            ],
            'puntos' => ['Revisar el calendario', ''],
            'acuerdos' => ['Se acordó publicar el video'],
            'compromisos' => [
                ['actividad' => 'Presupuesto de redes', 'responsable' => 'Mtro. Juan', 'fecha_limite' => '19/11/2026', 'estatus' => 'En proceso'],
                ['actividad' => 'Identidad visual', 'responsable' => 'Beto', 'fecha_limite' => 'Primera quincena de diciembre', 'estatus' => ''],
            ],
        ], $over);
    }

    public function test_only_admins_and_managers_see_minutes(): void
    {
        foreach (['admin', 'gerente'] as $role) {
            $u = User::factory()->role($role)->create();
            $this->actingAs($u)->get('/minutas')->assertOk();
            $this->actingAs($u)->get('/activities')->assertSee('Minutas');
        }
        foreach (['encargado', 'empleado'] as $role) {
            $u = User::factory()->role($role)->create();
            $this->actingAs($u)->get('/minutas')->assertForbidden();
            $this->actingAs($u)->get('/minutas/create')->assertForbidden();
            $this->actingAs($u)->post('/minutas', $this->form())->assertForbidden();
            $this->actingAs($u)->get('/activities')->assertDontSee('Minutas');
        }
    }

    public function test_create_your_minute_keeps_every_excel_section(): void
    {
        $gerente = User::factory()->role('gerente')->create();
        $this->actingAs($gerente)->get('/minutas/create')->assertOk()
            ->assertSeeInOrder(['1. Información general', '2. Control de participantes', '3. Puntos tratados', '4. Acuerdos y decisiones', '5. Compromisos']);

        $this->actingAs($gerente)->post('/minutas', $this->form())->assertSessionHasNoErrors();
        $m = Minute::first();

        $this->assertSame('2026-11-10', $m->fecha->toDateString());
        $this->assertSame('MINUTA REUNIÓN MARTES 10/11/2026', $m->titulo);
        $this->assertSame('16:00', Minute::hhmm($m->hora_inicio));
        $this->assertSame(['Lic. Karla'], $m->participants->pluck('nombre')->all());
        $this->assertTrue($m->participants->first()->asistio);
        $this->assertSame(['Revisar el calendario'], $m->puntos->pluck('texto')->all());
        $this->assertSame(['2026-11-19', 'Primera quincena de diciembre'], $m->commitments->pluck('fecha_limite')->all());

        $this->actingAs($gerente)->get("/minutas/{$m->id}")->assertOk()
            ->assertSee('MINUTA REUNIÓN MARTES 10/11/2026')
            ->assertSee('10/11/2026')->assertSee('19/11/2026')->assertSee('Primera quincena de diciembre')
            ->assertSee('Se acordó publicar el video');

        // La fecha es obligatoria
        $this->actingAs($gerente)->post('/minutas', $this->form(['fecha' => '']))->assertSessionHasErrors('fecha');
    }

    public function test_editing_replaces_rows_and_keeps_the_date(): void
    {
        $admin = User::factory()->role('admin')->create();
        $this->actingAs($admin)->post('/minutas', $this->form());
        $m = Minute::first();

        $this->actingAs($admin)->get("/minutas/{$m->id}/edit")->assertOk()->assertSee('value="19/11/2026"', false);
        $this->actingAs($admin)->put("/minutas/{$m->id}", $this->form([
            'titulo' => 'Minuta especial', 'acuerdos' => ['Uno', 'Dos'], 'compromisos' => [1 => ['actividad' => '']],
        ]))->assertRedirect("/minutas/{$m->id}");

        $m->refresh();
        $this->assertSame('Minuta especial', $m->titulo);
        $this->assertSame('2026-11-10', $m->fecha->toDateString());
        $this->assertSame(['Uno', 'Dos'], $m->acuerdos->pluck('texto')->all());
        $this->assertCount(1, $m->commitments);

        $this->actingAs($admin)->delete("/minutas/{$m->id}")->assertRedirect('/minutas');
        $this->assertSame(0, Minute::count());
    }

    public function test_excel_import_reads_the_team_format_once(): void
    {
        $gerente = User::factory()->role('gerente')->create();
        $file = fn () => new UploadedFile(base_path('tests/fixtures/minuta-ejemplo.xlsx'), 'minuta-ejemplo.xlsx', null, null, true);

        $this->actingAs($gerente)->post('/minutas/importar', ['archivos' => [$file()]])
            ->assertRedirect('/minutas')->assertSessionHas('status', 'Se cargó 1 minuta.');

        $m = Minute::first();
        $this->assertSame('2027-01-05', $m->fecha->toDateString());
        $this->assertSame('MINUTA REUNIÓN MARTES 05/01/2027', $m->titulo);
        $this->assertSame(['17:00', '19:30'], [Minute::hhmm($m->hora_inicio), Minute::hhmm($m->hora_fin)]);
        $this->assertSame('Sala de pruebas', $m->lugar);
        $this->assertSame([true, false], $m->participants->pluck('asistio')->all());
        // Un punto que dice «información» no se confunde con el encabezado de la sección 1
        $this->assertSame(['Revisar la información de apoyos por comunidad', 'Fecha con la totalidad de la Información'], $m->puntos->pluck('texto')->all());
        $this->assertSame(['Se acordó entregar el reporte'], $m->acuerdos->pluck('texto')->all());
        // La fila «3. Pendiente» vacía se descarta; la fecha límite en texto se conserva
        $this->assertSame([['2027-01-12', 'En proceso'], ['Primera quincena de enero', null]],
            $m->commitments->map(fn ($c) => [$c->fecha_limite, $c->estatus])->all());
        $this->assertSame('minuta-ejemplo.xlsx', $m->archivo_origen);

        // Subirla otra vez no la duplica
        $this->actingAs($gerente)->post('/minutas/importar', ['archivos' => [$file()]]);
        $this->assertSame(1, Minute::count());

        // Un archivo que no es Excel se rechaza
        $this->actingAs($gerente)->post('/minutas/importar', ['archivos' => [UploadedFile::fake()->create('notas.pdf', 10)]])
            ->assertSessionHasErrors('archivos.0');

        $this->actingAs($gerente)->get('/minutas?q=reporte')->assertSee('Reunión de prueba');
        $this->actingAs($gerente)->get('/minutas?q=inexistente')->assertSee('Ninguna minuta coincide');
    }
}
