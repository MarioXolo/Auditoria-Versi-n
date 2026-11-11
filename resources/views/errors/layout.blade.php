<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#1d1d1b">
    <title>@yield('title') · {{ config('app.name') }}</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Source+Serif+4:ital,opsz,wght@0,8..60,600;1,8..60,600&display=swap" rel="stylesheet">
    @vite(['resources/css/app.css'])
</head>
<body class="grid min-h-screen place-items-center bg-slate-100 px-4 py-10 font-sans text-slate-900 antialiased">
    <div class="card w-full max-w-md p-8 text-center">
        <span class="mx-auto mb-5 grid size-12 place-items-center rounded-xl bg-ink text-lg font-bold text-white">AC</span>
        <p class="text-sm font-semibold tracking-wide text-brand-600">Error @yield('code')</p>
        <h1 class="mt-1 text-xl font-semibold">@yield('title')</h1>
        <p class="mt-2 text-sm text-slate-600">@yield('message')</p>
        <a href="{{ url('/') }}" class="btn btn-primary mt-6">Volver al inicio</a>
    </div>
</body>
</html>
