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

        // En producción con HTTPS, generar todas las URLs con https (la cámara del celular lo requiere).
        // Mientras el servidor solo tenga IP (sin certificado), se respeta http.
        if ($this->app->environment('production') && str_starts_with((string) config('app.url'), 'https://')) {
            URL::forceScheme('https');
        }

        // Vista previa (p. ej. GitHub Codespaces): el proxy reenvía la petición como "localhost",
        // así que con APP_FORCE_URL=true todas las URLs usan APP_URL.
        if (config('app.force_url')) {
            URL::forceRootUrl(config('app.url'));
            if (str_starts_with(config('app.url'), 'https://')) {
                URL::forceScheme('https');
            }
        }
    }
}
