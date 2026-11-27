<?php

namespace App\Http\Controllers;

use App\Models\OrgMember;
use App\Models\Section;
use App\Models\Team;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Punto 19 · Organigrama. Cada persona dice a quién reporta y el organigrama se dibuja solo.
 * Quien está marcado como servidor público se resalta con una alerta.
 */
class OrgChartController extends Controller
{
    public function index(): View
    {
        $members = OrgMember::with(['team:id,name', 'section:id,clave', 'user.accessProfile:id,name', 'jefe:id,nombre'])
            ->orderBy('orden')->orderBy('nombre')->get();

        return view('org.index', [
            'members' => $members,
            'children' => $members->groupBy(fn ($m) => $m->reporta_a_id ?? 0),
            'servidores' => $members->where('servidor_publico', true),
            'teams' => Team::orderBy('name')->get(['id', 'name']),
            'sections' => Section::with('community:id,name')->orderBy('clave')->get(),
            'users' => User::orderByDesc('active')->orderBy('name')->get(['id', 'name', 'active']),
            'linkedUserIds' => $members->pluck('user_id')->filter()->all(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $member = OrgMember::create($this->validated($request));

        return redirect()->route('org.index')->with('status', "«{$member->nombre}» agregado al organigrama.");
    }

    public function update(Request $request, OrgMember $member): RedirectResponse
    {
        $member->update($this->validated($request, $member));

        return redirect()->route('org.index')->with('status', "«{$member->nombre}» actualizado.");
    }

    public function destroy(OrgMember $member): RedirectResponse
    {
        // Quienes le reportaban pasan a reportar a su jefe
        OrgMember::where('reporta_a_id', $member->id)->update(['reporta_a_id' => $member->reporta_a_id]);
        $member->delete();

        return redirect()->route('org.index')->with('status', "«{$member->nombre}» quitado del organigrama.");
    }

    private function validated(Request $request, ?OrgMember $member = null): array
    {
        $blocked = $member ? OrgMember::descendantIds($member->id) : [];

        $data = $request->validate([
            'nombre' => ['required', 'string', 'max:150'],
            'puesto' => ['required', 'string', 'max:150'],
            'reporta_a_id' => ['nullable', 'integer', 'exists:org_members,id', Rule::notIn($blocked)],
            'team_id' => ['nullable', 'integer', 'exists:teams,id'],
            'delegacion' => ['nullable', 'string', 'max:120'],
            'section_id' => ['nullable', 'integer', 'exists:sections,id'],
            'telefono' => ['nullable', 'string', 'max:30'],
            'user_id' => ['nullable', 'integer', 'exists:users,id', Rule::unique('org_members', 'user_id')->ignore($member?->id)],
            'notas' => ['nullable', 'string', 'max:500'],
            'orden' => ['nullable', 'integer', 'min:0', 'max:999'],
        ], [
            'reporta_a_id.not_in' => 'No puede reportar a sí misma ni a alguien que depende de ella.',
            'user_id.unique' => 'Esa cuenta ya está ligada a otra persona del organigrama.',
        ], ['reporta_a_id' => 'a quién reporta', 'team_id' => 'equipo', 'section_id' => 'sección', 'user_id' => 'cuenta', 'telefono' => 'teléfono', 'delegacion' => 'delegación']);

        foreach (['reporta_a_id', 'team_id', 'delegacion', 'section_id', 'telefono', 'user_id', 'notas'] as $k) {
            $data[$k] = filled($data[$k] ?? null) ? $data[$k] : null;
        }
        $data['orden'] = (int) ($data['orden'] ?? 0);
        $data['servidor_publico'] = $request->boolean('servidor_publico');

        return $data;
    }
}
