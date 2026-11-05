<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#10302c">
    <title>Iniciar sesión · {{ config('app.name') }}</title>
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=instrument-sans:400,500,600,700" rel="stylesheet">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="grid min-h-screen place-items-center bg-brand-900 px-4 py-10 font-sans antialiased">
    <div class="w-full max-w-sm">
        <div class="mb-8 text-center text-white">
            <span class="mx-auto mb-4 grid size-12 place-items-center rounded-xl bg-brand-500 text-lg font-bold">AC</span>
            <h1 class="text-xl font-semibold">{{ config('app.name') }}</h1>
            <p class="mt-1 text-sm text-stone-300">Control de actividades y asistencia</p>
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

            <label class="flex items-center gap-2 text-sm text-stone-600">
                <input type="checkbox" name="remember" class="size-4 rounded border-stone-300 text-brand-600">
                Mantener sesión iniciada
            </label>

            <button class="btn btn-primary w-full">Entrar</button>
        </form>
    </div>
</body>
</html>
