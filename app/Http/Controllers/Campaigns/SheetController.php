<?php

namespace App\Http\Controllers\Campaigns;

use App\Http\Controllers\Controller;
use App\Models\Campaign;
use App\Models\Community;
use App\Services\CampaignSheets;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Pestañas 5, 6, 7, 8 y 10 del cuaderno. Todas funcionan igual: un formulario para
 * agregar fila y la tabla con cada fila editable en su lugar, como en el Excel.
 */
class SheetController extends Controller
{
    public function index(Request $request, Campaign $campaign, string $sheet): View
    {
        $def = CampaignSheets::get($sheet);

        return view('campaigns.sheet', [
            'campaign' => $campaign,
            'sheet' => $def,
            'rows' => $this->query($request, $campaign, $def)->paginate(100)->withQueryString(),
            'all' => $campaign->{$def['relation']}()->get(),
            'summary' => CampaignSheets::summary($def, $campaign),
            'communities' => $this->hasType($def, 'community') ? $this->communities($campaign) : collect(),
        ]);
    }

    public function store(Request $request, Campaign $campaign, string $sheet): RedirectResponse
    {
        $def = CampaignSheets::get($sheet);
        $data = $this->validated($request, $campaign, $def);
        $campaign->{$def['relation']}()->create($data);

        return back()->with('status', ucfirst($def['singular']).' agregado.');
    }

    public function update(Request $request, Campaign $campaign, string $sheet, int $row): RedirectResponse
    {
        $def = CampaignSheets::get($sheet);
        $model = $campaign->{$def['relation']}()->findOrFail($row);
        $model->update($this->validated($request, $campaign, $def, $model));

        return back()->with('status', ucfirst($def['singular']).' actualizado.');
    }

    public function destroy(Campaign $campaign, string $sheet, int $row): RedirectResponse
    {
        $def = CampaignSheets::get($sheet);
        $campaign->{$def['relation']}()->findOrFail($row)->delete();

        return back()->with('status', ucfirst($def['singular']).' eliminado.');
    }

    public function export(Request $request, Campaign $campaign, string $sheet): StreamedResponse
    {
        $def = CampaignSheets::get($sheet);
        $rows = $this->query($request, $campaign, $def)->get();
        $columns = $this->columns($def);

        return response()->streamDownload(function () use ($rows, $columns) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, ['#', ...array_column($columns, 'label')]);
            foreach ($rows as $i => $row) {
                fputcsv($out, [$i + 1, ...array_map(fn ($c) => $c['computed'] ?? false
                    ? CampaignSheets::money(($c['value'])($row))
                    : CampaignSheets::display($c, $row, $c['name']), $columns)]);
            }
            fclose($out);
        }, $sheet.'-'.str($campaign->name)->slug().'.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /** Presupuesto: tope legal de gastos (dato del IEEQ). */
    public function tope(Request $request, Campaign $campaign): RedirectResponse
    {
        $data = $request->validate(['tope_gastos' => ['required', 'numeric', 'min:0', 'max:999999999999']], [], ['tope_gastos' => 'tope de gastos']);
        $campaign->update($data);

        return back()->with('status', 'Tope de gastos actualizado.');
    }

    /** Cronograma: carga las tareas sugeridas del Excel (solo si aún no hay ninguna). */
    public function template(Campaign $campaign): RedirectResponse
    {
        if ($campaign->tasks()->exists()) {
            return back()->withErrors(['tarea' => 'El cronograma ya tiene tareas; la plantilla solo se carga en uno vacío.']);
        }

        foreach (CampaignSheets::TASK_TEMPLATE as [$fase, $tarea, $area, $fecha, $nota, $estatus]) {
            $campaign->tasks()->create(['fase' => $fase, 'tarea' => $tarea, 'area' => $area, 'fecha_limite' => $fecha, 'nota_fecha' => $nota, 'estatus' => $estatus]);
        }

        return back()->with('status', 'Se cargaron '.count(CampaignSheets::TASK_TEMPLATE).' tareas del cronograma del Excel. Ajusta fechas y estatus.');
    }

    /** Columnas en orden del Excel, con las calculadas en su lugar. */
    public static function columns(array $def): array
    {
        $columns = [];
        foreach ($def['fields'] as $name => $field) {
            $columns[] = $field + ['name' => $name];
            foreach ($def['computed'] ?? [] as $cName => $c) {
                if ($c['after'] === $name) {
                    $columns[] = $c + ['name' => $cName, 'computed' => true];
                }
            }
        }

        return $columns;
    }

    private function query(Request $request, Campaign $campaign, array $def)
    {
        $query = $campaign->{$def['relation']}();
        if ($this->hasType($def, 'community')) {
            $query->with('community:id,name');
        }

        if ($request->filled('q') && $def['search']) {
            $term = '%'.$request->string('q').'%';
            $query->where(fn ($w) => collect($def['search'])->each(fn ($col) => $w->orWhere($col, 'like', $term)));
        }

        foreach ($def['filters'] as $filter) {
            if (! $request->filled($filter)) {
                continue;
            }
            $value = $request->input($filter);
            match (true) {
                $filter === 'vencidas' => $query->where('estatus', '!=', 'Hecho')->whereDate('fecha_limite', '<', today()),
                ($def['fields'][$filter]['type'] ?? null) === 'bool' => $query->where($filter, $value === 'si'),
                default => $query->where($filter, $value),
            };
        }

        foreach ($def['order'] as [$col, $dir]) {
            $query->orderBy($col, $dir);
        }

        return $query;
    }

    private function hasType(array $def, string $type): bool
    {
        return collect($def['fields'])->contains(fn ($f) => $f['type'] === $type);
    }

    private function communities(Campaign $campaign)
    {
        $used = $campaign->actors()->whereNotNull('community_id')->distinct()->pluck('community_id');

        return Community::active()->orWhereIn('id', $used)->orderBy('name')->get(['id', 'name']);
    }

    private function validated(Request $request, Campaign $campaign, array $def, ?Model $model = null): array
    {
        $rules = [];
        $attributes = [];
        $communityIds = Community::active()->pluck('id')->push($model?->community_id)->filter()->all();

        foreach ($def['fields'] as $name => $f) {
            $req = ($f['required'] ?? false) ? 'required' : 'nullable';
            $attributes[$name] = mb_strtolower($f['label']);
            $rules[$name] = match ($f['type']) {
                'text' => [$req, 'string', 'max:'.($f['max'] ?? 255)],
                'phone' => [$req, 'string', 'max:30'],
                'int' => [$req, 'integer', 'min:0', 'max:10000000'],
                'money' => [$req, 'numeric', 'min:0', 'max:999999999999'],
                'percent' => [$req, 'numeric', 'min:0', 'max:100'],
                'date' => [$req, 'date'],
                'select' => [$req, Rule::in($f['options'])],
                'bool' => ['nullable', 'boolean'],
                'community' => [$req, Rule::in([...$communityIds, 'otro'])],
            };
            if ($f['type'] === 'community') {
                $rules['community_new'] = ['nullable', 'required_if:'.$name.',otro', 'string', 'max:120'];
            }
        }

        $data = $request->validate($rules, ['community_new.required_if' => 'Escribe el nombre de la comunidad.'], $attributes);

        foreach ($def['fields'] as $name => $f) {
            $data[$name] = match ($f['type']) {
                'bool' => $request->boolean($name),
                'int' => (int) ($data[$name] ?? 0),
                'percent' => ((float) ($data[$name] ?? 0)) / 100,
                'money' => ($data[$name] ?? null) === null ? (($f['nullable'] ?? false) ? null : 0) : (float) $data[$name],
                'community' => CatalogResolver::community($data[$name] ?? null, $data['community_new'] ?? null),
                'text', 'phone' => filled($data[$name] ?? null) ? trim($data[$name]) : null,
                default => $data[$name] ?? ($f['default'] ?? null),
            };
        }
        unset($data['community_new']);

        // Sin duplicados (p. ej. la misma sección y casilla dos veces)
        if ($unique = $def['unique'] ?? null) {
            $exists = $campaign->{$def['relation']}()
                ->where(fn ($q) => collect($unique)->each(fn ($c) => $q->where($c, $data[$c])))
                ->when($model, fn ($q) => $q->whereKeyNot($model->getKey()))
                ->exists();
            if ($exists) {
                throw \Illuminate\Validation\ValidationException::withMessages([
                    $unique[0] => 'Ya existe '.($def['singular'] === 'casilla' ? 'esa casilla en esa sección' : 'ese registro').' en esta campaña.',
                ]);
            }
        }

        return $data;
    }
}
