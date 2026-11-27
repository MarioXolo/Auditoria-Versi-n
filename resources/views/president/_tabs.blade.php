<nav class="mb-4 inline-flex rounded-lg border border-slate-200 bg-white p-1 text-sm print:hidden" aria-label="Vista">
    <a href="{{ route('president.index') }}" @class(['rounded-md px-4 py-1.5 font-medium', 'bg-brand-600 text-white' => request()->routeIs('president.index'), 'text-slate-600 hover:bg-slate-100' => ! request()->routeIs('president.index')])>Lista</a>
    <a href="{{ route('president.calendar') }}" @class(['rounded-md px-4 py-1.5 font-medium', 'bg-brand-600 text-white' => request()->routeIs('president.calendar'), 'text-slate-600 hover:bg-slate-100' => ! request()->routeIs('president.calendar')])>Calendario</a>
</nav>
