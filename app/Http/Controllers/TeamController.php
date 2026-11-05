<?php

namespace App\Http\Controllers;

use App\Enums\Role;
use App\Models\Team;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/** Gestión de equipos. Solo administradores (ver routes/web.php). */
class TeamController extends Controller
{
    public function index(): View
    {
        $teams = Team::with('leader:id,name')
            ->withCount('members')
            ->orderBy('name')
            ->get();

        return view('teams.index', ['teams' => $teams]);
    }

    public function create(): View
    {
        return view('teams.create', [
            'team' => new Team(),
            'leaders' => $this->leaders(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $team = Team::create($this->validated($request));
        $this->attachLeader($team);

        return redirect()->route('teams.index')->with('status', 'Equipo creado.');
    }

    public function edit(Team $team): View
    {
        return view('teams.edit', [
            'team' => $team->load('members:id,name,role,team_id'),
            'leaders' => $this->leaders(),
        ]);
    }

    public function update(Request $request, Team $team): RedirectResponse
    {
        $team->update($this->validated($request));
        $this->attachLeader($team);

        return redirect()->route('teams.index')->with('status', 'Equipo actualizado.');
    }

    public function destroy(Team $team): RedirectResponse
    {
        // Los miembros quedan sin equipo (nullOnDelete en la base de datos)
        $team->delete();

        return redirect()->route('teams.index')->with('status', 'Equipo eliminado.');
    }

    private function validated(Request $request): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:2000'],
            'leader_id' => [
                'nullable',
                'integer',
                Rule::exists('users', 'id')->where('role', Role::Encargado->value),
            ],
        ], [
            'leader_id.exists' => 'El responsable debe ser un usuario con rol de encargado.',
        ], [
            'name' => 'nombre',
            'description' => 'descripción',
            'leader_id' => 'encargado',
        ]);
    }

    /** El encargado también queda como miembro del equipo si no tiene otro. */
    private function attachLeader(Team $team): void
    {
        if ($team->leader && ! $team->leader->team_id) {
            $team->leader->update(['team_id' => $team->id]);
        }
    }

    private function leaders()
    {
        return User::active()->where('role', Role::Encargado->value)->orderBy('name')->get(['id', 'name']);
    }
}
