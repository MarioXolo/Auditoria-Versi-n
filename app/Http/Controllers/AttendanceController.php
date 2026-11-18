<?php

namespace App\Http\Controllers;

use App\Enums\Role;
use App\Models\Attendance;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use App\Services\PhotoStorage;
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
        // Encargados ven a su equipo; gerentes y administradores, a todo el personal
        if ($user->canManageActivities()) {
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

    /**
     * Un solo botón: registra la entrada si aún no hay, o la salida si ya hay entrada.
     * Sin foto. Solo en días laborales (lunes a viernes).
     */
    public function register(Request $request): RedirectResponse
    {
        $request->validate(['notes' => ['nullable', 'string', 'max:500']]);

        if (! Attendance::isWorkday()) {
            return back()->withErrors(['asistencia' => 'Hoy no es día laboral. El checador solo registra de '.Attendance::workdaysLabel().'.']);
        }

        $user = $request->user();
        $attendance = Attendance::where('user_id', $user->id)->whereDate('work_date', today())->first();

        if (! $attendance) {
            Attendance::create([
                'user_id' => $user->id,
                'work_date' => today()->toDateString(),
                'check_in_at' => now(),
                'notes' => $request->input('notes'),
            ]);

            return back()->with('status', 'Entrada registrada a las '.now()->format('H:i').'.');
        }

        if ($attendance->check_out_at) {
            return back()->withErrors(['asistencia' => 'Ya registraste tu entrada y tu salida de hoy.']);
        }

        $attendance->check_out_at = now();
        if ($request->filled('notes')) {
            $attendance->notes = trim(($attendance->notes ? $attendance->notes.' · ' : '').$request->input('notes'));
        }
        $attendance->save();

        return back()->with('status', 'Salida registrada a las '.now()->format('H:i').'. Jornada de '.$attendance->workedTime().'.');
    }

    /** Foto de entrada o salida de registros anteriores (el checador ya no pide foto). */
    public function photo(Request $request, Attendance $attendance, string $type): StreamedResponse
    {
        $viewer = $request->user();

        $allowed = $viewer->id === $attendance->user_id
            || $viewer->seesEverything()
            || ($viewer->isEncargado() && in_array($attendance->user_id, $viewer->teamMemberIds(), true));

        abort_unless($allowed, 403);

        $path = match ($type) {
            'entrada' => $attendance->check_in_photo,
            'salida' => $attendance->check_out_photo,
            default => null,
        };

        abort_unless(PhotoStorage::exists($path), 404);

        return PhotoStorage::response($path);
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
