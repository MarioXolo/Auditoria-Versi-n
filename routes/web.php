<?php

use App\Http\Controllers\ActivityController;
use App\Http\Controllers\AttendanceController;
use App\Http\Controllers\CalendarController;
use App\Http\Controllers\AccessProfileController;
use App\Http\Controllers\CatalogController;
use App\Http\Controllers\OrgChartController;
use App\Http\Controllers\Campaigns\CampaignController;
use App\Http\Controllers\Campaigns\KpiController;
use App\Http\Controllers\Campaigns\PromoterController;
use App\Http\Controllers\Campaigns\SheetController;
use App\Http\Controllers\Campaigns\StrategyController;
use App\Http\Controllers\Campaigns\AgendaController;
use App\Http\Controllers\Campaigns\TerritoryController;
use App\Http\Controllers\Campaigns\VoterController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\MinuteController;
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

    // Inicio: todos van a su dashboard (el de auditoría, o el propio del encargado)
    Route::get('/', fn () => redirect()->route('dashboard'))->name('home');
    Route::get('/dashboard', DashboardController::class)->name('dashboard');

    Route::middleware('role:admin,gerente')->group(function () {
        Route::get('/dashboard/exportar', [DashboardController::class, 'export'])->name('dashboard.export');

    });

    // Minutas de reunión (formato de Excel de 5 secciones) · permisos por perfil (punto 19)
    Route::middleware('puede:minutas')->group(function () {
        Route::post('/minutas/importar', [MinuteController::class, 'import'])->name('minutes.import');
        Route::resource('minutas', MinuteController::class)->parameters(['minutas' => 'minute'])->names('minutes');
    });

    // Actividades (los permisos finos están en ActivityPolicy)
    Route::resource('activities', ActivityController::class);
    Route::post('/activities/{activity}/marcar', [ActivityController::class, 'mark'])->name('activities.mark');
    Route::patch('/activities/{activity}/estado', [ActivityController::class, 'updateStatus'])->name('activities.status');
    Route::get('/activities/{activity}/evidence', [ActivityController::class, 'evidence'])->name('activities.evidence');
    Route::post('/activities/{activity}/ubicacion', [ActivityController::class, 'validateLocation'])->middleware('throttle:20,1')->name('activities.location');
    Route::post('/activities/{activity}/ubicacion/manual', [ActivityController::class, 'validateLocationManually'])->name('activities.location.manual');
    Route::delete('/activities/{activity}/ubicacion/manual', [ActivityController::class, 'revokeManualLocation'])->name('activities.location.revoke');

    // Calendario de actividades (cada quien ve lo que su rol le permite); se imprime a PDF desde el navegador
    Route::get('/calendario', CalendarController::class)->name('calendar.index');

    // Asistencia (checador): solo el administrador. No interviene en ningún indicador.
    Route::middleware('role:admin')->group(function () {
        Route::get('/asistencia', [AttendanceController::class, 'index'])->name('attendance.index');
        Route::post('/asistencia/registrar', [AttendanceController::class, 'register'])->middleware('throttle:10,1')->name('attendance.register');
        Route::get('/asistencia/{attendance}/foto/{type}', [AttendanceController::class, 'photo'])
            ->whereIn('type', ['entrada', 'salida'])
            ->name('attendance.photo');
    });

    // Perfil
    Route::get('/perfil', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::put('/perfil/password', [ProfileController::class, 'updatePassword'])->name('profile.password');

    // Administración y gerencia: equipos y consulta de usuarios
    Route::middleware('role:admin,gerente')->group(function () {
        Route::get('/users', [UserController::class, 'index'])->name('users.index');
        Route::resource('teams', TeamController::class)->except(['show']);

    });

    // Organigrama y perfiles de acceso (punto 19)
    Route::middleware('puede:organigrama')->group(function () {
        Route::get('/organigrama', [OrgChartController::class, 'index'])->name('org.index');
        Route::post('/organigrama', [OrgChartController::class, 'store'])->name('org.store');
        Route::put('/organigrama/{member}', [OrgChartController::class, 'update'])->name('org.update');
        Route::delete('/organigrama/{member}', [OrgChartController::class, 'destroy'])->name('org.destroy');
    });

    // Catálogos: tipos de actividad, comunidades y secciones electorales
    Route::middleware('puede:catalogos')->group(function () {
        Route::get('/catalogos', [CatalogController::class, 'index'])->name('catalogs.index');
        Route::post('/catalogos/secciones', [CatalogController::class, 'storeSection'])->name('sections.store');
        Route::patch('/catalogos/secciones/{section}', [CatalogController::class, 'updateSection'])->name('sections.update');
        Route::delete('/catalogos/secciones/{section}', [CatalogController::class, 'destroySection'])->name('sections.destroy');
        Route::post('/catalogos/{catalog}', [CatalogController::class, 'store'])->whereIn('catalog', ['tipos', 'comunidades'])->name('catalogs.store');
        Route::patch('/catalogos/{catalog}/{id}', [CatalogController::class, 'update'])->whereIn('catalog', ['tipos', 'comunidades'])->whereNumber('id')->name('catalogs.update');
        Route::delete('/catalogos/{catalog}/{id}', [CatalogController::class, 'destroy'])->whereIn('catalog', ['tipos', 'comunidades'])->whereNumber('id')->name('catalogs.destroy');
    });

    // Cuaderno Maestro de Campaña: uno solo; /campanas abre el que existe (o su configuración inicial).
    // Cada módulo exige su permiso del perfil (punto 19); cada registro pertenece a su campaña (scopeBindings).
    Route::get('/campanas', [CampaignController::class, 'index'])->name('campaigns.index');
    Route::get('/campanas/{campaign}', [CampaignController::class, 'show'])->whereNumber('campaign')->name('campaigns.show');
    Route::middleware('puede:cuaderno,capturar')->group(function () {
        Route::get('/campanas/create', [CampaignController::class, 'create'])->name('campaigns.create');
        Route::post('/campanas', [CampaignController::class, 'store'])->name('campaigns.store');
        Route::get('/campanas/{campaign}/edit', [CampaignController::class, 'edit'])->name('campaigns.edit');
        Route::match(['put', 'patch'], '/campanas/{campaign}', [CampaignController::class, 'update'])->name('campaigns.update');
    });
    Route::prefix('/campanas/{campaign}')->name('campaigns.')->scopeBindings()->group(function () {
        Route::middleware('puede:cuaderno')->group(function () {
            Route::get('/estructura', [TerritoryController::class, 'index'])->name('territories.index');
            Route::post('/estructura', [TerritoryController::class, 'store'])->name('territories.store');
            Route::put('/estructura/{territory}', [TerritoryController::class, 'update'])->name('territories.update');
            Route::delete('/estructura/{territory}', [TerritoryController::class, 'destroy'])->name('territories.destroy');

            Route::get('/kpi', [KpiController::class, 'index'])->name('kpis.index');
            Route::post('/kpi', [KpiController::class, 'store'])->name('kpis.store');
            Route::put('/kpi/{kpi}', [KpiController::class, 'update'])->name('kpis.update');
            Route::delete('/kpi/{kpi}', [KpiController::class, 'destroy'])->name('kpis.destroy');
        });

        Route::middleware('puede:promotores')->group(function () {
            Route::get('/promotores/exportar', [PromoterController::class, 'export'])->name('promoters.export');
            Route::resource('promotores', PromoterController::class)->parameters(['promotores' => 'promoter'])->names('promoters')->except(['show']);
        });

        Route::middleware('puede:votantes')->group(function () {
            Route::get('/votantes/exportar', [VoterController::class, 'export'])->name('voters.export');
            Route::resource('votantes', VoterController::class)->parameters(['votantes' => 'voter'])->names('voters')->except(['show']);
        });

        // 11. Estrategia de campaña y 20. Agenda reportada al IEEQ
        // Puntos 11 a 18 (el permiso de cada libro lo revisa el controlador según su punto)
        Route::get('/estrategia', [StrategyController::class, 'hub'])->name('strategy.hub');
        Route::get('/punto/{punto}', [StrategyController::class, 'hub'])->whereIn('punto', ['12', '13', '14', '15', '16', '17', '18'])->name('strategy.punto');
        Route::get('/agenda-ieeq', [AgendaController::class, 'index'])->middleware('puede:agenda')->name('agenda.index');
        Route::put('/agenda-ieeq/{record}', [AgendaController::class, 'update'])->middleware('puede:agenda,revisar')->withoutScopedBindings()->name('agenda.update');
        Route::prefix('/estrategia/{libro}')->whereIn('libro', \App\Services\StrategyBooks::keys())->withoutScopedBindings()->group(function () {
            Route::get('/', [StrategyController::class, 'index'])->name('strategy.index');
            Route::get('/nuevo', [StrategyController::class, 'create'])->name('strategy.create');
            Route::post('/{record}/subir', [StrategyController::class, 'escalate'])->name('strategy.escalate');
            Route::post('/', [StrategyController::class, 'store'])->name('strategy.store');
            Route::get('/exportar', [StrategyController::class, 'export'])->name('strategy.export');
            Route::get('/formato/{record?}', [StrategyController::class, 'formato'])->name('strategy.formato');
            Route::get('/{record}/editar', [StrategyController::class, 'edit'])->name('strategy.edit');
            Route::put('/{record}', [StrategyController::class, 'update'])->name('strategy.update');
            Route::delete('/{record}', [StrategyController::class, 'destroy'])->name('strategy.destroy');
            Route::get('/{record}/archivo/{campo}', [StrategyController::class, 'file'])->where('campo', '[a-z_]+')->name('strategy.file');
        });

        // Pestañas 1-2, 5-10 genéricas (mismo controlador; columnas en App\Services\CampaignSheets)
        Route::middleware('puede:cuaderno')->group(function () {
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
        Route::get('/perfiles', [AccessProfileController::class, 'index'])->name('profiles.index');
        Route::post('/perfiles', [AccessProfileController::class, 'store'])->name('profiles.store');
        Route::put('/perfiles/{profile}', [AccessProfileController::class, 'update'])->name('profiles.update');
        Route::delete('/perfiles/{profile}', [AccessProfileController::class, 'destroy'])->name('profiles.destroy');
    });
});
