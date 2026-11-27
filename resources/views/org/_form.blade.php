{{-- Formulario de una persona del organigrama ($m puede ser nuevo) --}}
@php $blocked = $m->exists ? \App\Models\OrgMember::descendantIds($m->id) : []; @endphp
<div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
    <div>
        <label class="label" for="nombre{{ $m->id }}">Nombre *</label>
        <input id="nombre{{ $m->id }}" name="nombre" value="{{ $m->nombre }}" required maxlength="150" class="input">
    </div>
    <div>
        <label class="label" for="puesto{{ $m->id }}">Puesto *</label>
        <input id="puesto{{ $m->id }}" name="puesto" value="{{ $m->puesto }}" required maxlength="150" class="input" placeholder="Coordinación territorial">
    </div>
    <div>
        <label class="label" for="jefe{{ $m->id }}">A quién reporta</label>
        <select id="jefe{{ $m->id }}" name="reporta_a_id" class="input">
            <option value="">— Nadie (está arriba) —</option>
            @foreach ($members as $o)
                @continue(in_array($o->id, $blocked, true))
                <option value="{{ $o->id }}" @selected($m->reporta_a_id === $o->id)>{{ $o->nombre }} · {{ $o->puesto }}</option>
            @endforeach
        </select>
    </div>
    <div>
        <label class="label" for="team{{ $m->id }}">Equipo</label>
        <select id="team{{ $m->id }}" name="team_id" class="input">
            <option value="">—</option>
            @foreach ($teams as $t)<option value="{{ $t->id }}" @selected($m->team_id === $t->id)>{{ $t->name }}</option>@endforeach
        </select>
    </div>
    <div>
        <label class="label" for="del{{ $m->id }}">Delegación</label>
        <input id="del{{ $m->id }}" name="delegacion" value="{{ $m->delegacion }}" maxlength="120" class="input">
    </div>
    <div>
        <label class="label" for="sec{{ $m->id }}">Sección</label>
        <select id="sec{{ $m->id }}" name="section_id" class="input">
            <option value="">—</option>
            @foreach ($sections as $s)<option value="{{ $s->id }}" @selected($m->section_id === $s->id)>{{ $s->label() }}</option>@endforeach
        </select>
    </div>
    <div>
        <label class="label" for="tel{{ $m->id }}">Teléfono</label>
        <input id="tel{{ $m->id }}" name="telefono" value="{{ $m->telefono }}" maxlength="30" class="input">
    </div>
    <div>
        <label class="label" for="user{{ $m->id }}">Cuenta en el sistema</label>
        <select id="user{{ $m->id }}" name="user_id" class="input">
            <option value="">— Sin cuenta —</option>
            @foreach ($users as $u)
                @continue(in_array($u->id, $linkedUserIds, true) && $u->id !== $m->user_id)
                <option value="{{ $u->id }}" @selected($m->user_id === $u->id)>{{ $u->name }}{{ $u->active ? '' : ' (inactivo)' }}</option>
            @endforeach
        </select>
        <p class="mt-1 text-xs text-slate-500">Su rol y perfil se toman de su usuario.</p>
    </div>
    <div>
        <label class="label" for="orden{{ $m->id }}">Orden</label>
        <input id="orden{{ $m->id }}" type="number" min="0" max="999" name="orden" value="{{ $m->orden ?? 0 }}" class="input">
        <p class="mt-1 text-xs text-slate-500">Entre compañeros del mismo jefe, el menor va primero.</p>
    </div>
    <div class="sm:col-span-2 lg:col-span-3">
        <label class="label" for="notas{{ $m->id }}">Notas</label>
        <input id="notas{{ $m->id }}" name="notas" value="{{ $m->notas }}" maxlength="500" class="input">
    </div>
    <label class="inline-flex items-center gap-2 rounded-lg border border-rose-200 bg-rose-50 px-3 py-2 text-sm font-medium text-rose-900 sm:col-span-2 lg:col-span-3">
        <input type="checkbox" name="servidor_publico" value="1" class="size-4 rounded border-rose-300" @checked($m->servidor_publico)>
        ¿Es servidor público? (se mostrará una alerta)
    </label>
</div>
