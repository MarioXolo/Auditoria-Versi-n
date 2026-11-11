<?php

use App\Http\Controllers\ActivityController;
use App\Http\Controllers\AttendanceController;
use App\Http\Controllers\CalendarController;
use App\Http\Controllers\CatalogController;
use App\Http\Controllers\Campaigns\CampaignController;
use App\Http\Controllers\Campaigns\KpiController;
use App\Http\Controllers\Campaigns\PromoterController;
use App\Http\Controllers\Campaigns\SheetController;
use App\Http\Controllers\Campaigns\TerritoryController;
use App\Http\Controllers\Campaigns\VoterController;
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
    Route::post('/activities/{activity}/marcar', [ActivityController::class, 'mark'])->name('activities.mark');
    Route::patch('/activities/{activity}/estado', [ActivityController::class, 'updateStatus'])->name('activities.status');
    Route::get('/activities/{activity}/evidence', [ActivityController::class, 'evidence'])->name('activities.evidence');

    // Calendario de actividades (cada quien ve lo que su rol le permite); se imprime a PDF desde el navegador
    Route::get('/calendario', CalendarController::class)->name('calendar.index');

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

        // Catálogos: tipos de actividad y comunidades
        Route::get('/catalogos', [CatalogController::class, 'index'])->name('catalogs.index');
        Route::post('/catalogos/{catalog}', [CatalogController::class, 'store'])->whereIn('catalog', ['tipos', 'comunidades'])->name('catalogs.store');
        Route::patch('/catalogos/{catalog}/{id}', [CatalogController::class, 'update'])->whereIn('catalog', ['tipos', 'comunidades'])->whereNumber('id')->name('catalogs.update');
        Route::delete('/catalogos/{catalog}/{id}', [CatalogController::class, 'destroy'])->whereIn('catalog', ['tipos', 'comunidades'])->whereNumber('id')->name('catalogs.destroy');

        // V2 · Cuaderno Maestro de Campaña (cada registro pertenece a su campaña: scopeBindings)
        Route::resource('campanas', CampaignController::class)->parameters(['campanas' => 'campaign'])->names('campaigns');
        Route::patch('/campanas/{campaign}/archivar', [CampaignController::class, 'toggle'])->name('campaigns.toggle');
        Route::prefix('/campanas/{campaign}')->name('campaigns.')->scopeBindings()->group(function () {
            Route::get('/estructura', [TerritoryController::class, 'index'])->name('territories.index');
            Route::post('/estructura', [TerritoryController::class, 'store'])->name('territories.store');
            Route::put('/estructura/{territory}', [TerritoryController::class, 'update'])->name('territories.update');
            Route::delete('/estructura/{territory}', [TerritoryController::class, 'destroy'])->name('territories.destroy');

            Route::get('/promotores/exportar', [PromoterController::class, 'export'])->name('promoters.export');
            Route::resource('promotores', PromoterController::class)->parameters(['promotores' => 'promoter'])->names('promoters')->except(['show']);

            Route::get('/votantes/exportar', [VoterController::class, 'export'])->name('voters.export');
            Route::resource('votantes', VoterController::class)->parameters(['votantes' => 'voter'])->names('voters')->except(['show']);

            Route::get('/kpi', [KpiController::class, 'index'])->name('kpis.index');
            Route::post('/kpi', [KpiController::class, 'store'])->name('kpis.store');
            Route::put('/kpi/{kpi}', [KpiController::class, 'update'])->name('kpis.update');
            Route::delete('/kpi/{kpi}', [KpiController::class, 'destroy'])->name('kpis.destroy');

            // Etapa 2: pestañas 5, 6, 7, 8 y 10 (mismo controlador; columnas en App\Services\CampaignSheets)
            Route::put('/presupuesto/tope', [SheetController::class, 'tope'])->name('sheets.tope');
            Route::post('/cronograma/plantilla', [SheetController::class, 'template'])->name('sheets.template');
            Route::pattern('sheet', implode('|', \App\Services\CampaignSheets::keys()));
            Route::get('/{sheet}/exportar', [SheetController::class, 'export'])->name('sheets.export');
            Route::get('/{sheet}', [SheetController::class, 'index'])->name('sheets.index');
            Route::post('/{sheet}', [SheetController::class, 'store'])->name('sheets.store');
            Route::put('/{sheet}/{row}', [SheetController::class, 'update'])->whereNumber('row')->name('sheets.update');
            Route::delete('/{sheet}/{row}', [SheetController::class, 'destroy'])->whereNumber('row')->name('sheets.destroy');
        });
    });

    // Solo administradores: crear, editar y desactivar usuarios
    Route::middleware('role:admin')->group(function () {
        Route::resource('users', UserController::class)->except(['index', 'show', 'destroy']);
        Route::patch('/users/{user}/toggle', [UserController::class, 'toggle'])->name('users.toggle');
    });
});
