<?php

namespace Tests\Feature;

use App\Models\Campaign;
use App\Models\Community;
use App\Models\Section;
use App\Models\User;
use App\Services\CampaignMetrics;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Semáforo del voto por sección en «Meta de votos»: la meta se reparte por lista nominal. */
class SectionVoteSemaphoreTest extends TestCase
{
    use RefreshDatabase;

    public function test_meta_is_split_by_nominal_list_and_voters_are_counted_per_section(): void
    {
        $admin = User::factory()->role('admin')->create();
        $this->actingAs($admin)->post('/campanas', [
            'name' => 'Huimilpan 2027', 'municipality' => 'Huimilpan', 'listado_nominal' => 3000,
            'participacion' => 50, 'objetivo' => 50, 'colchon' => 0, 'votantes_por_promotor' => 10,
        ]);
        $campaign = Campaign::first();
        $meta = $campaign->metaConColchon();

        [$centro, $ceja, $taponas, $vegil] = collect(['Huimilpan Centro', 'La Ceja', 'Las Taponas', 'El Vegil'])
            ->map(fn ($n) => Community::create(['name' => $n, 'active' => true]));

        // Catálogo: lista nominal, representante y varias comunidades por sección
        $this->post('/catalogos/secciones', ['clave' => '139', 'listado_nominal' => 2000, 'communities' => [$centro->id, $ceja->id, $taponas->id]])->assertSessionHasNoErrors();
        $s139 = Section::where('clave', '139')->first();
        $this->patch("/catalogos/secciones/{$s139->id}", ['clave' => '139', 'listado_nominal' => 2000, 'representante' => 'Rep Uno', 'communities' => [$centro->id, $ceja->id, $taponas->id]])->assertSessionHasNoErrors();
        $this->post('/catalogos/secciones', ['clave' => '148', 'listado_nominal' => 1000, 'communities' => [$taponas->id]]);
        $this->post('/catalogos/secciones', ['clave' => '147', 'communities' => [$vegil->id]]); // sin lista nominal
        $this->assertSame(3, $s139->fresh()->communities()->count());
        $this->assertSame('Rep Uno', $s139->fresh()->representante);

        $v = fn (array $a) => $campaign->voters()->create($a + ['nombre' => 'Votante']);
        $v(['seccion' => '139', 'community_id' => $centro->id]);
        $v(['seccion' => '0139', 'community_id' => $ceja->id]);       // con ceros a la izquierda
        $v(['seccion' => null, 'community_id' => $centro->id]);       // sin sección: su comunidad está en una sola
        $v(['seccion' => null, 'community_id' => $taponas->id]);      // sin sección y comunidad en dos: no cuenta
        $v(['seccion' => '148', 'community_id' => $taponas->id]);

        $data = (new CampaignMetrics($campaign))->sectionRows();
        $rows = $data['rows']->keyBy(fn ($r) => $r->section->clave);

        $this->assertSame(['139', '147', '148'], $data['rows']->map(fn ($r) => $r->section->clave)->all());
        $this->assertSame((int) round($meta * 2 / 3), $rows[139]->meta);
        $this->assertSame((int) round($meta / 3), $rows[148]->meta);
        $this->assertNull($rows[147]->meta);
        $this->assertSame('gris', $rows[147]->color);
        $this->assertSame(3, $rows[139]->identificados);
        $this->assertSame(1, $rows[148]->identificados);
        $this->assertSame(1, $data['totals']->sin_seccion);
        $this->assertSame(2, $rows[139]->comunidades->firstWhere('name', 'Huimilpan Centro')->identificados);
        $this->assertSame('rojo', $rows[139]->color);

        $this->get("/campanas/{$campaign->id}/edit")->assertOk()
            ->assertSee('Semáforo del voto por sección')->assertSee('Sección 139')->assertSee('Rep Uno')->assertSee('data-semaforo="gris"', false);
    }
}
