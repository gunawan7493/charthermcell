@extends('layouts.app')

@section('title', 'CharThermCell — Monitoring TEG Biochar')
@section('body-class', 'page-dashboard')

@section('content')
<header class="site-header">
    <div class="brand">
        <div class="logo" aria-hidden="true">CTC</div>
        <div>
            <h1>CharThermCell</h1>
            <p>TEG Biochar Tempurung Kelapa · SMAN 1 Blitar</p>
        </div>
    </div>

    <div class="conn" data-role="connection" role="status" aria-live="polite">
        <span class="dot" data-role="dot"></span>
        <span data-role="connection-text">Menghubungkan…</span>
    </div>
</header>

<main>
    <section class="grid gauges" aria-label="Suhu">
        <article class="card gauge">
            <h2 class="gauge-title">Suhu Biochar</h2>
            <svg viewBox="0 0 140 140" role="img" aria-hidden="true" focusable="false">
                <circle cx="70" cy="70" r="54" fill="none" stroke="#1d2b45" stroke-width="12"/>
                <circle data-gauge="hot" cx="70" cy="70" r="54" fill="none" stroke="var(--hot)" stroke-width="12"
                        stroke-linecap="round" transform="rotate(-90 70 70)" stroke-dasharray="0 340"/>
            </svg>
            <div class="val" data-value="temperature_hot">--</div>
            <div class="unit">°C</div>
        </article>

        <article class="card gauge">
            <h2 class="gauge-title">Suhu Heatsink</h2>
            <svg viewBox="0 0 140 140" role="img" aria-hidden="true" focusable="false">
                <circle cx="70" cy="70" r="54" fill="none" stroke="#1d2b45" stroke-width="12"/>
                <circle data-gauge="cold" cx="70" cy="70" r="54" fill="none" stroke="var(--cold)" stroke-width="12"
                        stroke-linecap="round" transform="rotate(-90 70 70)" stroke-dasharray="0 340"/>
            </svg>
            <div class="val" data-value="temperature_cold">--</div>
            <div class="unit">°C</div>
        </article>

        <article class="card gauge">
            <h2 class="gauge-title">Delta T (ΔT)</h2>
            <svg viewBox="0 0 140 140" role="img" aria-hidden="true" focusable="false">
                <circle cx="70" cy="70" r="54" fill="none" stroke="#1d2b45" stroke-width="12"/>
                <circle data-gauge="delta" cx="70" cy="70" r="54" fill="none" stroke="var(--dt)" stroke-width="12"
                        stroke-linecap="round" transform="rotate(-90 70 70)" stroke-dasharray="0 340"/>
            </svg>
            <div class="val" data-value="delta_temperature">--</div>
            <div class="unit">°C</div>
        </article>
    </section>

    <section class="grid stats" aria-label="Kelistrikan">
        <article class="card stat">
            <h2>Tegangan TEG</h2>
            <div><span class="num" data-value="voltage">--</span> <span class="unit">V</span></div>
            <p class="sub">keluaran modul (open circuit / berbeban)</p>
        </article>
        <article class="card stat">
            <h2>Arus TEG</h2>
            <div><span class="num" data-value="current">--</span> <span class="unit">mA</span></div>
            <p class="sub">diukur INA219</p>
        </article>
        <article class="card stat">
            <h2>Daya Keluaran</h2>
            <div><span class="num" data-value="power">--</span> <span class="unit">mW</span></div>
            <p class="sub">P = V × I</p>
        </article>
    </section>

    <section class="card status-row">
        <div class="badge s0" data-role="status-badge">
            <span class="bdot" aria-hidden="true"></span>
            <span data-role="status-text">Menunggu data…</span>
        </div>
        <p class="meta">
            Diperbarui <span data-role="updated">—</span> · data tiap {{ intdiv($refreshMs, 1000) }} detik
        </p>
    </section>

    <p class="notice" data-role="notice" hidden></p>

    <section class="card chart-wrap" aria-label="Riwayat suhu">
        <h2 class="chart-title">Riwayat Suhu (5 menit terakhir)</h2>
        <canvas data-role="chart" aria-label="Grafik riwayat suhu sisi panas dan sisi dingin" role="img"></canvas>
        <div class="legend" aria-hidden="true">
            <span><i style="background:var(--hot)"></i>Sisi panas</span>
            <span><i style="background:var(--cold)"></i>Sisi dingin</span>
        </div>
    </section>

    @if ($controlsEnabled)
        <section class="controls" aria-label="Kontrol perangkat">
            <button type="button" class="primary" data-control="buzzer-on">🔕 Mute Buzzer</button>
            <button type="button" data-control="buzzer-off">🔔 Aktifkan Buzzer</button>
            <button type="button" data-control="calibrate">⟲ Rekalibrasi INA219</button>
        </section>
    @else
        <p class="controls-off">Kontrol buzzer &amp; rekalibrasi belum diaktifkan di server.</p>
    @endif

    <p class="foot">Data: Blynk Cloud · {{ $device }}</p>
</main>
@endsection
