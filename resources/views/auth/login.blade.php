<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#1d1d1b">
    <title>Iniciar sesión · {{ config('app.name') }}</title>
    <link rel="icon" href="{{ asset('favicon.svg') }}" type="image/svg+xml">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Source+Serif+4:ital,opsz,wght@0,8..60,600;1,8..60,600&display=swap" rel="stylesheet">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="grid min-h-screen place-items-center bg-ink px-4 py-10 font-sans antialiased">
    <div class="w-full max-w-sm">
        <div class="mb-8 text-center text-white">
            <img src="{{ asset('images/logo.svg') }}" alt="WeAdvise" class="mx-auto mb-6 h-16 w-auto">
            <h1 class="text-2xl font-semibold">{{ config('app.name') }}</h1>
            <p class="mt-1 text-sm text-slate-300">Control de actividades y asistencia</p>
        </div>

        <form method="POST" action="{{ route('login.attempt') }}" class="card space-y-4 p-6">
            @csrf

            @if ($errors->any())
                <div class="rounded-lg border border-rose-200 bg-rose-50 px-3 py-2 text-sm text-rose-800">
                    {{ $errors->first() }}
                </div>
            @endif

            <div>
                <label for="email" class="label">Correo</label>
                <input id="email" name="email" type="email" value="{{ old('email') }}" required autofocus autocomplete="username" class="input">
            </div>

            <div>
                <label for="password" class="label">Contraseña</label>
                <input id="password" name="password" type="password" required autocomplete="current-password" class="input">
            </div>

            <label class="flex items-center gap-2 text-sm text-slate-600">
                <input type="checkbox" name="remember" class="size-4 rounded border-slate-300 text-brand-600">
                Mantener sesión iniciada
            </label>

            <button class="btn btn-primary w-full">Entrar</button>
        </form>
    </div>
</body>
</html>
