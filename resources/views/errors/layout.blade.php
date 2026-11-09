<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#10302c">
    <title>@yield('title') · {{ config('app.name') }}</title>
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=instrument-sans:400,500,600,700" rel="stylesheet">
    @vite(['resources/css/app.css'])
</head>
<body class="grid min-h-screen place-items-center bg-stone-100 px-4 py-10 font-sans text-stone-900 antialiased">
    <div class="card w-full max-w-md p-8 text-center">
        <span class="mx-auto mb-5 grid size-12 place-items-center rounded-xl bg-brand-900 text-lg font-bold text-white">AC</span>
        <p class="text-sm font-semibold tracking-wide text-brand-600">Error @yield('code')</p>
        <h1 class="mt-1 text-xl font-semibold">@yield('title')</h1>
        <p class="mt-2 text-sm text-stone-600">@yield('message')</p>
        <a href="{{ url('/') }}" class="btn btn-primary mt-6">Volver al inicio</a>
    </div>
</body>
</html>
