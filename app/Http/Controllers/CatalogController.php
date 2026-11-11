<?php

namespace App\Http\Controllers;

use App\Models\ActivityType;
use App\Models\Community;
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
            'types' => ActivityType::withCount('activities')->orderByDesc('active')->orderBy('name')->get(),
            'communities' => Community::withCount('activities')->orderByDesc('active')->orderBy('name')->get(),
        ]);
    }

    public function store(Request $request, string $catalog): RedirectResponse
    {
        $config = $this->config($catalog);

        $data = $request->validateWithBag($catalog, [
            'name' => ['required', 'string', 'max:120', Rule::unique($config['table'], 'name')],
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
            'name' => ['required', 'string', 'max:120', Rule::unique($config['table'], 'name')->ignore($item->id)],
        ], [], ['name' => 'nombre']);

        $item->update(['name' => trim($data['name'])]);

        return back()->with('status', "Nombre actualizado a «{$item->name}».");
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
