<?php

namespace App\Http\Controllers;

use App\Enums\Role;
use App\Models\Team;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Enum;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;

/** Gestión de usuarios. Solo administradores (ver routes/web.php). */
class UserController extends Controller
{
    public function index(Request $request): View
    {
        $users = User::query()
            ->with('team:id,name')
            ->when($request->filled('q'), function ($q) use ($request) {
                $term = '%'.$request->string('q').'%';
                $q->where(fn ($w) => $w->where('name', 'like', $term)->orWhere('email', 'like', $term));
            })
            ->when($request->filled('role'), fn ($q) => $q->where('role', $request->input('role')))
            ->when($request->filled('team_id'), fn ($q) => $q->where('team_id', $request->integer('team_id')))
            ->orderByDesc('active')
            ->orderBy('name')
            ->paginate(20)
            ->withQueryString();

        return view('users.index', [
            'users' => $users,
            'roles' => Role::options(),
            'teams' => Team::orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function create(): View
    {
        return view('users.create', [
            'user' => new User(['role' => Role::Empleado, 'active' => true]),
            'roles' => Role::options(),
            'teams' => Team::orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);
        $data['email_verified_at'] = now();

        User::create($data);

        return redirect()->route('users.index')->with('status', 'Usuario creado.');
    }

    public function edit(User $user): View
    {
        return view('users.edit', [
            'user' => $user,
            'roles' => Role::options(),
            'teams' => Team::orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function update(Request $request, User $user): RedirectResponse
    {
        $data = $this->validated($request, $user);

        if (blank($data['password'] ?? null)) {
            unset($data['password']);
        }

        // Un administrador no puede quitarse a sí mismo el rol ni desactivarse
        if ($user->is($request->user())) {
            $data['role'] = Role::Admin->value;
            $data['active'] = true;
        }

        $user->update($data);

        return redirect()->route('users.index')->with('status', 'Usuario actualizado.');
    }

    public function toggle(Request $request, User $user): RedirectResponse
    {
        if ($user->is($request->user())) {
            return back()->withErrors(['user' => 'No puedes desactivar tu propia cuenta.']);
        }

        $user->update(['active' => ! $user->active]);

        return back()->with('status', $user->active ? 'Usuario activado.' : 'Usuario desactivado.');
    }

    private function validated(Request $request, ?User $user = null): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user?->id)],
            'phone' => ['nullable', 'string', 'max:30'],
            'role' => ['required', new Enum(Role::class)],
            'team_id' => ['nullable', 'integer', 'exists:teams,id'],
            'password' => [$user ? 'nullable' : 'required', 'confirmed', Password::min(8)],
        ], [], [
            'name' => 'nombre',
            'email' => 'correo',
            'phone' => 'teléfono',
            'role' => 'rol',
            'team_id' => 'equipo',
            'password' => 'contraseña',
        ]);

        $data['active'] = $request->boolean('active');

        return $data;
    }
}
