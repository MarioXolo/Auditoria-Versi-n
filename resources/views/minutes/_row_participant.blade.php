<tr data-row class="align-top">
    <td class="p-1"><input name="participantes[{{ $i }}][nombre]" value="{{ $p['nombre'] ?? '' }}" maxlength="150" class="input min-w-44 py-1.5" aria-label="Nombre y apellido" placeholder="Nombre y apellido"></td>
    <td class="p-1"><input name="participantes[{{ $i }}][puesto]" value="{{ $p['puesto'] ?? '' }}" maxlength="150" class="input min-w-40 py-1.5" aria-label="Puesto / Área"></td>
    <td class="p-1 text-center">
        <input type="hidden" name="participantes[{{ $i }}][asistio]" value="0">
        <input type="checkbox" name="participantes[{{ $i }}][asistio]" value="1" @checked($p['asistio'] ?? true) class="mt-2 size-4 rounded border-slate-300" aria-label="Asistió">
    </td>
    <td class="p-1">
        <select name="participantes[{{ $i }}][modalidad]" class="input py-1.5" aria-label="Modalidad">
            <option value="">—</option>
            @foreach (\App\Models\Minute::MODALIDADES as $m)
                <option value="{{ $m }}" @selected(($p['modalidad'] ?? '') === $m)>{{ $m }}</option>
            @endforeach
        </select>
    </td>
    <td class="p-1"><button type="button" class="mt-1.5 px-1 text-slate-400 hover:text-rose-600" data-remove aria-label="Quitar participante">✕</button></td>
</tr>
