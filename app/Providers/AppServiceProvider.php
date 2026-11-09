<?php

namespace App\Providers;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        // Fechas en español ("lunes 5 de noviembre")
        Carbon::setLocale(config('app.locale'));

        // En producción, generar todas las URLs con HTTPS (la cámara del celular lo requiere)
        if ($this->app->environment('production')) {
            URL::forceScheme('https');
        }

        // Vista previa en GitHub Codespaces: el proxy reenvía la petición como "localhost",
        // así que forzamos la URL pública del puerto 8000 para que carguen estilos y formularios.
        $codespace = env('CODESPACE_NAME');
        $domain = env('GITHUB_CODESPACES_PORT_FORWARDING_DOMAIN');
        if ($codespace && $domain && ! $this->app->runningInConsole()) {
            URL::forceRootUrl("https://{$codespace}-8000.{$domain}");
            URL::forceScheme('https');
        }
    }
}
