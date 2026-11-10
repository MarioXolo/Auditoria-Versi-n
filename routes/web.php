<?php

use App\Http\Controllers\ActivityController;
use App\Http\Controllers\AttendanceController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\TeamController;
use App\Http\Controllers\UserController;
use Illuminate\Support\Facades\Route;

// ---------- Invitados ----------
Route::middleware('guest')->group(function () {
    Route::get('/login', [LoginController::class, 'show'])->name('login');
    Route::post('/login', [LoginController::class, 'login'])->name('login.attempt');
});

// ---------- Usuarios con sesión ----------
Route::middleware(['auth', 'active'])->group(function () {
    Route::post('/logout', [LoginController::class, 'logout'])->name('logout');

    // Inicio: administradores y gerentes van al dashboard, el resto a sus actividades
    Route::get('/', function () {
        return auth()->user()->seesEverything()
            ? redirect()->route('dashboard')
            : redirect()->route('activities.index');
    })->name('home');

    // Dashboard de auditoría
    Route::middleware('role:admin,gerente')->group(function () {
        Route::get('/dashboard', DashboardController::class)->name('dashboard');
        Route::get('/dashboard/exportar', [DashboardController::class, 'export'])->name('dashboard.export');
    });

    // Actividades (los permisos finos están en ActivityPolicy)
    Route::resource('activities', ActivityController::class);
    Route::patch('/activities/{activity}/status', [ActivityController::class, 'updateStatus'])->name('activities.status');
    Route::get('/activities/{activity}/evidence', [ActivityController::class, 'evidence'])->name('activities.evidence');

    // Asistencia
    Route::get('/asistencia', [AttendanceController::class, 'index'])->name('attendance.index');
    Route::post('/asistencia/entrada', [AttendanceController::class, 'checkIn'])->name('attendance.check-in');
    Route::post('/asistencia/salida', [AttendanceController::class, 'checkOut'])->name('attendance.check-out');
    Route::get('/asistencia/{attendance}/foto/{type}', [AttendanceController::class, 'photo'])
        ->whereIn('type', ['entrada', 'salida'])
        ->name('attendance.photo');

    // Perfil
    Route::get('/perfil', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::put('/perfil/password', [ProfileController::class, 'updatePassword'])->name('profile.password');

    // Administración y gerencia: equipos y consulta de usuarios
    Route::middleware('role:admin,gerente')->group(function () {
        Route::get('/users', [UserController::class, 'index'])->name('users.index');
        Route::resource('teams', TeamController::class)->except(['show']);
    });

    // Solo administradores: crear, editar y desactivar usuarios
    Route::middleware('role:admin')->group(function () {
        Route::resource('users', UserController::class)->except(['index', 'show', 'destroy']);
        Route::patch('/users/{user}/toggle', [UserController::class, 'toggle'])->name('users.toggle');
    });
});
