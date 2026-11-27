<?php

namespace App\Http\Controllers;

use App\Models\AccessProfile;
use App\Support\Access;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/** Punto 19 · Perfiles de acceso: el administrador decide qué ve, captura, revisa y exporta cada perfil. */
class AccessProfileController extends Controller
{
    public function index(): View
    {
        return view('profiles.index', [
            'profiles' => AccessProfile::withCount('users')->orderBy('name')->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $profile = AccessProfile::create($this->validated($request));

        return redirect()->route('profiles.index')->with('status', "Perfil «{$profile->name}» creado.");
    }

    public function update(Request $request, AccessProfile $profile): RedirectResponse
    {
        $profile->update($this->validated($request, $profile));

        return redirect()->route('profiles.index')->with('status', "Perfil «{$profile->name}» guardado.");
    }

    public function destroy(AccessProfile $profile): RedirectResponse
    {
        $n = $profile->users()->count();
        $profile->delete(); // los usuarios que lo tenían quedan sin perfil

        return redirect()->route('profiles.index')->with('status', "Perfil «{$profile->name}» eliminado.".($n ? " {$n} usuario(s) quedaron sin perfil." : ''));
    }

    private function validated(Request $request, ?AccessProfile $profile = null): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:80', Rule::unique('access_profiles', 'name')->ignore($profile?->id)],
            'description' => ['nullable', 'string', 'max:255'],
            'permisos' => ['nullable', 'array'],
        ], [], ['name' => 'nombre del perfil', 'description' => 'descripción']);

        return [
            'name' => trim($data['name']),
            'description' => $data['description'] ?? null,
            'permisos' => Access::clean($data['permisos'] ?? []),
            'solo_sus_votantes' => $request->boolean('solo_sus_votantes'),
        ];
    }
}
