// Mapas de geolocalización de actividades (Leaflet + OpenStreetMap)
import L from 'leaflet';
import 'leaflet/dist/leaflet.css';

const BRAND = '#1d4ed8';
const DEFAULT_CENTER = [20.3739, -100.2732]; // Huimilpan, Qro.
const tiles = () => L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
    maxZoom: 19,
    attribution: '&copy; colaboradores de OpenStreetMap',
});
// Puntos dibujados (sin imágenes de marcador, que fallan al empaquetar)
const dot = (latlng, color, label) => L.circleMarker(latlng, { radius: 8, color: '#fff', weight: 2, fillColor: color, fillOpacity: 1 }).bindTooltip(label);

// 1. Selector del lugar al crear o editar una actividad
document.querySelectorAll('[data-map-picker]').forEach((box) => {
    const toggle = document.getElementById(box.dataset.toggle);
    const panel = box.querySelector('[data-map-panel]');
    const lat = box.querySelector('[name="destino_lat"]');
    const lng = box.querySelector('[name="destino_lng"]');
    const radius = box.querySelector('[name="destino_radio"]');
    const hint = box.querySelector('[data-map-hint]');
    let map, marker, circle;

    const place = (latlng, zoom) => {
        lat.value = latlng.lat.toFixed(7);
        lng.value = latlng.lng.toFixed(7);
        marker ? marker.setLatLng(latlng) : (marker = dot(latlng, BRAND, 'Lugar al que hay que acudir').addTo(map));
        circle ? circle.setLatLng(latlng) : (circle = L.circle(latlng, { radius: +radius.value || 300, color: BRAND, weight: 1, fillOpacity: 0.12 }).addTo(map));
        if (zoom) map.setView(latlng, zoom);
        hint.textContent = `Lugar marcado: ${lat.value}, ${lng.value}`;
    };

    const init = () => {
        if (map) return setTimeout(() => map.invalidateSize(), 50);
        const has = lat.value && lng.value;
        map = L.map(box.querySelector('[data-map]')).setView(has ? [+lat.value, +lng.value] : DEFAULT_CENTER, has ? 15 : 12);
        tiles().addTo(map);
        if (has) place(L.latLng(+lat.value, +lng.value));
        if (box.dataset.locked === '1') return;
        // Gerentes y administradores marcan el lugar tocando el mapa; el encargado solo con su GPS
        if (box.dataset.manual === '1') map.on('click', (e) => place(e.latlng));
        box.querySelector('[data-map-here]')?.addEventListener('click', () => {
            if (!navigator.geolocation) return (hint.textContent = 'Este navegador no da la ubicación.');
            hint.textContent = 'Buscando tu ubicación…';
            navigator.geolocation.getCurrentPosition(
                (p) => {
                    const acc = box.querySelector('[name="destino_precision"]');
                    if (acc) acc.value = Math.round(p.coords.accuracy);
                    place(L.latLng(p.coords.latitude, p.coords.longitude), 16);
                    hint.textContent += ` · precisión ±${Math.round(p.coords.accuracy)} m`;
                },
                (err) => (hint.textContent = err.code === 1
                    ? 'Negaste el acceso a la ubicación. Actívalo en los permisos del navegador para este sitio.'
                    : 'No se pudo obtener tu ubicación. Revisa que el GPS esté encendido e inténtalo de nuevo.'),
                { enableHighAccuracy: true, timeout: 20000, maximumAge: 0 },
            );
        });
    };

    radius?.addEventListener('input', () => circle?.setRadius(+radius.value || 300));
    const sync = () => {
        panel.classList.toggle('hidden', !toggle.checked);
        if (toggle.checked) init();
    };
    toggle.addEventListener('change', sync);
    sync();
});

// 2. Mapa de la actividad: destino, radio y dónde se validó
document.querySelectorAll('[data-map-view]').forEach((el) => {
    const d = el.dataset;
    const dest = L.latLng(+d.destLat, +d.destLng);
    const map = L.map(el, { scrollWheelZoom: false }).setView(dest, 15);
    tiles().addTo(map);
    L.circle(dest, { radius: +d.radius, color: BRAND, weight: 1, fillOpacity: 0.12 }).addTo(map);
    dot(dest, BRAND, 'Lugar al que había que acudir').addTo(map);
    // Al cargar en el celular el contenedor cambia de tamaño: se recalcula para que el lugar quede centrado
    const recenter = () => {
        map.invalidateSize();
        d.meLat ? map.fitBounds(L.latLngBounds([dest, L.latLng(+d.meLat, +d.meLng)]).pad(0.3), { maxZoom: 17 }) : map.setView(dest, 15);
    };
    setTimeout(recenter, 300);
    window.addEventListener('load', recenter, { once: true });
    if (d.meLat) {
        const me = L.latLng(+d.meLat, +d.meLng);
        const ok = d.ok === '1';
        dot(me, ok ? '#059669' : '#e11d48', 'Donde se validó la ubicación').addTo(map);
        L.polyline([dest, me], { color: ok ? '#059669' : '#e11d48', weight: 2, dashArray: '4 6' }).addTo(map);
        map.fitBounds(L.latLngBounds([dest, me]).pad(0.3), { maxZoom: 17 });
    }
});

// 3. Botón «Validar mi ubicación»: toma el GPS del celular en ese momento
document.querySelectorAll('[data-geo-validate]').forEach((form) => {
    const btn = form.querySelector('button');
    const msg = form.querySelector('[data-geo-msg]');
    form.addEventListener('submit', (e) => {
        if (form.dataset.ready === '1') return;
        e.preventDefault();
        if (!navigator.geolocation) return (msg.textContent = 'Este navegador no puede dar la ubicación. Abre el sistema desde el celular.');
        btn.disabled = true;
        msg.textContent = 'Obteniendo tu ubicación… permite el acceso si te lo pide.';
        navigator.geolocation.getCurrentPosition(
            (p) => {
                form.querySelector('[name="lat"]').value = p.coords.latitude;
                form.querySelector('[name="lng"]').value = p.coords.longitude;
                form.querySelector('[name="precision"]').value = p.coords.accuracy;
                form.dataset.ready = '1';
                form.submit();
            },
            (err) => {
                btn.disabled = false;
                msg.textContent = err.code === 1
                    ? 'Negaste el acceso a la ubicación. Actívalo en los permisos del navegador para este sitio.'
                    : 'No se pudo obtener la ubicación. Revisa que el GPS esté encendido e inténtalo de nuevo.';
            },
            { enableHighAccuracy: true, timeout: 20000, maximumAge: 0 },
        );
    });
});

// 4. Punto en el mapa de un registro de Estrategia (bardas, lonas): se toca el mapa o se usa el GPS
document.querySelectorAll('[data-map-point]').forEach((box) => {
    const lat = box.querySelector('[name="lat"]');
    const lng = box.querySelector('[name="lng"]');
    const hint = box.querySelector('[data-map-hint]');
    const has = lat.value && lng.value;
    const map = L.map(box.querySelector('[data-map]')).setView(has ? [+lat.value, +lng.value] : DEFAULT_CENTER, has ? 17 : 12);
    tiles().addTo(map);
    let marker = null;
    const place = (latlng, zoom) => {
        lat.value = latlng.lat.toFixed(7);
        lng.value = latlng.lng.toFixed(7);
        marker ? marker.setLatLng(latlng) : (marker = dot(latlng, BRAND, 'Ubicación').addTo(map));
        if (zoom) map.setView(latlng, zoom);
        hint.textContent = `Punto marcado: ${lat.value}, ${lng.value}`;
    };
    if (has) place(L.latLng(+lat.value, +lng.value));
    setTimeout(() => map.invalidateSize(), 300);
    map.on('click', (e) => place(e.latlng));
    box.querySelector('[data-map-clear]')?.addEventListener('click', () => {
        lat.value = '';
        lng.value = '';
        if (marker) { map.removeLayer(marker); marker = null; }
        hint.textContent = 'Sin punto. Toca el mapa para marcar dónde está.';
    });
    box.querySelector('[data-map-here]')?.addEventListener('click', () => {
        if (!navigator.geolocation) return (hint.textContent = 'Este navegador no da la ubicación.');
        hint.textContent = 'Buscando tu ubicación…';
        navigator.geolocation.getCurrentPosition(
            (p) => place(L.latLng(p.coords.latitude, p.coords.longitude), 18),
            () => (hint.textContent = 'No se pudo obtener tu ubicación. Toca el mapa para marcar el punto.'),
            { enableHighAccuracy: true, timeout: 20000, maximumAge: 0 },
        );
    });
});
