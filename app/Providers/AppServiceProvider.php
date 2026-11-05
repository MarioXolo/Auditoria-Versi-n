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
    }
}
