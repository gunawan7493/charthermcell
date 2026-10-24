/**
 * CharThermCell dashboard.
 *
 * Polls the Laravel proxy every two seconds, renders gauges and a rolling
 * five minute chart. No external dependencies.
 */

const REFRESH_MS = 2000;
const MAX_POINTS = 150;
const CIRCUMFERENCE = 2 * Math.PI * 54;
const CSRF = document.querySelector('meta[name="csrf-token"]')?.content ?? '';

const el = (role) => document.querySelector(`[data-role="${role}"]`);
const valueNode = (key) => document.querySelector(`[data-value="${key}"]`);
const gaugeNode = (key) => document.querySelector(`[data-gauge="${key}"]`);

const ui = {
    dot: el('dot'),
    connection: el('connection-text'),
    badge: el('status-badge'),
    status: el('status-text'),
    updated: el('updated'),
    notice: el('notice'),
    chart: el('chart'),
};

const history = [];
let gaugeMax = 150;
let pollTimer = null;
let inFlight = null;

/* --------------------------------------------------------------- formatting */

const format = (value, digits) => {
    if (value === null || value === undefined || Number.isNaN(value)) {
        return '--';
    }

    return Number(value).toFixed(digits);
};

/* ------------------------------------------------------------------- gauges */

function setGauge(node, value) {
    if (!node) {
        return;
    }

    if (value === null || Number.isNaN(value)) {
        node.setAttribute('stroke-dasharray', `0 ${CIRCUMFERENCE}`);
        return;
    }

    const ratio = Math.max(0, Math.min(1, value / gaugeMax));

    node.setAttribute('stroke-dasharray', `${(ratio * CIRCUMFERENCE).toFixed(1)} ${CIRCUMFERENCE.toFixed(1)}`);
}

/* -------------------------------------------------------------------- chart */

function drawChart() {
    const canvas = ui.chart;

    if (!canvas) {
        return;
    }

    const ratio = window.devicePixelRatio || 1;
    const width = canvas.clientWidth;
    const height = canvas.clientHeight;

    canvas.width = width * ratio;
    canvas.height = height * ratio;

    const ctx = canvas.getContext('2d');

    ctx.setTransform(ratio, 0, 0, ratio, 0, 0);
    ctx.clearRect(0, 0, width, height);

    const padLeft = 34;
    const padRight = 8;
    const padTop = 10;
    const padBottom = 18;
    const chartWidth = width - padLeft - padRight;
    const chartHeight = height - padTop - padBottom;

    let min = Infinity;
    let max = -Infinity;

    for (const point of history) {
        if (point.hot !== null) {
            min = Math.min(min, point.hot);
            max = Math.max(max, point.hot);
        }

        if (point.cold !== null) {
            min = Math.min(min, point.cold);
            max = Math.max(max, point.cold);
        }
    }

    if (!Number.isFinite(min)) {
        min = 20;
        max = 80;
    }

    min = Math.floor(min - 2);
    max = Math.ceil(max + 2);

    if (max - min < 10) {
        max = min + 10;
    }

    ctx.strokeStyle = '#20304d';
    ctx.fillStyle = '#6f86ab';
    ctx.font = '10px sans-serif';
    ctx.lineWidth = 1;

    for (let step = 0; step <= 4; step += 1) {
        const y = padTop + (chartHeight * step) / 4;

        ctx.beginPath();
        ctx.moveTo(padLeft, y);
        ctx.lineTo(width - padRight, y);
        ctx.stroke();

        const label = max - ((max - min) * step) / 4;
        ctx.fillText(label.toFixed(0), 4, y + 3.5);
    }

    const plot = (key, color) => {
        ctx.beginPath();
        ctx.strokeStyle = color;
        ctx.lineWidth = 2;
        ctx.lineJoin = 'round';

        let started = false;

        history.forEach((point, index) => {
            if (point[key] === null) {
                return;
            }

            const x = padLeft + (history.length === 1 ? chartWidth : (chartWidth * index) / (MAX_POINTS - 1));
            const y = padTop + chartHeight * (1 - (point[key] - min) / (max - min));

            if (!started) {
                ctx.moveTo(x, y);
                started = true;
            } else {
                ctx.lineTo(x, y);
            }
        });

        ctx.stroke();
    };

    plot('hot', '#ff922b');
    plot('cold', '#4dabf7');
}

/* ------------------------------------------------------------------ render */

function showNotice(message) {
    if (!ui.notice) {
        return;
    }

    if (!message) {
        ui.notice.hidden = true;
        ui.notice.textContent = '';
        return;
    }

    ui.notice.hidden = false;
    ui.notice.textContent = message;
}

function render(payload) {
    gaugeMax = payload.gauge_max ?? gaugeMax;

    const readings = payload.readings ?? {};
    const hot = readings.temperature_hot ?? null;
    const cold = readings.temperature_cold ?? null;
    const delta = readings.delta_temperature ?? null;

    valueNode('temperature_hot').textContent = format(hot, 1);
    valueNode('temperature_cold').textContent = format(cold, 1);
    valueNode('delta_temperature').textContent = format(delta, 1);
    valueNode('voltage').textContent = format(readings.voltage, 3);
    valueNode('current').textContent = format(readings.current, 2);
    valueNode('power').textContent = format(readings.power, 2);

    setGauge(gaugeNode('hot'), hot);
    setGauge(gaugeNode('cold'), cold);
    setGauge(gaugeNode('delta'), delta);

    const status = payload.status ?? {};
    const code = [0, 1, 2].includes(status.code) ? status.code : 0;

    ui.badge.className = `badge s${code}`;
    ui.status.textContent = status.label ?? 'Menunggu data…';

    const online = Boolean(payload.online);

    ui.dot.className = `dot ${online ? 'on' : 'off'}`;
    ui.connection.textContent = online ? 'Device online' : 'Device offline';

    ui.updated.textContent = new Date(payload.updated_at ?? Date.now()).toLocaleTimeString('id-ID');
    showNotice(payload.error);

    history.push({ hot, cold });

    if (history.length > MAX_POINTS) {
        history.shift();
    }

    drawChart();
}

function renderOffline(error) {
    const offline = {
        online: false,
        readings: {},
        status: { code: 0, label: 'Data tidak tersedia' },
        updated_at: new Date().toISOString(),
        gauge_max: gaugeMax,
        error: error ?? 'Gagal mengambil data dari server.',
    };

    render(offline);
}

/* -------------------------------------------------------------------- fetch */

async function fetchTelemetry() {
    if (inFlight) {
        inFlight.abort();
    }

    inFlight = new AbortController();
    const timeout = setTimeout(() => inFlight.abort(), 8000);

    try {
        const response = await fetch('/api/telemetry', {
            headers: { Accept: 'application/json' },
            cache: 'no-store',
            signal: inFlight.signal,
        });

        if (!response.ok) {
            throw new Error(`HTTP ${response.status}`);
        }

        render(await response.json());
    } catch (error) {
        if (error.name !== 'AbortError') {
            renderOffline('Tidak dapat menghubungi server. Mencoba lagi…');
        }
    } finally {
        clearTimeout(timeout);
        inFlight = null;
    }
}

function schedule() {
    clearInterval(pollTimer);
    pollTimer = setInterval(fetchTelemetry, REFRESH_MS);
}

/* ----------------------------------------------------------------- controls */

async function sendControl(control, body) {
    const buttons = document.querySelectorAll('[data-control]');
    buttons.forEach((button) => {
        button.disabled = true;
    });

    try {
        const response = await fetch(control, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                Accept: 'application/json',
                'X-CSRF-TOKEN': CSRF,
            },
            body: JSON.stringify(body ?? {}),
        });

        const payload = await response.json().catch(() => ({}));

        if (!response.ok) {
            throw new Error(payload.message ?? `Perintah ditolak (HTTP ${response.status}).`);
        }

        showNotice('');
        await fetchTelemetry();
    } catch (error) {
        showNotice(error.message);
    } finally {
        buttons.forEach((button) => {
            button.disabled = false;
        });
    }
}

function bindControls() {
    document.querySelector('[data-control="buzzer-on"]')?.addEventListener('click', () => {
        sendControl('/api/controls/buzzer', { enabled: true });
    });

    document.querySelector('[data-control="buzzer-off"]')?.addEventListener('click', () => {
        sendControl('/api/controls/buzzer', { enabled: false });
    });

    document.querySelector('[data-control="calibrate"]')?.addEventListener('click', () => {
        sendControl('/api/controls/calibrate');
    });
}

/* --------------------------------------------------------------------- boot */

document.addEventListener('visibilitychange', () => {
    if (document.hidden) {
        clearInterval(pollTimer);
        return;
    }

    fetchTelemetry();
    schedule();
});

window.addEventListener('resize', drawChart);

bindControls();
fetchTelemetry();
schedule();
