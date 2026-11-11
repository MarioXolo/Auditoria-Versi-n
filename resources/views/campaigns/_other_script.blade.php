{{-- «Otra (escribirla)…»: muestra el campo para escribir la comunidad --}}
@push('scripts')
<script>
    document.querySelectorAll('select[data-other]').forEach((select) => {
        const input = document.getElementById(select.dataset.other);
        const sync = () => {
            const other = select.value === 'otro';
            input.classList.toggle('hidden', !other);
            input.required = other;
            if (other) input.focus();
        };
        select.addEventListener('change', sync);
        input.required = select.value === 'otro';
    });
</script>
@endpush
