<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#1d1d1b">
    <title>@yield('title', 'Panel') · {{ config('app.name') }}</title>
    <link rel="icon" href="{{ asset('favicon.svg') }}" type="image/svg+xml">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Source+Serif+4:ital,opsz,wght@0,8..60,600;1,8..60,600&display=swap" rel="stylesheet">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-slate-100 font-sans text-slate-900 antialiased">
@php
    $me = auth()->user();
    $nav = [];
    if ($me->seesEverything()) {
        $nav[] = ['route' => 'dashboard', 'match' => 'dashboard', 'label' => 'Dashboard', 'icon' => 'M3 13h8V3H3v10zm0 8h8v-6H3v6zm10 0h8V11h-8v10zm0-18v6h8V3h-8z'];
    }
    $nav[] = ['route' => 'activities.index', 'match' => 'activities.*', 'label' => 'Actividades', 'icon' => 'M9 11l3 3L22 4M21 12v7a2 2 0 01-2 2H5a2 2 0 01-2-2V5a2 2 0 012-2h11'];
    $nav[] = ['route' => 'calendar.index', 'match' => 'calendar.*', 'label' => 'Calendario', 'icon' => 'M8 3v3m8-3v3M4 9h16M5 5h14a1 1 0 011 1v13a1 1 0 01-1 1H5a1 1 0 01-1-1V6a1 1 0 011-1zm3 8h2m3 0h2m-7 3h2'];
    $nav[] = ['route' => 'attendance.index', 'match' => 'attendance.*', 'label' => 'Asistencia', 'icon' => 'M12 6v6l4 2m6-2a10 10 0 11-20 0 10 10 0 0120 0z'];
    if ($me->seesEverything()) {
        $nav[] = ['route' => 'users.index', 'match' => 'users.*', 'label' => 'Usuarios', 'icon' => 'M17 21v-2a4 4 0 00-4-4H5a4 4 0 00-4 4v2m22 0v-2a4 4 0 00-3-3.87M16 3.13a4 4 0 010 7.75M13 7a4 4 0 11-8 0 4 4 0 018 0z'];
        $nav[] = ['route' => 'teams.index', 'match' => 'teams.*', 'label' => 'Equipos', 'icon' => 'M3 7h18M3 12h18M3 17h18'];
        $nav[] = ['route' => 'catalogs.index', 'match' => 'catalogs.*', 'label' => 'Catálogos', 'icon' => 'M4 6a2 2 0 012-2h3l2 2h7a2 2 0 012 2v10a2 2 0 01-2 2H6a2 2 0 01-2-2V6zm4 6h8m-8 4h5'];
    }
@endphp

<div class="lg:flex">
    {{-- Barra lateral (escritorio) / barra superior (móvil) --}}
    <aside class="bg-ink text-white print:hidden lg:sticky lg:top-0 lg:flex lg:h-screen lg:w-64 lg:shrink-0 lg:flex-col">
        <div class="flex items-center justify-between px-4 py-4 lg:px-5 lg:py-6">
            <a href="{{ route('home') }}" class="flex items-center gap-2">
                <img src="{{ asset('images/logo.svg') }}" alt="WeAdvise" class="h-7 w-auto">
                <span class="text-sm font-semibold leading-tight">{{ config('app.name') }}</span>
            </a>
            <label for="nav-toggle" class="cursor-pointer rounded-md p-2 hover:bg-white/10 lg:hidden" aria-label="Abrir menú">
                <svg class="size-6" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" d="M4 6h16M4 12h16M4 18h16"/></svg>
            </label>
        </div>

        <input type="checkbox" id="nav-toggle" class="peer hidden">
        <nav class="hidden flex-1 flex-col gap-1 px-3 pb-4 peer-checked:flex lg:flex">
            @foreach ($nav as $item)
                <a href="{{ route($item['route']) }}" @class(['nav-link', 'nav-link-active' => request()->routeIs($item['match'])])>
                    <svg class="size-5 shrink-0" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="{{ $item['icon'] }}"/></svg>
                    {{ $item['label'] }}
                </a>
            @endforeach

            <div class="mt-4 border-t border-white/10 pt-4 lg:mt-auto">
                <a href="{{ route('profile.edit') }}" class="flex items-center gap-3 rounded-lg px-3 py-2 hover:bg-white/10">
                    <span class="grid size-8 place-items-center rounded-full bg-white/15 text-xs font-semibold">{{ $me->initials() }}</span>
                    <span class="min-w-0">
                        <span class="block truncate text-sm font-medium">{{ $me->name }}</span>
                        <span class="block text-xs text-slate-300">{{ $me->role->label() }}</span>
                    </span>
                </a>
                <form method="POST" action="{{ route('logout') }}" class="mt-1">
                    @csrf
                    <button class="nav-link w-full">
                        <svg class="size-5" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"/></svg>
                        Cerrar sesión
                    </button>
                </form>
            </div>
        </nav>
    </aside>

    <main class="min-w-0 flex-1 px-4 py-6 sm:px-6 lg:px-10 lg:py-8 print:p-0">
        <div class="mx-auto max-w-7xl print:max-w-none">
            @hasSection('header')
                <div class="mb-6 flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between print:hidden">
                    @yield('header')
                </div>
            @endif

            @if (session('status'))
                <div class="mb-5 rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800 print:hidden">
                    {{ session('status') }}
                </div>
            @endif

            @if ($errors->any())
                <div class="mb-5 rounded-lg border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-800">
                    <ul class="list-inside list-disc space-y-0.5">
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            @yield('content')
        </div>
    </main>
</div>
@stack('scripts')
</body>
</html>
