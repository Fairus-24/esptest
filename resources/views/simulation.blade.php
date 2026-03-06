<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Simulasi Aplikasi IoT</title>
    <link rel="icon" type="image/svg+xml" href="{{ asset('project-favicon.svg') }}?v=20260227">
    <link rel="icon" type="image/png" sizes="32x32" href="{{ asset('project-favicon.png') }}?v=20260227">
    <link rel="icon" type="image/x-icon" href="{{ asset('favicon.ico') }}?v=20260227">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Manrope:wght@400;500;600;700;800&family=Space+Grotesk:wght@500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --primary: #1f4fd7;
            --secondary: #0e8c63;
            --danger: #dc2626;
            --warning: #f59e0b;
            --text-dark: #0f172a;
            --text-muted: #475569;
            --surface: rgba(255, 255, 255, 0.96);
            --border: rgba(15, 23, 42, 0.12);
            --shadow: 0 18px 38px rgba(2, 6, 23, 0.12);
        }

        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            font-family: 'Manrope', 'Segoe UI', sans-serif;
            color: var(--text-dark);
            background: linear-gradient(140deg, #1f4fd7 0%, #0e8c63 100%);
            background-attachment: fixed;
            min-height: 100vh;
            padding: 20px 16px 30px;
        }

        .wrap {
            max-width: 1300px;
            margin: 0 auto;
            display: grid;
            gap: 16px;
        }

        .page-nav {
            display: flex;
            justify-content: flex-start;
        }

        .page-nav .btn.link {
            background: rgba(248, 250, 252, 0.95);
            color: #1e3a8a;
            border: 1px solid rgba(30, 58, 138, 0.2);
        }

        .hero {
            background: linear-gradient(140deg, rgba(15, 23, 42, 0.88), rgba(30, 64, 175, 0.82));
            color: #f8fafc;
            border-radius: 18px;
            border: 1px solid rgba(148, 163, 184, 0.28);
            box-shadow: 0 20px 40px rgba(2, 6, 23, 0.24);
            padding: 22px;
        }

        .hero h1 {
            margin: 0 0 8px;
            font-family: 'Space Grotesk', 'Manrope', sans-serif;
            font-size: clamp(1.4rem, 1.2rem + 1vw, 2rem);
            letter-spacing: 0.2px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .hero p {
            margin: 0;
            opacity: 0.96;
            line-height: 1.65;
            font-size: 0.95rem;
        }

        .surface {
            background: var(--surface);
            border-radius: 16px;
            border: 1px solid var(--border);
            box-shadow: var(--shadow);
            padding: 16px;
        }

        .surface.surface-dashboard {
            width: min(1680px, calc(100vw - 32px));
            max-width: none;
            justify-self: center;
            min-width: 0;
        }

        .toolbar {
            display: grid;
            grid-template-columns: repeat(4, minmax(0, 1fr));
            gap: 12px;
        }

        .field {
            display: grid;
            gap: 6px;
        }

        .field label {
            font-size: 0.8rem;
            color: var(--text-muted);
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.35px;
        }

        .field input,
        .field select {
            border: 1px solid rgba(15, 23, 42, 0.2);
            border-radius: 10px;
            padding: 10px 11px;
            font-size: 0.95rem;
            font-weight: 600;
            background: #fff;
            color: #0f172a;
        }

        .field input:disabled,
        .field select:disabled {
            background: #e2e8f0;
            color: #64748b;
            cursor: not-allowed;
        }

        .check-wrap {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            margin-top: 10px;
            font-size: 0.88rem;
            font-weight: 600;
            color: #1f2937;
        }

        .check-wrap input:disabled {
            cursor: not-allowed;
        }

        .actions {
            margin-top: 12px;
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
        }

        .btn {
            border: none;
            border-radius: 10px;
            padding: 10px 13px;
            font-size: 0.88rem;
            font-weight: 700;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }

        .btn.primary {
            background: #1f4fd7;
            color: #fff;
        }

        .btn.warning {
            background: #f59e0b;
            color: #fff;
        }

        .btn.danger {
            background: var(--danger);
            color: #fff;
        }

        .btn.dark {
            background: #0f172a;
            color: #fff;
        }

        .btn.link {
            text-decoration: none;
            background: rgba(30, 64, 175, 0.12);
            color: #1e40af;
        }

        .status-row {
            margin-top: 10px;
            font-size: 0.88rem;
            font-weight: 700;
            color: #1f2937;
            min-height: 22px;
        }

        .status-row.ok {
            color: #047857;
        }

        .status-row.error {
            color: #b91c1c;
        }

        .stats {
            display: grid;
            grid-template-columns: repeat(6, minmax(0, 1fr));
            gap: 10px;
            margin-top: 14px;
        }

        .stat {
            border: 1px solid rgba(15, 23, 42, 0.1);
            border-radius: 12px;
            padding: 10px 11px;
            background: #f8fafc;
        }

        .stat .label {
            font-size: 0.75rem;
            text-transform: uppercase;
            color: #64748b;
            font-weight: 800;
            letter-spacing: 0.3px;
        }

        .stat .value {
            margin-top: 5px;
            font-size: 1.02rem;
            font-weight: 800;
            color: #0f172a;
        }

        .meta {
            margin-top: 10px;
            font-size: 0.82rem;
            color: #334155;
            display: flex;
            gap: 12px;
            flex-wrap: wrap;
        }

        .log-box {
            margin-top: 10px;
            border-radius: 10px;
            border: 1px solid rgba(15, 23, 42, 0.12);
            background: #0f172a;
            color: #e2e8f0;
            font-family: Consolas, monospace;
            padding: 9px 10px;
            min-height: 120px;
            max-height: 220px;
            overflow: auto;
            font-size: 0.76rem;
            line-height: 1.45;
        }

        .frame-wrap {
            --dashboard-viewport-width: 1440px;
            --dashboard-viewport-height: 1024px;
            --dashboard-scale: 1;
            --dashboard-stage-width: var(--dashboard-viewport-width);
            --dashboard-stage-height: var(--dashboard-viewport-height);
            margin-top: 12px;
            border: 1px solid rgba(15, 23, 42, 0.16);
            border-radius: 14px;
            overflow: hidden;
            background: linear-gradient(145deg, #e2e8f0 0%, #cbd5e1 100%);
            display: flex;
            justify-content: center;
            align-items: flex-start;
            padding: 12px;
            min-width: 0;
        }

        .frame-stage {
            width: var(--dashboard-stage-width);
            height: var(--dashboard-stage-height);
            display: flex;
            justify-content: center;
            align-items: flex-start;
            flex: none;
        }

        .frame-wrap iframe {
            width: var(--dashboard-viewport-width);
            border: 0;
            height: var(--dashboard-viewport-height);
            min-height: 0;
            background: #fff;
            border-radius: 12px;
            box-shadow: 0 18px 35px rgba(2, 6, 23, 0.16);
            transform: scale(var(--dashboard-scale));
            transform-origin: top center;
            transition: transform 180ms ease, width 180ms ease, height 180ms ease;
            max-width: none;
            max-height: none;
            flex: none;
        }

        .frame-wrap[data-viewport="desktop"] iframe {
            width: var(--dashboard-viewport-width);
            height: var(--dashboard-viewport-height);
        }

        .frame-wrap[data-viewport="tablet"] iframe {
            width: var(--dashboard-viewport-width);
            height: var(--dashboard-viewport-height);
        }

        .frame-wrap[data-viewport="mobile"] iframe {
            width: var(--dashboard-viewport-width);
            height: var(--dashboard-viewport-height);
        }

        .surface-head {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            gap: 12px;
            flex-wrap: wrap;
        }

        .surface-head h2 {
            margin: 0 0 6px;
            font-size: 1.06rem;
            font-family: 'Space Grotesk', 'Manrope', sans-serif;
        }

        .surface-head p {
            margin: 0;
            color: #475569;
            font-size: 0.9rem;
        }

        .frame-tools {
            display: grid;
            gap: 6px;
            justify-items: end;
        }

        .viewport-label {
            font-size: 0.76rem;
            color: #334155;
            font-weight: 700;
        }

        .viewport-switch {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            border-radius: 999px;
            padding: 4px;
            background: #e2e8f0;
            border: 1px solid rgba(15, 23, 42, 0.12);
        }

        .viewport-btn {
            border: none;
            border-radius: 999px;
            min-width: 38px;
            min-height: 34px;
            padding: 7px 10px;
            background: transparent;
            color: #334155;
            cursor: pointer;
            font-size: 0.9rem;
            font-weight: 700;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 6px;
        }

        .viewport-btn:hover {
            background: rgba(148, 163, 184, 0.32);
        }

        .viewport-btn.is-active {
            background: #1f4fd7;
            color: #fff;
            box-shadow: 0 7px 16px rgba(31, 79, 215, 0.3);
        }

        .viewport-btn:focus-visible {
            outline: 2px solid #1f4fd7;
            outline-offset: 1px;
        }

        .calculator-toolbar {
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
            align-items: center;
            justify-content: flex-end;
        }

        .calculator-groups {
            margin-top: 14px;
            display: grid;
            gap: 14px;
        }

        .calculator-group {
            border: 1px solid rgba(15, 23, 42, 0.1);
            border-radius: 14px;
            background: linear-gradient(180deg, rgba(255, 255, 255, 0.96), rgba(248, 250, 252, 0.98));
            padding: 14px;
        }

        .calculator-group h3 {
            margin: 0 0 4px;
            font-size: 1rem;
            font-family: 'Space Grotesk', 'Manrope', sans-serif;
            color: #0f172a;
        }

        .calculator-group p {
            margin: 0;
            color: #475569;
            font-size: 0.86rem;
            line-height: 1.55;
        }

        .calculator-grid {
            margin-top: 12px;
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(260px, 1fr));
            gap: 12px;
        }

        .calculator-card {
            border-radius: 14px;
            border: 1px solid rgba(15, 23, 42, 0.12);
            background: #ffffff;
            padding: 13px;
            box-shadow: 0 12px 26px rgba(15, 23, 42, 0.08);
            border-left-width: 4px;
        }

        .calculator-card.tone-ok {
            border-left-color: #0e8c63;
        }

        .calculator-card.tone-warn {
            border-left-color: #f59e0b;
        }

        .calculator-card.tone-muted {
            border-left-color: #94a3b8;
        }

        .calculator-card.tone-info {
            border-left-color: #1f4fd7;
        }

        .calculator-card-top {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            gap: 10px;
        }

        .calculator-chip {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            border-radius: 999px;
            padding: 4px 9px;
            font-size: 0.72rem;
            font-weight: 800;
            letter-spacing: 0.45px;
            text-transform: uppercase;
            background: #e2e8f0;
            color: #334155;
        }

        .calculator-result {
            font-size: 1rem;
            font-weight: 800;
            color: #0f172a;
            text-align: right;
        }

        .calculator-card h4 {
            margin: 10px 0 6px;
            font-size: 0.98rem;
            color: #0f172a;
        }

        .calculator-formula {
            margin: 0;
            font-size: 0.82rem;
            color: #475569;
            line-height: 1.55;
        }

        .calculator-inputs {
            margin-top: 12px;
            display: grid;
            gap: 7px;
        }

        .calculator-input-row {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            gap: 10px;
            font-size: 0.81rem;
            padding-top: 7px;
            border-top: 1px dashed rgba(148, 163, 184, 0.45);
        }

        .calculator-input-row span {
            color: #475569;
        }

        .calculator-input-row strong {
            color: #0f172a;
            text-align: right;
            font-weight: 800;
        }

        .calculator-empty {
            margin-top: 10px;
            border-radius: 12px;
            border: 1px dashed rgba(148, 163, 184, 0.6);
            padding: 14px;
            color: #64748b;
            font-size: 0.9rem;
            text-align: center;
            background: rgba(248, 250, 252, 0.75);
        }

        @media (max-width: 1120px) {
            .toolbar {
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }

            .stats {
                grid-template-columns: repeat(3, minmax(0, 1fr));
            }
        }

        @media (max-width: 860px) {
            .toolbar {
                grid-template-columns: 1fr;
            }

            .stats {
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }

            .surface.surface-dashboard {
                width: 100%;
            }

            .surface-head {
                align-items: stretch;
            }

            .frame-tools {
                justify-items: start;
            }

            .calculator-toolbar {
                justify-content: flex-start;
            }
        }

        @media (max-width: 520px) {
            body {
                padding: 14px 10px 20px;
            }

            .stats {
                grid-template-columns: 1fr;
            }

            .actions {
                flex-direction: column;
            }

            .btn {
                justify-content: center;
            }

            .viewport-switch {
                width: 100%;
                justify-content: space-between;
            }

            .viewport-btn {
                flex: 1 1 0;
            }

            .frame-wrap {
                padding: 8px;
            }

            .calculator-card-top,
            .calculator-input-row {
                flex-direction: column;
            }

            .calculator-result,
            .calculator-input-row strong {
                text-align: left;
            }

        }
    </style>
</head>
<body>
    @php
        $basePath = rtrim(request()->getBaseUrl(), '/');
        $dashboardPath = $basePath !== '' ? $basePath . '/' : '/';
        $simulationDashboardPath = $dashboardPath . '?source=simulation&embedded=1';
        $simulationApiBase = ($basePath !== '' ? $basePath : '') . '/simulation';
        $dashboardCalculatorPath = ($basePath !== '' ? $basePath : '') . '/dashboard/calculator?source=real';
    @endphp
    <div class="wrap">
        <div class="page-nav">
            <a class="btn link" href="{{ $dashboardPath }}"><i class="fas fa-arrow-left"></i> Kembali ke Dashboard Utama</a>
        </div>

        <section class="hero">
            <h1><i class="fas fa-vial-circle-check"></i> Simulasi Keseluruhan Aplikasi</h1>
            <p>
                Halaman ini mensimulasikan alur aplikasi lengkap MQTT vs HTTP secara realtime
                (packet sequence, latency, daya, reliability, diagnostics, warning, dan chart)
                menggunakan jalur data simulasi terpisah dari data real produksi.
            </p>
        </section>

        <section class="surface">
            <div class="toolbar">
                <div class="field">
                    <label for="intervalSeconds">Interval Tick (detik)</label>
                    <input id="intervalSeconds" type="number" min="1" max="30" value="5">
                </div>
                <div class="field">
                    <label for="httpFailRate">HTTP Fail Rate (0-1)</label>
                    <input id="httpFailRate" type="number" step="0.01" min="0" max="1" value="0.08">
                </div>
                <div class="field">
                    <label for="mqttFailRate">MQTT Fail Rate (0-1)</label>
                    <input id="mqttFailRate" type="number" step="0.01" min="0" max="1" value="0.12">
                </div>
                <div class="field">
                    <label for="networkProfile">Network Profile</label>
                    <select id="networkProfile">
                        <option value="stable">Stable (Lab tenang)</option>
                        <option value="normal" selected>Normal (Realistik)</option>
                        <option value="stress">Stress (Jaringan padat)</option>
                        <option value="auto_shuffle">Auto Shuffle (acak per tick)</option>
                    </select>
                </div>
            </div>

            <label class="check-wrap" for="resetBeforeStart">
                <input id="resetBeforeStart" type="checkbox">
                Reset data simulasi lama sebelum start
            </label>

            <div class="actions">
                <button class="btn primary" id="toggleSimulationBtn"><i class="fas fa-play"></i> Start Simulasi</button>
                <button class="btn warning" id="tickSimulationBtn"><i class="fas fa-hand-pointer"></i> Tick Manual</button>
                <button class="btn dark" id="syncConfigBtn"><i class="fas fa-rotate"></i> Sinkronkan Input</button>
                <button class="btn danger" id="resetSimulationBtn"><i class="fas fa-trash"></i> Reset Data Simulasi</button>
            </div>

            <div class="status-row" id="simulationStatusRow">Memuat status simulasi...</div>

            <div class="stats">
                <div class="stat"><div class="label">Status</div><div class="value" id="statRunning">-</div></div>
                <div class="stat"><div class="label">Tick Count</div><div class="value" id="statTicks">-</div></div>
                <div class="stat"><div class="label">ESP Uptime</div><div class="value" id="statUptime">-</div></div>
                <div class="stat"><div class="label">Rows MQTT</div><div class="value" id="statMqttRows">-</div></div>
                <div class="stat"><div class="label">Rows HTTP</div><div class="value" id="statHttpRows">-</div></div>
                <div class="stat"><div class="label">Last Tick</div><div class="value" id="statLastTick">-</div></div>
            </div>

            <div class="meta">
                <span><strong>Simulator Device:</strong> <span id="metaDevice">-</span></span>
                <span><strong>Packet Seq:</strong> MQTT <span id="metaMqttSeq">-</span> | HTTP <span id="metaHttpSeq">-</span></span>
                <span><strong>Sensor Seq:</strong> <span id="metaSensorSeq">-</span></span>
                <span><strong>Base Sensor:</strong> T <span id="metaTemp">-</span> C | H <span id="metaHumidity">-</span>%</span>
                <span><strong>Network:</strong> <span id="metaNetworkProfile">-</span> / <span id="metaNetworkMode">-</span> (<span id="metaNetworkHealth">-</span>%)</span>
            </div>

            <div class="log-box" id="simulationLog"></div>
        </section>

        <section class="surface">
            <div class="surface-head">
                <div>
                    <h2>Kalkulator Dashboard Real</h2>
                    <p>
                        Panel ini menghitung ulang metrik dashboard real dari tabel produksi (`eksperimens`), sehingga Anda bisa melihat rumus, input, dan hasil tanpa keluar dari halaman simulasi.
                    </p>
                </div>
                <div class="calculator-toolbar">
                    <button class="btn dark" id="refreshRealCalculatorBtn"><i class="fas fa-calculator"></i> Refresh Kalkulator</button>
                </div>
            </div>
            <div class="status-row" id="realCalculatorStatusRow">Memuat kalkulator dashboard real...</div>
            <div class="meta">
                <span><strong>Source:</strong> <span id="realCalculatorSource">-</span></span>
                <span><strong>Update:</strong> <span id="realCalculatorStamp">-</span></span>
                <span><strong>Freshness:</strong> <span id="realCalculatorFreshness">-</span></span>
                <span><strong>Analysis Window:</strong> <span id="realCalculatorWindow">-</span></span>
            </div>
            <div class="calculator-groups" id="realCalculatorGroups">
                <div class="calculator-empty">Menunggu data kalkulator dashboard real...</div>
            </div>
        </section>

        <section class="surface surface-dashboard">
            <div class="surface-head">
                <div>
                    <h2>Live Dashboard Simulasi</h2>
                    <p>
                        Frame di bawah ini membaca sumber telemetry simulasi (`source=simulation`), sehingga tidak bercampur dengan dashboard data real produksi.
                    </p>
                </div>
                <div class="frame-tools">
                    <div class="viewport-label">Mode tampilan: <span id="dashboardViewportLabel">Desktop</span></div>
                    <div class="viewport-switch" role="group" aria-label="Pilih mode tampilan dashboard simulasi">
                        <button type="button" class="viewport-btn" data-dashboard-viewport="desktop" title="Desktop">
                            <i class="fas fa-desktop"></i>
                        </button>
                        <button type="button" class="viewport-btn" data-dashboard-viewport="tablet" title="Tablet">
                            <i class="fas fa-tablet-alt"></i>
                        </button>
                        <button type="button" class="viewport-btn" data-dashboard-viewport="mobile" title="Mobile">
                            <i class="fas fa-mobile-alt"></i>
                        </button>
                    </div>
                </div>
            </div>
            <div class="frame-wrap" id="simulationDashboardFrameWrap" data-viewport="desktop">
                <div class="frame-stage" id="simulationDashboardFrameStage">
                    <iframe src="{{ $simulationDashboardPath }}" title="Dashboard MQTT vs HTTP (Simulation Source)"></iframe>
                </div>
            </div>
        </section>
    </div>

    <script>
        (function () {
            const csrf = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
            const apiBase = @json($simulationApiBase);
            const dashboardCalculatorUrl = @json($dashboardCalculatorPath);
            const initialStatus = @json($simulationStatus ?? []);
            const logBox = document.getElementById('simulationLog');
            const statusRow = document.getElementById('simulationStatusRow');
            const calculatorStatusRow = document.getElementById('realCalculatorStatusRow');
            const refreshIntervalMs = 3000;
            const calculatorRefreshIntervalMs = 5000;
            let statusTimer = null;
            let realCalculatorTimer = null;
            let tickTimer = null;
            let tickIntervalMs = 0;
            let lastKnownStatus = null;
            let configDirty = false;
            let hydratingConfig = false;
            const actionButtonIds = [
                'toggleSimulationBtn',
                'tickSimulationBtn',
                'syncConfigBtn',
                'resetSimulationBtn',
            ];
            const configInputIds = [
                'intervalSeconds',
                'httpFailRate',
                'mqttFailRate',
                'networkProfile',
                'resetBeforeStart',
            ];
            const frameWrap = document.getElementById('simulationDashboardFrameWrap');
            const frameStage = document.getElementById('simulationDashboardFrameStage');
            const viewportButtons = Array.from(document.querySelectorAll('[data-dashboard-viewport]'));
            const viewportLabel = document.getElementById('dashboardViewportLabel');
            const viewportStorageKey = 'simulation_dashboard_viewport';
            const dashboardViewportSpecs = {
                desktop: { width: 1440, height: 1024, label: 'Desktop' },
                tablet: { width: 1024, height: 1366, label: 'Tablet' },
                mobile: { width: 430, height: 932, label: 'Mobile' },
            };

            function appendLog(message) {
                const now = new Date();
                const stamp = now.toLocaleTimeString('id-ID', { hour12: false });
                const line = `[${stamp}] ${message}`;
                logBox.textContent = `${line}\n${logBox.textContent}`.slice(0, 6000);
            }

            function setStatus(message, type) {
                statusRow.textContent = message;
                statusRow.classList.remove('ok', 'error');
                if (type === 'ok') statusRow.classList.add('ok');
                if (type === 'error') statusRow.classList.add('error');
            }

            function setCalculatorStatus(message, type) {
                if (!calculatorStatusRow) return;
                calculatorStatusRow.textContent = message;
                calculatorStatusRow.classList.remove('ok', 'error');
                if (type === 'ok') calculatorStatusRow.classList.add('ok');
                if (type === 'error') calculatorStatusRow.classList.add('error');
            }

            function setSimulationControlsDisabled(disabled) {
                actionButtonIds.forEach((id) => {
                    const button = document.getElementById(id);
                    if (!button) return;
                    button.disabled = disabled;
                });
            }

            function setConfigInputsDisabled(disabled) {
                configInputIds.forEach((id) => {
                    const input = document.getElementById(id);
                    if (!input) return;
                    input.disabled = disabled;
                });
            }

            function markConfigDirty() {
                if (hydratingConfig) {
                    return;
                }

                configDirty = true;
            }

            function clearConfigDirty() {
                configDirty = false;
            }

            async function requestJson(path, method = 'GET', body = null) {
                const options = {
                    method,
                    headers: {
                        'Accept': 'application/json',
                    },
                    credentials: 'same-origin',
                };

                if (method !== 'GET') {
                    options.headers['X-CSRF-TOKEN'] = csrf;
                    options.headers['Content-Type'] = 'application/json';
                    options.body = JSON.stringify(body || {});
                }

                const response = await fetch(`${apiBase}${path}`, options);
                const payload = await response.json().catch(() => ({}));
                if (!response.ok) {
                    const message = payload.message || `HTTP ${response.status}`;
                    throw new Error(message);
                }

                return payload;
            }

            async function requestExternalJson(url) {
                const response = await fetch(url, {
                    method: 'GET',
                    headers: {
                        'Accept': 'application/json',
                    },
                    credentials: 'same-origin',
                    cache: 'no-store',
                });
                const payload = await response.json().catch(() => ({}));
                if (!response.ok) {
                    const message = payload.message || `HTTP ${response.status}`;
                    throw new Error(message);
                }

                return payload;
            }

            function normalizeIntervalSeconds(rawValue, fallback = 5) {
                const parsed = Number(rawValue);
                if (!Number.isFinite(parsed)) {
                    return fallback;
                }

                return Math.max(1, Math.min(30, Math.round(parsed)));
            }

            function normalizeRate(rawValue, fallback = 0) {
                const parsed = Number(rawValue);
                if (!Number.isFinite(parsed)) {
                    return fallback;
                }

                return Math.max(0, Math.min(1, parsed));
            }

            function normalizeProfile(profile) {
                const value = String(profile || 'normal').toLowerCase();
                if (value === 'stable' || value === 'normal' || value === 'stress' || value === 'auto_shuffle') {
                    return value;
                }

                return 'normal';
            }

            function formatProfileLabel(profile, activeProfile) {
                const configured = normalizeProfile(profile);
                const active = normalizeProfile(activeProfile || configured);
                if (configured === 'auto_shuffle') {
                    return `AUTO_SHUFFLE (${active.toUpperCase()})`;
                }

                return configured.toUpperCase();
            }

            function modeLabel(mode) {
                const value = String(mode || '').toLowerCase();
                if (value === 'steady') return 'STEADY';
                if (value === 'congested') return 'CONGESTED';
                if (value === 'recovering') return 'RECOVERING';
                return '-';
            }

            function formatLastTickWib(timestampValue) {
                if (!timestampValue) {
                    return '-';
                }

                const parsed = new Date(timestampValue);
                if (Number.isNaN(parsed.getTime())) {
                    return String(timestampValue);
                }

                const parts = new Intl.DateTimeFormat('id-ID', {
                    timeZone: 'Asia/Jakarta',
                    year: 'numeric',
                    month: '2-digit',
                    day: '2-digit',
                    hour: '2-digit',
                    minute: '2-digit',
                    second: '2-digit',
                    hour12: false,
                }).formatToParts(parsed).reduce((carry, part) => {
                    if (part.type !== 'literal') {
                        carry[part.type] = part.value;
                    }
                    return carry;
                }, {});

                const datePart = `${parts.year || '0000'}-${parts.month || '00'}-${parts.day || '00'}`;
                const timePart = `${parts.hour || '00'}:${parts.minute || '00'}:${parts.second || '00'}`;
                return `${datePart}  |  ${timePart} WIB+07:00`;
            }

            function syncToggleButton(isRunning) {
                const button = document.getElementById('toggleSimulationBtn');
                if (!button) return;

                if (isRunning) {
                    button.classList.remove('primary');
                    button.classList.add('dark');
                    button.innerHTML = '<i class="fas fa-stop"></i> Stop Simulasi';
                    return;
                }

                button.classList.remove('dark');
                button.classList.add('primary');
                button.innerHTML = '<i class="fas fa-play"></i> Start Simulasi';
            }

            function syncResetButtonVisibility(totalRows, storageReady) {
                const button = document.getElementById('resetSimulationBtn');
                if (!button) return;

                const hasData = Number(totalRows || 0) > 0;
                button.style.display = (hasData && storageReady) ? '' : 'none';
            }

            function hydrateInputsFromStatus(status, options = {}) {
                if (!status || typeof status !== 'object') return;
                const force = options.force === true;
                if (configDirty && !force) {
                    return;
                }

                hydratingConfig = true;

                if (Number.isFinite(Number(status.interval_seconds))) {
                    document.getElementById('intervalSeconds').value = String(status.interval_seconds);
                }
                if (Number.isFinite(Number(status.http_fail_rate))) {
                    document.getElementById('httpFailRate').value = Number(status.http_fail_rate).toFixed(2);
                }
                if (Number.isFinite(Number(status.mqtt_fail_rate))) {
                    document.getElementById('mqttFailRate').value = Number(status.mqtt_fail_rate).toFixed(2);
                }

                document.getElementById('networkProfile').value = normalizeProfile(status.network_profile);
                hydratingConfig = false;
                if (force) {
                    clearConfigDirty();
                }
            }

            function normalizeViewport(viewport) {
                const value = String(viewport || '').toLowerCase();
                if (value === 'desktop' || value === 'tablet' || value === 'mobile') {
                    return value;
                }

                return 'desktop';
            }

            function getViewportSpec(viewport) {
                const normalized = normalizeViewport(viewport);
                return dashboardViewportSpecs[normalized] || dashboardViewportSpecs.desktop;
            }

            function viewportText(viewport) {
                return getViewportSpec(viewport).label;
            }

            function syncDashboardViewportScale() {
                if (!frameWrap || !frameStage) return;

                const normalized = normalizeViewport(frameWrap.dataset.viewport || 'desktop');
                const spec = getViewportSpec(normalized);
                const wrapStyle = window.getComputedStyle(frameWrap);
                const paddingLeft = parseFloat(wrapStyle.paddingLeft || '0') || 0;
                const paddingRight = parseFloat(wrapStyle.paddingRight || '0') || 0;
                const availableWidth = Math.max(frameWrap.clientWidth - paddingLeft - paddingRight, 240);
                const scale = Math.min(1, availableWidth / spec.width);
                const scaledWidth = Math.max(1, Math.round(spec.width * scale));
                const scaledHeight = Math.max(1, Math.round(spec.height * scale));

                frameWrap.style.setProperty('--dashboard-viewport-width', `${spec.width}px`);
                frameWrap.style.setProperty('--dashboard-viewport-height', `${spec.height}px`);
                frameWrap.style.setProperty('--dashboard-scale', scale.toFixed(4));
                frameWrap.style.setProperty('--dashboard-stage-width', `${scaledWidth}px`);
                frameWrap.style.setProperty('--dashboard-stage-height', `${scaledHeight}px`);
                frameWrap.dataset.viewport = normalized;
            }

            function applyDashboardViewport(viewport, persist = true) {
                if (!frameWrap) return;
                const normalized = normalizeViewport(viewport);
                frameWrap.dataset.viewport = normalized;
                viewportButtons.forEach((button) => {
                    const active = button.dataset.dashboardViewport === normalized;
                    button.classList.toggle('is-active', active);
                    button.setAttribute('aria-pressed', active ? 'true' : 'false');
                });

                if (viewportLabel) {
                    viewportLabel.textContent = viewportText(normalized);
                }

                syncDashboardViewportScale();

                if (persist) {
                    try {
                        window.localStorage.setItem(viewportStorageKey, normalized);
                    } catch (error) {
                        // Ignore localStorage failures (private mode / strict browser policy).
                    }
                }
            }

            function restoreDashboardViewport() {
                let stored = 'desktop';
                try {
                    stored = window.localStorage.getItem(viewportStorageKey) || 'desktop';
                } catch (error) {
                    stored = 'desktop';
                }

                applyDashboardViewport(stored, false);
            }

            function syncInputWithServerStatus() {
                if (!lastKnownStatus) {
                    return;
                }

                hydrateInputsFromStatus(lastKnownStatus, { force: true });
                appendLog('Input konfigurasi disinkronkan dari status server.');
                setStatus('Input konfigurasi berhasil disinkronkan.', 'ok');
            }

            function escapeHtml(value) {
                return String(value ?? '')
                    .replace(/&/g, '&amp;')
                    .replace(/</g, '&lt;')
                    .replace(/>/g, '&gt;')
                    .replace(/"/g, '&quot;')
                    .replace(/'/g, '&#39;');
            }

            function renderCalculatorGroups(payload) {
                const groupsWrap = document.getElementById('realCalculatorGroups');
                if (!groupsWrap) return;

                const groups = Array.isArray(payload?.groups) ? payload.groups : [];
                if (groups.length === 0) {
                    groupsWrap.innerHTML = '<div class="calculator-empty">Belum ada data kalkulator yang bisa ditampilkan.</div>';
                    return;
                }

                groupsWrap.innerHTML = groups.map((group) => {
                    const cards = Array.isArray(group.cards) ? group.cards : [];
                    const cardsHtml = cards.length > 0
                        ? cards.map((card) => {
                            const inputs = Array.isArray(card.inputs) ? card.inputs : [];
                            return `
                                <article class="calculator-card tone-${escapeHtml(card.tone || 'info')}">
                                    <div class="calculator-card-top">
                                        <span class="calculator-chip">${escapeHtml(card.chip || 'CALC')}</span>
                                        <div class="calculator-result">${escapeHtml(card.result || '-')}</div>
                                    </div>
                                    <h4>${escapeHtml(card.label || '-')}</h4>
                                    <p class="calculator-formula">${escapeHtml(card.formula || '-')}</p>
                                    <div class="calculator-inputs">
                                        ${inputs.map((input) => `
                                            <div class="calculator-input-row">
                                                <span>${escapeHtml(input.label || '-')}</span>
                                                <strong>${escapeHtml(input.value || '-')}</strong>
                                            </div>
                                        `).join('')}
                                    </div>
                                </article>
                            `;
                        }).join('')
                        : '<div class="calculator-empty">Belum ada card kalkulator untuk grup ini.</div>';

                    return `
                        <section class="calculator-group">
                            <h3>${escapeHtml(group.title || 'Kalkulator')}</h3>
                            <p>${escapeHtml(group.description || '')}</p>
                            <div class="calculator-grid">
                                ${cardsHtml}
                            </div>
                        </section>
                    `;
                }).join('');
            }

            function renderRealCalculator(payload) {
                document.getElementById('realCalculatorSource').textContent = payload?.source_label || '-';
                document.getElementById('realCalculatorStamp').textContent = payload?.generated_at_wib || '-';
                document.getElementById('realCalculatorFreshness').textContent = Number.isFinite(Number(payload?.protocol_freshness_seconds))
                    ? `${Number(payload.protocol_freshness_seconds)} detik`
                    : '-';
                document.getElementById('realCalculatorWindow').textContent = Number(payload?.analysis_window || 0) > 0
                    ? `${Number(payload.analysis_window)} data terakhir`
                    : 'unlimited';
                renderCalculatorGroups(payload);
                setCalculatorStatus(
                    payload?.status_message || 'Kalkulator dashboard real berhasil diperbarui.',
                    payload?.database_ready === false ? 'error' : 'ok'
                );
            }

            async function refreshRealCalculator() {
                try {
                    const payload = await requestExternalJson(dashboardCalculatorUrl);
                    renderRealCalculator(payload.data || {});
                } catch (error) {
                    setCalculatorStatus(`Gagal memuat kalkulator dashboard real: ${error.message}`, 'error');
                }
            }

            function ensureRealCalculatorLoop() {
                if (realCalculatorTimer) return;
                realCalculatorTimer = setInterval(refreshRealCalculator, calculatorRefreshIntervalMs);
            }

            function updateView(status, options = {}) {
                if (!status || typeof status !== 'object') return;
                const forceHydrate = options.forceHydrate === true;
                lastKnownStatus = status;
                const storageReady = status.storage_ready !== false;
                const totalRows = Number(status.total_rows || 0);

                document.getElementById('statRunning').textContent = status.running ? 'RUNNING' : 'STOPPED';
                document.getElementById('statTicks').textContent = String(status.tick_count ?? 0);
                document.getElementById('statUptime').textContent = `${Number(status.esp_uptime_s || 0)} s`;
                document.getElementById('statMqttRows').textContent = String(status.mqtt_total_rows ?? 0);
                document.getElementById('statHttpRows').textContent = String(status.http_total_rows ?? 0);
                document.getElementById('statLastTick').textContent = formatLastTickWib(status.last_tick_at);
                document.getElementById('metaDevice').textContent = status.device_id ? `${status.device_name} (#${status.device_id})` : '-';
                document.getElementById('metaMqttSeq').textContent = String(status.mqtt_packet_seq ?? 0);
                document.getElementById('metaHttpSeq').textContent = String(status.http_packet_seq ?? 0);
                document.getElementById('metaSensorSeq').textContent = String(status.sensor_read_seq ?? 0);
                document.getElementById('metaTemp').textContent = Number(status.base_temp || 0).toFixed(3);
                document.getElementById('metaHumidity').textContent = Number(status.base_humidity || 0).toFixed(3);
                document.getElementById('metaNetworkProfile').textContent = formatProfileLabel(status.network_profile, status.network_profile_active);
                document.getElementById('metaNetworkMode').textContent = modeLabel(status.network_mode);
                document.getElementById('metaNetworkHealth').textContent = Number(status.network_health || 0).toFixed(2);
                syncToggleButton(status.running);
                syncResetButtonVisibility(totalRows, storageReady);
                setConfigInputsDisabled(status.running || !storageReady);

                if (!status.running) {
                    hydrateInputsFromStatus(status, { force: forceHydrate });
                }

                if (!storageReady) {
                    stopTickLoop();
                    setSimulationControlsDisabled(true);
                    setStatus(
                        status.storage_error || 'Storage simulasi tidak siap. Jalankan migrasi tabel simulasi di server.',
                        'error'
                    );
                } else {
                    setSimulationControlsDisabled(false);
                    setStatus(
                        status.running
                            ? `Simulasi aktif (${modeLabel(status.network_mode)}). Generator berjalan dan dashboard simulasi diperbarui realtime.`
                            : 'Simulasi belum aktif. Tekan Start Simulasi untuk mulai.',
                        status.running ? 'ok' : null
                    );
                }

                if (status.running) {
                    ensureTickLoop(status.interval_seconds);
                } else {
                    stopTickLoop();
                }
            }

            async function refreshStatus() {
                try {
                    const payload = await requestJson('/status');
                    updateView(payload.data || {}, { forceHydrate: false });
                } catch (error) {
                    setStatus(`Gagal membaca status simulasi: ${error.message}`, 'error');
                }
            }

            function parseConfig() {
                const intervalSeconds = normalizeIntervalSeconds(document.getElementById('intervalSeconds').value, 5);
                const httpFailRate = normalizeRate(document.getElementById('httpFailRate').value, 0);
                const mqttFailRate = normalizeRate(document.getElementById('mqttFailRate').value, 0);
                document.getElementById('intervalSeconds').value = String(intervalSeconds);
                document.getElementById('httpFailRate').value = httpFailRate.toFixed(2);
                document.getElementById('mqttFailRate').value = mqttFailRate.toFixed(2);

                return {
                    interval_seconds: intervalSeconds,
                    http_fail_rate: httpFailRate,
                    mqtt_fail_rate: mqttFailRate,
                    network_profile: normalizeProfile(document.getElementById('networkProfile').value),
                    reset_before_start: document.getElementById('resetBeforeStart').checked,
                };
            }

            async function startSimulation() {
                try {
                    const payload = await requestJson('/start', 'POST', parseConfig());
                    const status = payload.data || {};
                    updateView(status, { forceHydrate: true });
                    clearConfigDirty();
                    appendLog(payload.message || 'Simulasi dimulai.');
                    setStatus('Simulasi berhasil dimulai.', 'ok');
                    appendLog(`Profil ${formatProfileLabel(status.network_profile, status.network_profile_active)} | interval ${status.interval_seconds || '-'} detik.`);
                } catch (error) {
                    setStatus(`Gagal start simulasi: ${error.message}`, 'error');
                }
            }

            async function stopSimulation() {
                try {
                    const payload = await requestJson('/stop', 'POST', {});
                    updateView(payload.data || {}, { forceHydrate: true });
                    appendLog(payload.message || 'Simulasi dihentikan.');
                    setStatus('Simulasi dihentikan.', null);
                    stopTickLoop();
                } catch (error) {
                    setStatus(`Gagal stop simulasi: ${error.message}`, 'error');
                }
            }

            async function toggleSimulation() {
                if (lastKnownStatus?.running) {
                    await stopSimulation();
                    return;
                }

                await startSimulation();
            }

            async function resetSimulation() {
                if (!confirm('Reset data simulasi? Hanya data device simulator yang akan dihapus.')) return;

                try {
                    const payload = await requestJson('/reset', 'POST', {});
                    updateView(payload.data || {}, { forceHydrate: true });
                    appendLog(payload.message || 'Data simulasi direset.');
                    setStatus('Data simulasi berhasil direset.', 'ok');
                } catch (error) {
                    setStatus(`Gagal reset simulasi: ${error.message}`, 'error');
                }
            }

            async function manualTick() {
                try {
                    const payload = await requestJson('/tick', 'POST', { run_once_if_stopped: true });
                    const data = payload.data || {};
                    const status = data.status || {};
                    updateView(status, { forceHydrate: true });
                    if (data.ran) {
                        appendLog(data.manual_once ? 'Tick manual berhasil (run-once saat status STOPPED).' : 'Tick simulasi berhasil.');
                    } else {
                        appendLog(`Tick skip (${data.reason || 'no_reason'}).`);
                    }
                } catch (error) {
                    setStatus(`Gagal tick simulasi: ${error.message}`, 'error');
                }
            }

            function ensureStatusLoop() {
                if (statusTimer) return;
                statusTimer = setInterval(refreshStatus, refreshIntervalMs);
            }

            function resolveTickIntervalMs(intervalSeconds) {
                const seconds = Number(intervalSeconds);
                if (!Number.isFinite(seconds) || seconds <= 0) {
                    return 1000;
                }

                return Math.max(1000, Math.round(seconds * 1000));
            }

            function ensureTickLoop(intervalSeconds = null) {
                const configuredSeconds = intervalSeconds
                    ?? lastKnownStatus?.interval_seconds
                    ?? document.getElementById('intervalSeconds').value
                    ?? 1;
                const nextIntervalMs = resolveTickIntervalMs(configuredSeconds);

                if (tickTimer && tickIntervalMs === nextIntervalMs) {
                    return;
                }

                if (tickTimer) {
                    clearInterval(tickTimer);
                }

                tickIntervalMs = nextIntervalMs;
                tickTimer = setInterval(async function () {
                    try {
                        await requestJson('/tick', 'POST', {});
                    } catch (error) {
                        appendLog(`Tick otomatis gagal: ${error.message}`);
                    }
                }, tickIntervalMs);
                appendLog(`Auto tick disetel ${Math.round(tickIntervalMs / 1000)} detik.`);
            }

            function stopTickLoop() {
                if (!tickTimer) return;
                clearInterval(tickTimer);
                tickTimer = null;
                tickIntervalMs = 0;
            }

            configInputIds.forEach((id) => {
                const input = document.getElementById(id);
                if (!input) return;
                input.addEventListener('input', markConfigDirty);
                input.addEventListener('change', markConfigDirty);
            });
            viewportButtons.forEach((button) => {
                button.addEventListener('click', function () {
                    applyDashboardViewport(button.dataset.dashboardViewport || 'desktop', true);
                });
            });
            if (window.ResizeObserver && frameWrap) {
                const observer = new ResizeObserver(function () {
                    syncDashboardViewportScale();
                });
                observer.observe(frameWrap);
            } else {
                window.addEventListener('resize', syncDashboardViewportScale);
            }
            document.getElementById('toggleSimulationBtn').addEventListener('click', toggleSimulation);
            document.getElementById('resetSimulationBtn').addEventListener('click', resetSimulation);
            document.getElementById('tickSimulationBtn').addEventListener('click', manualTick);
            document.getElementById('syncConfigBtn').addEventListener('click', syncInputWithServerStatus);
            document.getElementById('refreshRealCalculatorBtn').addEventListener('click', refreshRealCalculator);
            document.getElementById('intervalSeconds').addEventListener('change', function () {
                if (lastKnownStatus?.running) {
                    ensureTickLoop(this.value);
                }
            });

            hydrateInputsFromStatus(initialStatus, { force: true });
            updateView(initialStatus, { forceHydrate: true });
            restoreDashboardViewport();
            ensureStatusLoop();
            ensureRealCalculatorLoop();
            refreshStatus();
            refreshRealCalculator();
            appendLog('Halaman simulasi siap.');
        }());
    </script>
</body>
</html>
