<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Punto 19 · Permisos por perfil. Uso: ->middleware('puede:estrategia') o ('puede:agenda,revisar').
 * Sin acción explícita se deduce: GET → «ver» (o «exportar» si la ruta termina en .export); lo demás → «capturar».
 */
class EnsurePermission
{
    public function handle(Request $request, Closure $next, string $module, ?string $action = null): Response
    {
        $action ??= match (true) {
            str_ends_with((string) $request->route()?->getName(), '.export') => 'exportar',
            $request->isMethodSafe() => 'ver',
            default => 'capturar',
        };

        abort_unless($request->user()?->puede($module, $action), 403, 'Tu perfil no tiene permiso para esto.');

        return $next($request);
    }
}
