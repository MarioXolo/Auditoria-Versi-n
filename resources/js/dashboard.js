import {
    Chart, BarController, LineController, DoughnutController, BarElement, LineElement, PointElement, ArcElement,
    CategoryScale, LinearScale, Tooltip, Legend, Filler,
} from 'chart.js';

Chart.register(BarController, LineController, DoughnutController, BarElement, LineElement, PointElement, ArcElement,
    CategoryScale, LinearScale, Tooltip, Legend, Filler);

const C = {
    brand: '#2f7f77',
    brandSoft: 'rgba(47,127,119,.15)',
    ink: '#10302c',
    amber: '#d97706',
    sky: '#0284c7',
    green: '#059669',
    stone: '#a8a29e',
    rose: '#e11d48',
    grid: 'rgba(120,113,108,.15)',
    text: '#57534e',
};

Chart.defaults.font.family = "'Instrument Sans', ui-sans-serif, system-ui, sans-serif";
Chart.defaults.font.size = 11;
Chart.defaults.color = C.text;
Chart.defaults.plugins.legend.labels.boxWidth = 10;
Chart.defaults.plugins.legend.labels.boxHeight = 10;
Chart.defaults.plugins.tooltip.backgroundColor = C.ink;
Chart.defaults.maintainAspectRatio = false;
Chart.defaults.animation = window.matchMedia('(prefers-reduced-motion: reduce)').matches ? false : { duration: 400 };

const dataEl = document.getElementById('dashboard-data');
const data = dataEl ? JSON.parse(dataEl.textContent) : null;

/** Línea horizontal punteada con la meta. */
const goalLine = (value, label) => ({
    id: 'goal-' + label,
    afterDatasetsDraw(chart) {
        const { ctx, chartArea, scales } = chart;
        const y = scales.y.getPixelForValue(value);
        ctx.save();
        ctx.strokeStyle = C.rose;
        ctx.setLineDash([4, 4]);
        ctx.lineWidth = 1;
        ctx.beginPath();
        ctx.moveTo(chartArea.left, y);
        ctx.lineTo(chartArea.right, y);
        ctx.stroke();
        ctx.fillStyle = C.rose;
        ctx.font = '600 10px Instrument Sans, sans-serif';
        ctx.textAlign = 'left';
        ctx.fillText(label, chartArea.left + 4, y + 12);
        ctx.restore();
    },
});

const pctAxis = { min: 0, max: 100, ticks: { callback: (v) => v + '%', stepSize: 25 }, grid: { color: C.grid } };
const countAxis = { beginAtZero: true, ticks: { precision: 0 }, grid: { color: C.grid } };
const noGrid = { grid: { display: false } };

function make(id, config) {
    const el = document.getElementById(id);
    if (el) new Chart(el, config);
}

if (data) {
    make('chart-trend', {
        type: 'bar',
        data: {
            labels: data.trend.map((d) => d.label),
            datasets: [
                { label: 'Creadas', data: data.trend.map((d) => d.created), backgroundColor: C.brandSoft, borderColor: C.brand, borderWidth: 1, borderRadius: 3 },
                { label: 'Completadas', type: 'line', data: data.trend.map((d) => d.completed), borderColor: C.ink, backgroundColor: C.ink, tension: 0.3, pointRadius: 2.5 },
            ],
        },
        options: { scales: { y: countAxis, x: { ...noGrid, ticks: { maxRotation: 0, autoSkip: true, maxTicksLimit: 10 } } }, plugins: { legend: { position: 'bottom' } } },
    });

    make('chart-status', {
        type: 'doughnut',
        data: {
            labels: data.status.map((d) => d.label),
            datasets: [{ data: data.status.map((d) => d.value), backgroundColor: [C.amber, C.sky, C.green, C.stone], borderWidth: 2, borderColor: '#fff' }],
        },
        options: { cutout: '68%', plugins: { legend: { position: 'bottom' } } },
    });

    make('chart-attendance', {
        type: 'line',
        data: {
            labels: data.attendance.map((d) => d.label),
            datasets: [
                { label: 'Asistencia', data: data.attendance.map((d) => d.asistencia), borderColor: C.brand, backgroundColor: C.brandSoft, fill: true, tension: 0.3, pointRadius: 2 },
                { label: 'Puntualidad', data: data.attendance.map((d) => d.puntualidad), borderColor: C.amber, backgroundColor: C.amber, tension: 0.3, pointRadius: 2, spanGaps: true },
            ],
        },
        options: { scales: { y: pctAxis, x: { ...noGrid, ticks: { maxRotation: 0, autoSkip: true, maxTicksLimit: 8 } } }, plugins: { legend: { position: 'bottom' } } },
        plugins: [goalLine(data.goals.asistencia, 'Meta ' + data.goals.asistencia + '%')],
    });

    make('chart-teams', {
        type: 'bar',
        data: {
            labels: data.teams.map((t) => t.name),
            datasets: [
                { label: 'Cumplimiento', data: data.teams.map((t) => t.cumplimiento), backgroundColor: C.brand, borderRadius: 3 },
                { label: 'A tiempo', data: data.teams.map((t) => t.a_tiempo), backgroundColor: C.ink, borderRadius: 3 },
                { label: 'Asistencia', data: data.teams.map((t) => t.asistencia), backgroundColor: '#8fd3c8', borderRadius: 3 },
            ],
        },
        options: { scales: { y: pctAxis, x: noGrid }, plugins: { legend: { position: 'bottom' } } },
    });

    make('chart-priority', {
        type: 'bar',
        data: {
            labels: data.priority.map((p) => p.label),
            datasets: [
                { label: 'Total', data: data.priority.map((p) => p.total), backgroundColor: C.brandSoft, borderColor: C.brand, borderWidth: 1, borderRadius: 3 },
                { label: 'Vencidas', data: data.priority.map((p) => p.overdue), backgroundColor: C.rose, borderRadius: 3 },
            ],
        },
        options: { indexAxis: 'y', scales: { x: countAxis, y: noGrid }, plugins: { legend: { position: 'bottom' } } },
    });
}
