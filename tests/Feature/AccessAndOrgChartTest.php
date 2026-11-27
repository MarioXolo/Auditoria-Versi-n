<?php

namespace Tests\Feature;

use App\Models\AccessProfile;
use App\Models\Campaign;
use App\Models\CampaignPromoter;
use App\Models\CampaignVoter;
use App\Models\OrgMember;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Punto 19: perfiles con permisos, promotor que solo ve a sus votantes y organigrama. */
class AccessAndOrgChartTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private Campaign $campaign;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::factory()->role('admin')->create();
        $this->actingAs($this->admin)->post('/campanas', [
            'name' => 'Huimilpan 2027', 'municipality' => 'Huimilpan', 'listado_nominal' => 30000,
            'participacion' => 55, 'objetivo' => 50, 'colchon' => 10, 'votantes_por_promotor' => 10,
        ]);
        $this->campaign = Campaign::first();
    }

    private function url(string $path = ''): string
    {
        return "/campanas/{$this->campaign->id}{$path}";
    }

    private function withProfile(string $name, string $role = 'encargado'): User
    {
        return User::factory()->role($role)->create(['access_profile_id' => AccessProfile::firstWhere('name', $name)->id]);
    }

    public function test_default_profiles_are_created_and_admin_manages_them(): void
    {
        $this->assertSame(5, AccessProfile::count());
        $this->actingAs($this->admin)->get('/perfiles')->assertOk()->assertSee('Coordinación territorial')->assertSee('Promotor');

        $this->actingAs($this->admin)->post('/perfiles', [
            'name' => 'Comunicación', 'permisos' => ['estrategia' => ['capturar'], 'inventado' => ['ver'], 'agenda' => ['volar']],
        ])->assertRedirect();
        $p = AccessProfile::firstWhere('name', 'Comunicación');
        $this->assertSame(['estrategia' => ['ver', 'capturar']], $p->permisos);   // «ver» va implícito; lo inválido se ignora

        $gerente = User::factory()->role('gerente')->create();
        $this->actingAs($gerente)->get('/perfiles')->assertForbidden();
    }

    public function test_permissions_by_profile_on_modules_and_actions(): void
    {
        // Gerente sin perfil: acceso completo como antes. Encargado sin perfil: nada
        $gerente = User::factory()->role('gerente')->create();
        $this->assertTrue($gerente->puede('votantes', 'exportar'));
        $encargado = User::factory()->role('encargado')->create();
        $this->actingAs($encargado)->get('/campanas')->assertForbidden();
        $this->actingAs($encargado)->get('/minutas')->assertForbidden();

        // Análisis: ve y exporta, no captura
        $analisis = $this->withProfile('Análisis');
        $this->actingAs($analisis)->get($this->url())->assertOk()->assertSee('Estrategia de Campaña')->assertDontSee('Agregar mitin');
        $this->actingAs($analisis)->get($this->url('/estrategia/mitines'))->assertOk()->assertSee('Exportar CSV')->assertDontSee('+ Agregar');
        $this->actingAs($analisis)->get($this->url('/estrategia/mitines/exportar'))->assertOk();
        $this->actingAs($analisis)->get($this->url('/estrategia/mitines/nuevo'))->assertForbidden();
        $this->actingAs($analisis)->post($this->url('/actores'), ['nombre' => 'X'])->assertForbidden();
        $this->actingAs($analisis)->get('/minutas')->assertForbidden();
        $this->actingAs($analisis)->get('/organigrama')->assertOk()->assertDontSee('+ Agregar persona');

        // Equipo de campaña de apoyo: agenda (puede revisar) y estrategia, sin cuaderno ni votantes
        $apoyo = $this->withProfile('Equipo de campaña de apoyo');
        $cover = $this->actingAs($apoyo)->get($this->url())->assertOk();
        $cover->assertSee('Estrategia de Campaña')->assertSee('Agenda IEEQ')->assertDontSee('Base de Votantes')->assertDontSee('Meta de votos');
        $this->actingAs($apoyo)->get($this->url('/votantes'))->assertForbidden();
        $this->actingAs($apoyo)->get($this->url('/actores'))->assertForbidden();
        $this->actingAs($apoyo)->get('/calendario')->assertOk();

        // El administrador siempre puede, aunque tenga un perfil limitado
        $this->admin->update(['access_profile_id' => AccessProfile::firstWhere('name', 'Promotor')->id]);
        $this->assertTrue($this->admin->fresh()->puede('catalogos', 'capturar'));
    }

    public function test_promoter_only_sees_and_captures_their_own_voters(): void
    {
        $promotor = $this->withProfile('Promotor');
        $mio = CampaignPromoter::create(['campaign_id' => $this->campaign->id, 'name' => 'Lupita', 'user_id' => $promotor->id, 'meta_votos' => 10]);
        $otro = CampaignPromoter::create(['campaign_id' => $this->campaign->id, 'name' => 'Beto', 'meta_votos' => 10]);
        $ana = CampaignVoter::create(['campaign_id' => $this->campaign->id, 'nombre' => 'Ana Ruiz', 'promoter_id' => $mio->id, 'telefono' => '4421112233']);
        $pepe = CampaignVoter::create(['campaign_id' => $this->campaign->id, 'nombre' => 'Pepe Torres', 'promoter_id' => $otro->id, 'telefono' => '4429998877', 'seccion' => '0512']);

        // Solo ve la burbuja de votantes y a los suyos
        $this->actingAs($promotor)->get($this->url())->assertOk()->assertSee('Base de Votantes')->assertDontSee('Padrón de Promotores');
        $this->actingAs($promotor)->get($this->url('/votantes'))->assertOk()->assertSee('Ana Ruiz')->assertDontSee('Pepe Torres')->assertSee('Ves y capturas solo a tus votantes');
        $this->actingAs($promotor)->get($this->url("/votantes/{$pepe->id}/edit"))->assertNotFound();
        $this->actingAs($promotor)->delete($this->url("/votantes/{$pepe->id}"))->assertNotFound();
        $this->actingAs($promotor)->get($this->url('/votantes/exportar'))->assertForbidden();   // su perfil no exporta
        $this->actingAs($promotor)->get($this->url('/promotores'))->assertForbidden();

        // Captura solo con su promotor
        $this->actingAs($promotor)->post($this->url('/votantes'), ['nombre' => 'Rosa Díaz', 'promoter_id' => $otro->id])->assertSessionHasErrors('promoter_id');
        $this->actingAs($promotor)->post($this->url('/votantes'), ['nombre' => 'Rosa Díaz', 'promoter_id' => $mio->id])->assertSessionHasNoErrors();

        // Un posible duplicado de otro promotor no le revela el nombre
        $r = $this->actingAs($promotor)->post($this->url('/votantes'), ['nombre' => 'Otra', 'telefono' => '4429998877', 'promoter_id' => $mio->id]);
        $r->assertSessionHasErrors('duplicado');
        $this->assertStringNotContainsString('Pepe', session('errors')->first('duplicado'));

        // Sin promotor ligado: aviso y nada que ver
        $nuevo = $this->withProfile('Promotor');
        $this->actingAs($nuevo)->get($this->url('/votantes'))->assertOk()->assertSee('no está ligada a ningún promotor')->assertDontSee('Ana Ruiz');

        // El gerente sigue viendo a todos
        $this->actingAs(User::factory()->role('gerente')->create())->get($this->url('/votantes'))->assertSee('Ana Ruiz')->assertSee('Pepe Torres');
    }

    public function test_org_chart_draws_from_reports_to_and_flags_public_servants(): void
    {
        $this->actingAs($this->admin)->get('/organigrama')->assertOk()->assertSee('Aún no hay nadie');

        $this->actingAs($this->admin)->post('/organigrama', ['nombre' => 'Persona Candidata', 'puesto' => 'Candidato'])->assertRedirect();
        $top = OrgMember::firstWhere('puesto', 'Candidato');
        $this->actingAs($this->admin)->post('/organigrama', ['nombre' => 'Persona Coordinadora', 'puesto' => 'Coordinación general', 'reporta_a_id' => $top->id, 'user_id' => $this->admin->id])->assertSessionHasNoErrors();
        $coord = OrgMember::firstWhere('puesto', 'Coordinación general');
        $this->actingAs($this->admin)->post('/organigrama', ['nombre' => 'Persona Territorial', 'puesto' => 'Coordinación territorial', 'reporta_a_id' => $coord->id, 'servidor_publico' => '1'])->assertSessionHasNoErrors();
        $terr = OrgMember::firstWhere('puesto', 'Coordinación territorial');

        $page = $this->actingAs($this->admin)->get('/organigrama')->assertOk();
        $page->assertSee('org-tree', false)->assertSee('Hay 1 persona marcada')->assertSee('Servidor público')->assertSee('Administrador');

        // No se pueden hacer ciclos ni ligar la misma cuenta a dos personas
        $this->actingAs($this->admin)->put("/organigrama/{$top->id}", ['nombre' => 'Persona Candidata', 'puesto' => 'Candidato', 'reporta_a_id' => $terr->id])->assertSessionHasErrors('reporta_a_id');
        $this->actingAs($this->admin)->put("/organigrama/{$terr->id}", ['nombre' => 'Persona Territorial', 'puesto' => 'Coordinación territorial', 'user_id' => $this->admin->id])->assertSessionHasErrors('user_id');

        // Al quitar a alguien, sus subordinados suben a su jefe
        $this->actingAs($this->admin)->delete("/organigrama/{$coord->id}")->assertRedirect();
        $this->assertSame($top->id, $terr->fresh()->reporta_a_id);

        // El encargado sin perfil no entra
        $this->actingAs(User::factory()->role('encargado')->create())->get('/organigrama')->assertForbidden();
    }

    public function test_user_form_assigns_a_profile(): void
    {
        $u = User::factory()->role('gerente')->create();
        $p = AccessProfile::firstWhere('name', 'Análisis');
        $this->actingAs($this->admin)->get("/users/{$u->id}/edit")->assertOk()->assertSee('Perfil de acceso');
        $this->actingAs($this->admin)->put("/users/{$u->id}", [
            'name' => $u->name, 'email' => $u->email, 'role' => 'gerente', 'area' => 'Técnica', 'access_profile_id' => $p->id, 'active' => '1',
        ])->assertSessionHasNoErrors();
        $this->assertSame($p->id, $u->fresh()->access_profile_id);
        $this->assertFalse($u->fresh()->puede('votantes', 'capturar'));   // con perfil manda el perfil
        $this->actingAs($this->admin)->get('/users')->assertSee('Análisis');
    }
}
