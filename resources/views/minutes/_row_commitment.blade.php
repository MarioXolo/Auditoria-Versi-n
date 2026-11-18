<tr data-row class="align-top">
    <td class="p-1 pt-3 text-sm font-semibold text-slate-400 tabular-nums" data-num></td>
    <td class="p-1"><textarea name="compromisos[{{ $i }}][actividad]" rows="1" class="input min-w-60 py-1.5" aria-label="Actividad / Tarea específica">{{ $c['actividad'] ?? '' }}</textarea></td>
    <td class="p-1"><input name="compromisos[{{ $i }}][responsable]" value="{{ $c['responsable'] ?? '' }}" maxlength="200" class="input min-w-36 py-1.5" aria-label="Responsable"></td>
    <td class="p-1"><input name="compromisos[{{ $i }}][fecha_limite]" value="{{ $c['fecha_limite'] ?? '' }}" maxlength="100" class="input w-36 py-1.5" placeholder="dd/mm/aaaa" aria-label="Fecha límite"></td>
    <td class="p-1">
        <select name="compromisos[{{ $i }}][estatus]" class="input py-1.5" aria-label="Estatus">
            <option value="">—</option>
            @foreach ($estatus as $e)
                <option value="{{ $e }}" @selected(($c['estatus'] ?? '') === $e)>{{ $e }}</option>
            @endforeach
        </select>
    </td>
    <td class="p-1"><button type="button" class="mt-1.5 px-1 text-slate-400 hover:text-rose-600" data-remove aria-label="Quitar compromiso">✕</button></td>
</tr>
