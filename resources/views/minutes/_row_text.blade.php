<li data-row class="flex items-start gap-2">
    <span class="w-7 shrink-0 pt-2 text-right text-sm font-semibold text-slate-400 tabular-nums" data-num></span>
    <textarea name="{{ $key }}[]" rows="1" class="input py-1.5" aria-label="Renglón" oninput="this.style.height='auto';this.style.height=this.scrollHeight+'px'">{{ $texto }}</textarea>
    <button type="button" class="px-1 pt-2 text-slate-400 hover:text-rose-600" data-remove aria-label="Quitar renglón">✕</button>
</li>
