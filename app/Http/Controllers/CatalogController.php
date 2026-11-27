<?php

namespace App\Http\Controllers;

use App\Models\ActivityType;
use App\Models\Community;
use App\Models\Section;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Catálogos de tipos de actividad y comunidades. Solo administradores y gerentes.
 * Un elemento usado por actividades no se borra: se desactiva para que ya no
 * aparezca en el formulario sin perder el historial.
 */
class CatalogController extends Controller
{
    private const CATALOGS = [
        'tipos' => ['model' => ActivityType::class, 'table' => 'activity_types', 'one' => 'Tipo de actividad'],
        'comunidades' => ['model' => Community::class, 'table' => 'communities', 'one' => 'Comunidad'],
    ];

    public function index(): View
    {
        return view('catalogs.index', [
            'types' => ActivityType::shared()->withCount('activities')->orderByDesc('active')->orderBy('name')->get(),
            'privateTypes' => auth()->user()->isAdmin() ? ActivityType::whereNotNull('owner_id')->with('owner:id,name')->withCount('activities')->orderBy('name')->get() : collect(),
            'communities' => Community::withCount('activities')->orderByDesc('active')->orderBy('name')->get(),
            'sections' => Section::with('community:id,name')->withCount('strategyRecords')->orderByDesc('active')->orderBy('clave')->get(),
            'activeCommunities' => Community::active()->orderBy('name')->get(['id', 'name']),
        ]);
    }

    /** Secciones electorales: clave + comunidad a la que pertenece. */
    public function storeSection(Request $request): RedirectResponse
    {
        $data = $this->sectionData($request);
        $section = Section::create($data + ['active' => true]);

        return back()->with('status', "Sección {$section->clave} agregada.");
    }

    public function updateSection(Request $request, Section $section): RedirectResponse
    {
        if ($request->has('active')) {
            $section->update(['active' => $request->boolean('active')]);

            return back()->with('status', "Sección {$section->clave} ".($section->active ? 'activada.' : 'desactivada.'));
        }

        $section->update($this->sectionData($request, $section));

        return back()->with('status', "Sección {$section->clave} actualizada.");
    }

    public function destroySection(Section $section): RedirectResponse
    {
        if ($section->inUse()) {
            return back()->withErrors(['clave' => "La sección {$section->clave} ya se usa; desactívala en lugar de borrarla."], 'secciones');
        }
        $section->delete();

        return back()->with('status', "Sección {$section->clave} eliminada.");
    }

    private function sectionData(Request $request, ?Section $section = null): array
    {
        $data = $request->validateWithBag('secciones', [
            'clave' => ['required', 'string', 'max:10', 'regex:/^[0-9]{1,6}$/', Rule::unique('sections', 'clave')->ignore($section?->id)],
            'community_id' => ['nullable', 'integer', 'exists:communities,id'],
        ], ['clave.regex' => 'La sección solo lleva números (ej. 0512).', 'clave.unique' => 'Esa sección ya está en el catálogo.'], ['clave' => 'sección', 'community_id' => 'comunidad']);

        return ['clave' => trim($data['clave']), 'community_id' => $data['community_id'] ?? null];
    }

    public function store(Request $request, string $catalog): RedirectResponse
    {
        $config = $this->config($catalog);

        $data = $request->validateWithBag($catalog, [
            'name' => ['required', 'string', 'max:120', $catalog === 'tipos' ? Rule::unique('activity_types', 'name')->whereNull('owner_id') : Rule::unique($config['table'], 'name')],
        ], [], ['name' => 'nombre']);

        $config['model']::create(['name' => trim($data['name']), 'active' => true]);

        return back()->with('status', "{$config['one']} «{$data['name']}» agregado.");
    }

    public function update(Request $request, string $catalog, int $id): RedirectResponse
    {
        $config = $this->config($catalog);
        $item = $this->find($catalog, $id);

        if ($request->has('active')) {
            $item->update(['active' => $request->boolean('active')]);

            return back()->with('status', "«{$item->name}» ".($item->active ? 'activado.' : 'desactivado: ya no aparece al cargar actividades.'));
        }

        $data = $request->validateWithBag($catalog, [
            'name' => ['required', 'string', 'max:120', ($catalog === 'tipos' ? Rule::unique('activity_types', 'name')->where('owner_id', $item->owner_id) : Rule::unique($config['table'], 'name'))->ignore($item->id)],
        ], [], ['name' => 'nombre']);

        $item->update(['name' => trim($data['name'])]);

        return back()->with('status', "Nombre actualizado a «{$item->name}».");
    }

    /**
     * Admin: pasa un tipo privado a la lista común. Si ya hay uno común con el mismo nombre,
     * sus actividades se juntan con ese y el privado se elimina.
     */
    public function shareType(int $id): RedirectResponse
    {
        abort_unless(auth()->user()->isAdmin(), 403);
        $type = ActivityType::whereNotNull('owner_id')->findOrFail($id);
        $shared = ActivityType::shared()->whereRaw('LOWER(name) = ?', [mb_strtolower($type->name)])->first();

        if ($shared) {
            \App\Models\Activity::where('activity_type_id', $type->id)->update(['activity_type_id' => $shared->id]);
            $type->delete();
        } else {
            $type->update(['owner_id' => null]);
        }

        return back()->with('status', "«{$type->name}» ahora está en la lista común: todos lo pueden elegir.");
    }

    public function destroy(string $catalog, int $id): RedirectResponse
    {
        $item = $this->find($catalog, $id);

        if ($item->inUse()) {
            return back()->withErrors(['name' => "«{$item->name}» ya se usa en actividades o campañas; desactívalo en lugar de borrarlo."], $catalog);
        }

        $item->delete();

        return back()->with('status', "«{$item->name}» eliminado.");
    }

    private function config(string $catalog): array
    {
        abort_unless(isset(self::CATALOGS[$catalog]), 404);

        return self::CATALOGS[$catalog];
    }

    private function find(string $catalog, int $id): Model
    {
        return $this->config($catalog)['model']::findOrFail($id);
    }
}
