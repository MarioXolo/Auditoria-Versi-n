<?php

namespace App\Http\Controllers;

use App\Enums\Role;
use App\Models\Attendance;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AttendanceController extends Controller
{
    public function index(Request $request): View
    {
        $user = $request->user();

        $today = Attendance::where('user_id', $user->id)
            ->whereDate('work_date', today())
            ->first();

        $history = Attendance::where('user_id', $user->id)
            ->orderByDesc('work_date')
            ->limit(14)
            ->get();

        $data = [
            'today' => $today,
            'history' => $history,
            'roster' => null,
            'date' => null,
        ];

        // Encargados, gerentes y administradores ven la asistencia de su gente
        // Solo administradores y gerentes ven la asistencia del personal
        if ($user->seesEverything()) {
            $date = $request->filled('date')
                ? Carbon::parse($request->input('date'))->startOfDay()
                : today();

            $people = $this->peopleFor($user)->get(['id', 'name', 'role', 'team_id']);

            $records = Attendance::whereDate('work_date', $date)
                ->whereIn('user_id', $people->pluck('id'))
                ->get()
                ->keyBy('user_id');

            $data['date'] = $date;
            $data['roster'] = $people->map(fn (User $person) => [
                'user' => $person,
                'record' => $records->get($person->id),
            ]);
        }

        return view('attendance.index', $data);
    }

    public function checkIn(Request $request): RedirectResponse
    {
        $user = $request->user();

        $request->validate([
            'photo' => ['required', 'image', 'max:8192'],
            'notes' => ['nullable', 'string', 'max:500'],
        ], [
            'photo.required' => 'Toma una foto para registrar tu entrada.',
        ], ['photo' => 'foto']);

        $exists = Attendance::where('user_id', $user->id)->whereDate('work_date', today())->exists();

        if ($exists) {
            return back()->withErrors(['photo' => 'Ya registraste tu entrada de hoy.']);
        }

        Attendance::create([
            'user_id' => $user->id,
            'work_date' => today()->toDateString(),
            'check_in_at' => now(),
            'check_in_photo' => $request->file('photo')->store('asistencia/'.today()->format('Y-m'), 'local'),
            'notes' => $request->input('notes'),
        ]);

        return back()->with('status', 'Entrada registrada a las '.now()->format('H:i').'.');
    }

    public function checkOut(Request $request): RedirectResponse
    {
        $user = $request->user();

        $request->validate([
            'photo' => ['nullable', 'image', 'max:8192'],
        ], [], ['photo' => 'foto']);

        $attendance = Attendance::where('user_id', $user->id)->whereDate('work_date', today())->first();

        if (! $attendance) {
            return back()->withErrors(['photo' => 'Primero registra tu entrada.']);
        }

        if ($attendance->check_out_at) {
            return back()->withErrors(['photo' => 'Ya registraste tu salida de hoy.']);
        }

        $attendance->check_out_at = now();

        if ($request->hasFile('photo')) {
            $attendance->check_out_photo = $request->file('photo')->store('asistencia/'.today()->format('Y-m'), 'local');
        }

        $attendance->save();

        return back()->with('status', 'Salida registrada a las '.now()->format('H:i').'.');
    }

    /** Foto de entrada o salida, solo para quien tiene permiso de verla. */
    public function photo(Request $request, Attendance $attendance, string $type): StreamedResponse
    {
        $viewer = $request->user();

        $allowed = $viewer->id === $attendance->user_id || $viewer->seesEverything();

        abort_unless($allowed, 403);

        $path = match ($type) {
            'entrada' => $attendance->check_in_photo,
            'salida' => $attendance->check_out_photo,
            default => null,
        };

        abort_unless($path && Storage::disk('local')->exists($path), 404);

        return Storage::disk('local')->response($path);
    }

    private function peopleFor(User $user)
    {
        $query = User::active()->orderBy('name');

        if ($user->seesEverything()) {
            return $query->whereIn('role', [Role::Encargado->value, Role::Empleado->value]);
        }

        return $query->whereIn('id', $user->teamMemberIds() ?: [0])
            ->where('id', '!=', $user->id);
    }
}
