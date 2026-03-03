<!DOCTYPE html>
<html lang="{{ in_array(strtolower((string) request()->query('lang', 'id')), ['id', 'en'], true) ? strtolower((string) request()->query('lang', 'id')) : 'id' }}">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Technical Documentation - ESP32 MQTT vs HTTP</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        brand: {
                            50: '#ecf4ff',
                            100: '#dbeaff',
                            200: '#bcd9ff',
                            300: '#8bc1ff',
                            400: '#539fff',
                            500: '#2e79ff',
                            600: '#1d5cf3',
                            700: '#1849de',
                            800: '#1a3eb3',
                            900: '#1c388d'
                        }
                    },
                    boxShadow: {
                        glow: '0 0 0 1px rgba(46, 121, 255, 0.25), 0 12px 40px rgba(7, 20, 55, 0.35)'
                    }
                }
            }
        };
    </script>
    <style>
        html {
            scroll-behavior: smooth;
        }

        body {
            background-image:
                radial-gradient(circle at 12% 0%, rgba(46, 121, 255, 0.16), transparent 38%),
                radial-gradient(circle at 92% 8%, rgba(14, 165, 233, 0.14), transparent 35%),
                linear-gradient(180deg, rgba(2, 6, 23, 1) 0%, rgba(2, 6, 23, 0.98) 45%, rgba(1, 8, 25, 1) 100%);
        }

        .doc-card {
            background: linear-gradient(180deg, rgba(15, 23, 42, 0.88), rgba(15, 23, 42, 0.66));
        }

        /* Mobile/tablet nav gets blur only after it is stuck at top. */
        .mobile-doc-nav[data-stuck="false"] {
            background-color: rgba(15, 23, 42, 0.55);
            border-color: rgba(148, 163, 184, 0.20);
            box-shadow: none;
            -webkit-backdrop-filter: none;
            backdrop-filter: none;
        }

        .mobile-doc-nav[data-stuck="true"] {
            background-color: rgba(15, 23, 42, 0.88);
            border-color: rgba(148, 163, 184, 0.35);
            box-shadow: 0 14px 30px rgba(2, 6, 23, 0.42);
            -webkit-backdrop-filter: blur(12px);
            backdrop-filter: blur(12px);
        }

        @media (max-width: 640px) {
            pre {
                font-size: 11px;
                line-height: 1.45;
            }
        }
    </style>
</head>
<body class="min-h-screen bg-slate-950 text-slate-100 antialiased">
@php
    // Language toggle source for this page: /doc?lang=id|en
    $docLang = strtolower((string) request()->query('lang', 'id'));
    if (!in_array($docLang, ['id', 'en'], true)) {
        $docLang = 'id';
    }

    // Translation helper: first arg Indonesian, second arg English
    $t = static fn (string $id, string $en): string => $docLang === 'id' ? $id : $en;
    $langUrl = static function (string $lang): string {
        $query = request()->query();
        $query['lang'] = $lang;
        return url()->current() . '?' . http_build_query($query);
    };

    // Keep these payload field lists synchronized with:
    // - app/Http/Controllers/ApiController.php validation
    // - mqtt_worker.php payload validation
    // - ESP32_Firmware/src/main.cpp fillProtocolPayload()
    $requiredPayloadFields = [
        'device_id',
        'suhu',
        'kelembapan',
        'timestamp_esp',
        'daya',
        'packet_seq',
        'rssi_dbm',
        'tx_duration_ms',
        'payload_bytes',
        'uptime_s',
        'free_heap_bytes',
        'sensor_age_ms',
        'sensor_read_seq',
        'send_tick_ms',
    ];

    $extendedFirmwareFields = [
        'protokol',
        'sensor_reads',
        'http_success_count',
        'http_fail_count',
        'mqtt_success_count',
        'mqtt_fail_count',
    ];

    $analysisWindow = max(50, (int) config('dashboard.analysis_window', 1200));
    $chartWindow = max(0, (int) config('dashboard.chart_window', 0));
    $seqMaxGap = max(1, (int) config('dashboard.sequence.max_gap_for_loss', 120));
    $seqRebootDrop = max(1, (int) config('dashboard.sequence.reboot_uptime_drop_seconds', 30));
@endphp

    <div id="top"></div>

    <div class="border-b border-white/10 bg-slate-900/70 backdrop-blur">
        <div class="mx-auto flex max-w-7xl flex-col gap-4 px-4 py-4 sm:px-6 lg:flex-row lg:items-center lg:justify-between">
            <div>
                <p class="text-xs uppercase tracking-[0.2em] text-brand-300">{{ $t('Rute /doc', 'Route /doc') }}</p>
                <h1 class="text-xl font-semibold md:text-2xl">{{ $t('Dokumentasi Teknis - Implementasi Runtime Aktual', 'Technical Documentation - Actual Runtime Implementation') }}</h1>
                <p class="mt-1 text-sm text-slate-300">{{ $t('Cakupan sumber: firmware ESP32, route/controller Laravel, MQTT worker, statistics service, migration, dan dashboard view.', 'Source scope: ESP32 firmware, Laravel routes/controllers, MQTT worker, statistics service, migrations, and dashboard view.') }}</p>
            </div>
            <div class="flex w-full flex-wrap items-center gap-2 text-xs sm:w-auto sm:justify-end">
                <div class="flex items-center gap-1 rounded-lg border border-white/20 bg-slate-900/60 p-1">
                    <span class="px-1 text-[11px] uppercase tracking-[0.14em] text-slate-400">{{ $t('Bahasa', 'Language') }}</span>
                    <div class="inline-flex overflow-hidden rounded-md border border-white/20">
                        <a href="{{ $langUrl('id') }}" class="px-2.5 py-1.5 {{ $docLang === 'id' ? 'bg-brand-600 text-white' : 'bg-slate-800 text-slate-300 hover:bg-slate-700' }}">ID</a>
                        <a href="{{ $langUrl('en') }}" class="px-2.5 py-1.5 {{ $docLang === 'en' ? 'bg-brand-600 text-white' : 'bg-slate-800 text-slate-300 hover:bg-slate-700' }}">EN</a>
                    </div>
                </div>
                <a href="{{ url('/') }}" class="flex-1 rounded-lg border border-white/15 px-3 py-1.5 text-center text-slate-200 hover:border-brand-400 hover:text-white sm:flex-none">Dashboard</a>
                <a href="{{ url('/simulation') }}" class="flex-1 rounded-lg border border-white/15 px-3 py-1.5 text-center text-slate-200 hover:border-brand-400 hover:text-white sm:flex-none">Simulation</a>
                <a href="{{ url('/admin/config') }}" class="flex-1 rounded-lg border border-white/15 px-3 py-1.5 text-center text-slate-200 hover:border-brand-400 hover:text-white sm:flex-none">Admin Config</a>
            </div>
        </div>
    </div>

    <div class="mx-auto max-w-7xl px-4 py-6 sm:px-6">
        {{-- Quick metadata cards for responsive readability --}}
        <section class="mb-5 grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
            <div class="doc-card rounded-xl border border-white/10 p-3">
                <p class="text-[11px] uppercase tracking-[0.14em] text-slate-400">{{ $t('Bahasa Aktif', 'Active Language') }}</p>
                <p class="mt-1 text-sm font-semibold text-white">{{ $docLang === 'id' ? 'Indonesia' : 'English' }}</p>
            </div>
            <div class="doc-card rounded-xl border border-white/10 p-3">
                <p class="text-[11px] uppercase tracking-[0.14em] text-slate-400">{{ $t('Window Analisis', 'Analysis Window') }}</p>
                <p class="mt-1 text-sm font-semibold text-white">{{ number_format($analysisWindow) }} {{ $t('baris/protokol', 'rows/protocol') }}</p>
            </div>
            <div class="doc-card rounded-xl border border-white/10 p-3">
                <p class="text-[11px] uppercase tracking-[0.14em] text-slate-400">{{ $t('Window Chart', 'Chart Window') }}</p>
                <p class="mt-1 text-sm font-semibold text-white">{{ $chartWindow === 0 ? $t('Tidak terbatas', 'Unlimited') : number_format($chartWindow) }}</p>
            </div>
            <div class="doc-card rounded-xl border border-white/10 p-3">
                <p class="text-[11px] uppercase tracking-[0.14em] text-slate-400">{{ $t('Kapasitas Navigasi', 'Navigation Coverage') }}</p>
                <p class="mt-1 text-sm font-semibold text-white">13 {{ $t('bagian teknis', 'technical sections') }}</p>
            </div>
        </section>

        {{-- Mobile and tablet section jump navigation --}}
        <div id="mobile-doc-nav-sentinel" class="h-px lg:hidden"></div>
        <nav id="mobile-doc-nav" data-stuck="false" class="mobile-doc-nav sticky top-0 z-20 mb-4 flex gap-2 overflow-x-auto rounded-xl border p-2 text-xs whitespace-nowrap transition-all duration-200 lg:hidden">
            <a href="#overview" class="rounded-full bg-slate-800 px-3 py-1.5">{{ $t('Ringkasan', 'Overview') }}</a>
            <a href="#architecture" class="rounded-full bg-slate-800 px-3 py-1.5">{{ $t('Arsitektur', 'Architecture') }}</a>
            <a href="#payload" class="rounded-full bg-slate-800 px-3 py-1.5">Payload</a>
            <a href="#latency" class="rounded-full bg-slate-800 px-3 py-1.5">Latency</a>
            <a href="#power" class="rounded-full bg-slate-800 px-3 py-1.5">{{ $t('Daya', 'Power') }}</a>
            <a href="#pdr" class="rounded-full bg-slate-800 px-3 py-1.5">PDR</a>
            <a href="#ttest" class="rounded-full bg-slate-800 px-3 py-1.5">T-Test</a>
            <a href="#database" class="rounded-full bg-slate-800 px-3 py-1.5">Database</a>
            <a href="#flow" class="rounded-full bg-slate-800 px-3 py-1.5">Flow</a>
            <a href="#limits" class="rounded-full bg-slate-800 px-3 py-1.5">{{ $t('Batasan', 'Limits') }}</a>
        </nav>

        <div class="grid gap-6 lg:grid-cols-[18rem_minmax(0,1fr)]">
            {{-- Sidebar navigation for desktop --}}
            <aside class="hidden lg:block">
                <div class="sticky top-4 rounded-2xl border border-white/10 bg-slate-900/70 p-4 shadow-glow">
                    <p class="mb-3 text-xs uppercase tracking-[0.2em] text-brand-300">{{ $t('Navigasi', 'Navigation') }}</p>
                    <nav class="space-y-2 text-sm">
                        <a href="#overview" class="block rounded-lg px-2 py-1.5 hover:bg-white/5">{{ $t('System Overview', 'System Overview') }}</a>
                        <a href="#architecture" class="block rounded-lg px-2 py-1.5 hover:bg-white/5">{{ $t('Arsitektur Aktual', 'Actual Architecture') }}</a>
                        <a href="#routes" class="block rounded-lg px-2 py-1.5 hover:bg-white/5">Laravel Routes</a>
                        <a href="#ingress" class="block rounded-lg px-2 py-1.5 hover:bg-white/5">{{ $t('HTTP & MQTT Ingress', 'HTTP & MQTT Ingress') }}</a>
                        <a href="#payload" class="block rounded-lg px-2 py-1.5 hover:bg-white/5">JSON Payload</a>
                        <a href="#latency" class="block rounded-lg px-2 py-1.5 hover:bg-white/5">{{ $t('Logika Latency', 'Latency Logic') }}</a>
                        <a href="#power" class="block rounded-lg px-2 py-1.5 hover:bg-white/5">{{ $t('Logika Daya (daya_mw)', 'Power Logic (daya_mw)') }}</a>
                        <a href="#pdr" class="block rounded-lg px-2 py-1.5 hover:bg-white/5">Packet Delivery Ratio</a>
                        <a href="#ttest" class="block rounded-lg px-2 py-1.5 hover:bg-white/5">Independent Sample T-Test</a>
                        <a href="#database" class="block rounded-lg px-2 py-1.5 hover:bg-white/5">{{ $t('Database & Relasi', 'Database & Relations') }}</a>
                        <a href="#flow" class="block rounded-lg px-2 py-1.5 hover:bg-white/5">{{ $t('Alur Data ke Dashboard', 'Data-to-Dashboard Flow') }}</a>
                        <a href="#validation" class="block rounded-lg px-2 py-1.5 hover:bg-white/5">{{ $t('Validasi Statistik', 'Statistical Validation') }}</a>
                        <a href="#limits" class="block rounded-lg px-2 py-1.5 hover:bg-white/5">{{ $t('Batasan Sistem', 'System Limitations') }}</a>
                    </nav>
                </div>
            </aside>

            <main class="min-w-0 space-y-5 sm:space-y-6">
                {{-- SECTION 1: System Overview --}}
                <section id="overview" class="doc-card rounded-2xl border border-white/10 p-4 shadow-glow sm:p-5 lg:p-6">
                    <h2 class="text-lg font-semibold sm:text-xl">System Overview</h2>
                    <p class="mt-3 text-sm text-slate-300">{{ $t('Implementasi saat ini membandingkan telemetry MQTT vs HTTP dari ESP32, menyimpan keduanya ke MySQL, lalu menampilkan statistik komparatif, chart, dan t-test pada satu dashboard.', 'Current implementation compares MQTT vs HTTP telemetry from ESP32, stores both into MySQL, then renders comparative statistics, charts, and t-tests on one dashboard.') }}</p>
                    <div class="mt-4 grid gap-3 text-sm md:grid-cols-2">
                        <div class="rounded-xl border border-white/10 bg-slate-800/50 p-3">
                            <p class="font-medium text-brand-300">Firmware (ESP32)</p>
                            <p class="mt-1 text-slate-300">{!! $t('File: <code>ESP32_Firmware/src/main.cpp</code>. Mengirim HTTP dan MQTT secara periodik (default keduanya 10 detik) dengan field telemetry lengkap dan packet sequence.', 'File: <code>ESP32_Firmware/src/main.cpp</code>. Sends HTTP and MQTT periodically (default both 10 seconds) with complete telemetry fields and packet sequence.') !!}</p>
                        </div>
                        <div class="rounded-xl border border-white/10 bg-slate-800/50 p-3">
                            <p class="font-medium text-brand-300">HTTP Ingress</p>
                            <p class="mt-1 text-slate-300">{!! $t('Rute <code>POST /api/http-data</code> -> <code>ApiController::storeHttp</code>, memakai middleware <code>throttle:http-data</code> dan <code>ingest.key</code>.', 'Route <code>POST /api/http-data</code> -> <code>ApiController::storeHttp</code>, with middleware <code>throttle:http-data</code> and <code>ingest.key</code>.') !!}</p>
                        </div>
                        <div class="rounded-xl border border-white/10 bg-slate-800/50 p-3">
                            <p class="font-medium text-brand-300">MQTT Ingress</p>
                            <p class="mt-1 text-slate-300">{!! $t('Tidak ditangani controller Laravel. MQTT incoming diproses worker mandiri <code>mqtt_worker.php</code> lalu di-upsert ke <code>eksperimens</code>.', 'Not handled by a Laravel controller. Incoming MQTT is processed by standalone worker <code>mqtt_worker.php</code> then upserted into <code>eksperimens</code>.') !!}</p>
                        </div>
                        <div class="rounded-xl border border-white/10 bg-slate-800/50 p-3">
                            <p class="font-medium text-brand-300">Dashboard</p>
                            <p class="mt-1 text-slate-300">{!! $t('Rute <code>GET /</code> -> <code>DashboardController::index</code>, statistik dihitung oleh <code>StatisticsService</code>, frontend di <code>resources/views/dashboard.blade.php</code>.', 'Route <code>GET /</code> -> <code>DashboardController::index</code>, statistics are computed by <code>StatisticsService</code>, frontend is in <code>resources/views/dashboard.blade.php</code>.') !!}</p>
                        </div>
                    </div>
                </section>

                {{-- SECTION 2: Actual Architecture + data flow diagram --}}
                <section id="architecture" class="doc-card rounded-2xl border border-white/10 p-4 shadow-glow sm:p-5 lg:p-6">
                    <h2 class="text-lg font-semibold sm:text-xl">{{ $t('Arsitektur Aktual (Berbasis Kode)', 'Actual Architecture (Code-Based)') }}</h2>
                    <p class="mt-3 text-sm text-slate-300">{{ $t('Diagram berikut mengikuti jalur runtime yang benar-benar ada di repository ini.', 'The diagram below follows runtime paths that actually exist in this repository.') }}</p>
                    <div class="mt-4 overflow-x-auto rounded-xl border border-white/10 bg-slate-950 p-4">
<pre class="text-xs leading-5 text-slate-200">
ESP32 Firmware (main.cpp)
   |-- HTTP JSON -> POST /api/http-data
   |                 -> middleware(throttle:http-data, ingest.key)
   |                 -> ApiController::storeHttp()
   |                 -> Eksperimen::updateOrCreate(device_id, 'HTTP', packet_seq)
   |
   |-- MQTT JSON -> topic {{ config('mqtt.topic', 'iot/esp32/suhu') }}
                     -> Mosquitto Broker ({{ config('mqtt.host', '127.0.0.1') }}:{{ config('mqtt.port', 1883) }})
                     -> mqtt_worker.php subscribe()
                     -> Eksperimen::updateOrCreate(device_id, 'MQTT', packet_seq)

Dashboard (/)
   -> DashboardController::index()
   -> StatisticsService (summary, reliability, t-test)
   -> dashboard.blade.php (cards, warnings, diagnostics, charts, t-test)

Optional simulation branch
   /simulation endpoints -> ApplicationSimulationService
   -> simulated_eksperimens table
   -> /?source=simulation dashboard source
</pre>
                    </div>
                </section>

                {{-- SECTION 3: Route map --}}
                <section id="routes" class="doc-card rounded-2xl border border-white/10 p-4 shadow-glow sm:p-5 lg:p-6">
                    <h2 class="text-lg font-semibold sm:text-xl">{{ $t('Peta Route Laravel (Aktual)', 'Laravel Route Map (Current)') }}</h2>
                    <div class="mt-3 overflow-x-auto rounded-xl border border-white/10">
                        <table class="min-w-full text-left text-xs sm:text-sm">
                            <thead class="bg-slate-800/60 text-slate-200">
                                <tr>
                                    <th class="px-3 py-2">Method</th>
                                    <th class="px-3 py-2">Path</th>
                                    <th class="px-3 py-2">Handler</th>
                                    <th class="px-3 py-2">{{ $t('Tujuan', 'Purpose') }}</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-white/10 text-slate-300">
                                <tr><td class="px-3 py-2">GET</td><td class="px-3 py-2"><code>/</code></td><td class="px-3 py-2"><code>DashboardController@index</code></td><td class="px-3 py-2">{{ $t('Dashboard utama', 'Main dashboard') }}</td></tr>
                                <tr><td class="px-3 py-2">POST</td><td class="px-3 py-2"><code>/api/http-data</code></td><td class="px-3 py-2"><code>ApiController@storeHttp</code></td><td class="px-3 py-2">{{ $t('HTTP ingest dari ESP32', 'HTTP ingest from ESP32') }}</td></tr>
                                <tr><td class="px-3 py-2">GET</td><td class="px-3 py-2"><code>/simulation</code></td><td class="px-3 py-2"><code>SimulationController@index</code></td><td class="px-3 py-2">{{ $t('UI simulasi', 'Simulation UI') }}</td></tr>
                                <tr><td class="px-3 py-2">POST</td><td class="px-3 py-2"><code>/simulation/start|stop|tick|reset</code></td><td class="px-3 py-2"><code>SimulationController</code></td><td class="px-3 py-2">{{ $t('Kontrol simulasi', 'Simulation control') }}</td></tr>
                                <tr><td class="px-3 py-2">GET/POST</td><td class="px-3 py-2"><code>/reset-data</code></td><td class="px-3 py-2"><code>DashboardController</code></td><td class="px-3 py-2">{{ $t('Reset telemetry real', 'Reset real telemetry') }}</td></tr>
                                <tr><td class="px-3 py-2">GET</td><td class="px-3 py-2"><code>/admin/login</code></td><td class="px-3 py-2"><code>AdminConfigController@loginForm</code></td><td class="px-3 py-2">{{ $t('Login admin', 'Admin login') }}</td></tr>
                                <tr><td class="px-3 py-2">GET/POST/PATCH/DELETE</td><td class="px-3 py-2"><code>/admin/config/*</code></td><td class="px-3 py-2"><code>AdminConfigController</code></td><td class="px-3 py-2">{{ $t('Provisioning runtime + firmware', 'Runtime + firmware provisioning') }}</td></tr>
                                <tr><td class="px-3 py-2">GET</td><td class="px-3 py-2"><code>/doc</code></td><td class="px-3 py-2"><code>view('doc')</code></td><td class="px-3 py-2">{{ $t('Halaman dokumentasi teknis ini', 'This technical documentation page') }}</td></tr>
                            </tbody>
                        </table>
                    </div>
                </section>

                {{-- SECTION 4: Controller ingest and worker --}}
                <section id="ingress" class="doc-card rounded-2xl border border-white/10 p-4 shadow-glow sm:p-5 lg:p-6">
                    <h2 class="text-lg font-semibold sm:text-xl">{{ $t('Controller HTTP dan Worker MQTT', 'HTTP Controller and MQTT Worker') }}</h2>
                    <div class="mt-3 grid gap-3 text-sm md:grid-cols-2">
                        <div class="rounded-xl border border-white/10 bg-slate-800/50 p-4">
                            <p class="font-medium text-brand-300">HTTP path (<code>ApiController::storeHttp</code>)</p>
                            <ul class="mt-2 list-disc space-y-1 pl-5 text-slate-300">
                                <li>{{ $t('Memvalidasi semua field telemetry wajib beserta batas nilainya.', 'Validates all required telemetry fields with range constraints.') }}</li>
                                <li>{!! $t('Menghitung <code>latency_ms</code> dari waktu server UTC dikurangi <code>timestamp_esp</code>.', 'Computes <code>latency_ms</code> from server UTC time minus <code>timestamp_esp</code>.') !!}</li>
                                <li>{!! $t('Kunci upsert: <code>(device_id, protokol=\'HTTP\', packet_seq)</code>.', 'Upsert key: <code>(device_id, protokol=\'HTTP\', packet_seq)</code>.') !!}</li>
                                <li>{!! $t('Menyimpan <code>daya</code> ke kolom DB <code>daya_mw</code>.', 'Stores <code>daya</code> into DB column <code>daya_mw</code>.') !!}</li>
                            </ul>
                        </div>
                        <div class="rounded-xl border border-white/10 bg-slate-800/50 p-4">
                            <p class="font-medium text-brand-300">MQTT path (<code>mqtt_worker.php</code>)</p>
                            <ul class="mt-2 list-disc space-y-1 pl-5 text-slate-300">
                                <li>{!! $t('Subscribe topic telemetry: <code>' . config('mqtt.topic', 'iot/esp32/suhu') . '</code>.', 'Subscribes telemetry topic: <code>' . config('mqtt.topic', 'iot/esp32/suhu') . '</code>.') !!}</li>
                                <li>{{ $t('Melakukan validasi required/type/range secara manual di proses worker.', 'Performs required-field/type/range checks manually in worker process.') }}</li>
                                <li>{!! $t('Menghitung <code>latency_ms</code> dengan formula yang sama seperti jalur HTTP.', 'Computes <code>latency_ms</code> with the same formula as HTTP path.') !!}</li>
                                <li>{!! $t('Kunci upsert: <code>(device_id, protokol=\'MQTT\', packet_seq)</code>.', 'Upsert key: <code>(device_id, protokol=\'MQTT\', packet_seq)</code>.') !!}</li>
                                <li>{!! $t('Juga subscribe debug topic <code>' . config('mqtt.debug_topic', 'iot/esp32/debug') . '</code> dan update <code>storage/app/esp32_debug_heartbeat.json</code>.', 'Also subscribes debug topic <code>' . config('mqtt.debug_topic', 'iot/esp32/debug') . '</code> and updates <code>storage/app/esp32_debug_heartbeat.json</code>.') !!}</li>
                            </ul>
                        </div>
                    </div>
                    <p class="mt-3 text-xs text-slate-400">{!! $t('Catatan penting: repository ini tidak memiliki controller Laravel khusus MQTT ingest. Ingestion MQTT ditangani proses mandiri <code>mqtt_worker.php</code>.', 'Important note: this repository has no dedicated Laravel MQTT ingest controller. MQTT ingestion is handled by standalone process <code>mqtt_worker.php</code>.') !!}</p>
                </section>

                {{-- SECTION 5: Payload structure used by firmware/backend --}}
                <section id="payload" class="doc-card rounded-2xl border border-white/10 p-4 shadow-glow sm:p-5 lg:p-6">
                    <h2 class="text-lg font-semibold sm:text-xl">{{ $t('Struktur JSON Payload yang Dipakai Runtime', 'JSON Payload Structure Used in Runtime') }}</h2>
                    <p class="mt-3 text-sm text-slate-300">{!! $t('Builder payload firmware (<code>fillProtocolPayload()</code>) selalu mengirim field telemetry wajib beserta field diagnostik tambahan.', 'Firmware payload builder (<code>fillProtocolPayload()</code>) always emits required telemetry fields plus additional diagnostics fields.') !!}</p>
                    <div class="mt-4 rounded-xl border border-white/10 bg-slate-950 p-4">
<pre class="overflow-x-auto text-xs leading-5 text-slate-200"><code>{
  "device_id": 1,
  "protokol": "HTTP",
  "packet_seq": 171234,
  "suhu": 28.125,
  "kelembapan": 60.45,
  "timestamp_esp": 1772021517,
  "daya": 812.34,
  "rssi_dbm": -60,
  "tx_duration_ms": 45.2,
  "payload_bytes": 208,
  "uptime_s": 7200,
  "free_heap_bytes": 265000,
  "sensor_age_ms": 980,
  "sensor_read_seq": 444,
  "send_tick_ms": 9876543,
  "sensor_reads": 445,
  "http_success_count": 320,
  "http_fail_count": 4,
  "mqtt_success_count": 318,
  "mqtt_fail_count": 6
}</code></pre>
                    </div>
                    <div class="mt-4 grid gap-3 md:grid-cols-2">
                        <div class="rounded-xl border border-white/10 bg-slate-800/50 p-4 text-sm">
                            <p class="font-medium text-brand-300">{{ $t('Field wajib yang diterima backend', 'Required fields accepted by backend') }}</p>
                            <p class="mt-2 text-slate-300">{{ implode(', ', $requiredPayloadFields) }}</p>
                        </div>
                        <div class="rounded-xl border border-white/10 bg-slate-800/50 p-4 text-sm">
                            <p class="font-medium text-brand-300">{{ $t('Field tambahan yang dikirim firmware', 'Extra fields emitted by firmware') }}</p>
                            <p class="mt-2 text-slate-300">{{ implode(', ', $extendedFirmwareFields) }}</p>
                        </div>
                    </div>
                </section>

                {{-- SECTION 6: Latency formula --}}
                <section id="latency" class="doc-card rounded-2xl border border-white/10 p-4 shadow-glow sm:p-5 lg:p-6">
                    <h2 class="text-lg font-semibold sm:text-xl">{{ $t('Mekanisme Perhitungan Latency', 'Latency Calculation Mechanism') }}</h2>
                    <p class="mt-3 text-sm text-slate-300">{{ $t('Controller HTTP dan worker MQTT menggunakan formula latency yang sama.', 'Both HTTP controller and MQTT worker use the same latency formula.') }}</p>
                    <div class="mt-4 rounded-xl border border-white/10 bg-slate-950 p-4">
<pre class="overflow-x-auto text-xs leading-5 text-slate-200"><code>// app/Http/Controllers/ApiController.php and mqtt_worker.php
$timestampServer = Carbon::now('UTC');
$timestampEsp = Carbon::createFromTimestampUTC((int) $validated['timestamp_esp']);
$latencyMs = abs((float) $timestampServer->floatDiffInMilliseconds($timestampEsp));</code></pre>
                    </div>
                    <ul class="mt-3 list-disc space-y-1 pl-5 text-sm text-slate-300">
                        <li>{!! $t('Nilai latency disimpan di <code>eksperimens.latency_ms</code>.', 'Latency value is stored in <code>eksperimens.latency_ms</code>.') !!}</li>
                        <li>{!! $t('Menggunakan nilai absolut (<code>abs</code>), sehingga drift jam negatif tetap menjadi magnitude positif.', 'Absolute value (<code>abs</code>) is used, so negative clock drift becomes positive magnitude.') !!}</li>
                        <li>{!! $t('Accessor model pada <code>Eksperimen</code> juga mengembalikan <code>latency_ms</code> absolut.', 'Model accessor in <code>Eksperimen</code> also returns absolute <code>latency_ms</code>.') !!}</li>
                    </ul>
                </section>

                {{-- SECTION 7: Power formula from firmware --}}
                <section id="power" class="doc-card rounded-2xl border border-white/10 p-4 shadow-glow sm:p-5 lg:p-6">
                    <h2 class="text-lg font-semibold sm:text-xl">{!! $t('Mekanisme Perhitungan Daya (<code>daya_mw</code>)', 'Power Calculation Mechanism (<code>daya_mw</code>)') !!}</h2>
                    <p class="mt-3 text-sm text-slate-300">{!! $t('Daya diestimasi di firmware (<code>estimateProtocolPower()</code>) lalu dikirim sebagai field <code>daya</code>. Backend menyimpannya ke <code>daya_mw</code>.', 'Power is estimated inside firmware (<code>estimateProtocolPower()</code>) and sent as field <code>daya</code>. Backend stores it as <code>daya_mw</code>.') !!}</p>
                    <div class="mt-4 rounded-xl border border-white/10 bg-slate-950 p-4">
<pre class="overflow-x-auto text-xs leading-5 text-slate-200"><code>// ESP32_Firmware/src/main.cpp
float totalCurrentMa = wifiBaseCurrentMa
    + sensorCurrentMa
    + cpuCurrentMa
    + signalPenalty
    + payloadCurrent
    + txDurationCurrent
    + protocolOverhead
    + protocolReliabilityPenalty
    + thermalCurrent
    + humidityCurrent
    + retryPenalty;
float powerMw = voltage * totalCurrentMa;
return max(0.0f, powerMw);</code></pre>
                    </div>
                    <ul class="mt-3 list-disc space-y-1 pl-5 text-sm text-slate-300">
                        <li>{{ $t('HTTP dan MQTT memakai protocol overhead yang berbeda (HTTP lebih tinggi).', 'HTTP and MQTT have different protocol overhead values (HTTP is higher).') }}</li>
                        <li>{!! $t('Penalty naik saat rasio gagal meningkat (<code>httpSendFail</code>/<code>mqttSendFail</code>).', 'Penalty increases as fail ratio rises (<code>httpSendFail</code>/<code>mqttSendFail</code>).') !!}</li>
                        <li>{{ $t('Durasi transmisi, ukuran payload, RSSI, dan delta lingkungan memengaruhi estimasi.', 'Transmission duration, payload size, RSSI, and environment deltas affect the estimate.') }}</li>
                        <li>{{ $t('Formula bersifat deterministik (jalur firmware saat ini tidak memakai baseline acak).', 'Formula is deterministic (current firmware path uses no random baseline).') }}</li>
                    </ul>
                </section>

                {{-- SECTION 8: PDR / reliability formula --}}
                <section id="pdr" class="doc-card rounded-2xl border border-white/10 p-4 shadow-glow sm:p-5 lg:p-6">
                    <h2 class="text-lg font-semibold sm:text-xl">{{ $t('Packet Delivery Ratio (Reliability Backend)', 'Packet Delivery Ratio (Backend Reliability)') }}</h2>
                    <p class="mt-3 text-sm text-slate-300">{!! $t('Backend tidak menyimpan field terpisah bernama PDR, namun sequence reliability dihitung sebagai <code>received / expected * 100</code> dan dipakai di skor reliability total.', 'The backend does not persist a separate field named PDR, but sequence reliability is calculated as <code>received / expected * 100</code> and used in total reliability score.') !!}</p>
                    <div class="mt-4 rounded-xl border border-white/10 bg-slate-950 p-4">
<pre class="overflow-x-auto text-xs leading-5 text-slate-200"><code>// app/Services/StatisticsService.php
$missing = max(0, $expected - $received);
$rate = ($received / $expected) * 100;

// combineReliability() when packet_seq exists
return ($sequenceRate * 0.55) + ($completenessRate * 0.25) + ($transmissionRate * 0.20);</code></pre>
                    </div>
                    <div class="mt-3 grid gap-3 text-sm md:grid-cols-2">
                        <div class="rounded-xl border border-white/10 bg-slate-800/50 p-4">
                            <p class="font-medium text-brand-300">{{ $t('Aturan kontinuitas sequence', 'Sequence continuity rules') }}</p>
                            <ul class="mt-2 list-disc space-y-1 pl-5 text-slate-300">
                                <li>{!! $t('Batas gap loss: <code>DASHBOARD_SEQUENCE_MAX_GAP_FOR_LOSS</code> (saat ini ' . $seqMaxGap . ').', 'Gap handling threshold: <code>DASHBOARD_SEQUENCE_MAX_GAP_FOR_LOSS</code> (current ' . $seqMaxGap . ').') !!}</li>
                                <li>{!! $t('Deteksi reboot via penurunan uptime: <code>DASHBOARD_SEQUENCE_REBOOT_UPTIME_DROP_SECONDS</code> (saat ini ' . $seqRebootDrop . 's).', 'Reboot detection by uptime drop: <code>DASHBOARD_SEQUENCE_REBOOT_UPTIME_DROP_SECONDS</code> (current ' . $seqRebootDrop . 's).') !!}</li>
                                <li>{{ $t('Lompatan besar, lompatan negatif, atau reboot memulai segmen baru (tidak dihitung sebagai rantai loss kontinu).', 'Large jump, negative jump, or reboot starts a new segment (not counted as one continuous loss chain).') }}</li>
                            </ul>
                        </div>
                        <div class="rounded-xl border border-white/10 bg-slate-800/50 p-4">
                            <p class="font-medium text-brand-300">{{ $t('Komposisi reliability akhir', 'Final reliability composition') }}</p>
                            <ul class="mt-2 list-disc space-y-1 pl-5 text-slate-300">
                                <li>{{ $t('Ukuran window: 300 baris terbaru per protokol.', 'Window size: latest 300 rows per protocol.') }}</li>
                                <li>{!! $t('Jika packet sequence tidak tersedia: <code>0.6 * completeness + 0.4 * transmission_health</code>.', 'When packet sequence is missing: <code>0.6 * completeness + 0.4 * transmission_health</code>.') !!}</li>
                                <li>{{ $t('Transmission health menggunakan latency + tx duration + keberadaan payload.', 'Transmission health uses latency + tx duration + payload presence.') }}</li>
                            </ul>
                        </div>
                    </div>
                </section>

                {{-- SECTION 9: T-test implementation --}}
                <section id="ttest" class="doc-card rounded-2xl border border-white/10 p-4 shadow-glow sm:p-5 lg:p-6">
                    <h2 class="text-lg font-semibold sm:text-xl">Independent Sample T-Test</h2>
                    <p class="mt-3 text-sm text-slate-300">{!! $t('Diimplementasikan di <code>StatisticsService::tTest()</code> untuk <code>latency_ms</code> dan <code>daya_mw</code>.', 'Implemented in <code>StatisticsService::tTest()</code> for <code>latency_ms</code> and <code>daya_mw</code>.') !!}</p>
                    <div class="mt-4 rounded-xl border border-white/10 bg-slate-950 p-4">
<pre class="overflow-x-auto text-xs leading-5 text-slate-200"><code>// app/Services/StatisticsService.php (core path)
$pooledVariance = (($n1 - 1) * $var1 + ($n2 - 1) * $var2) / $df;
$standardError = sqrt(max(0, $pooledVariance) * ((1 / $n1) + (1 / $n2)));
$tValue = ($mean1 - $mean2) / $standardError;
$criticalValue = 1.96;
$isSignificant = abs($tValue) > $criticalValue;</code></pre>
                    </div>
                    <ul class="mt-3 list-disc space-y-1 pl-5 text-sm text-slate-300">
                        <li>{!! $t('Sample variance memakai denominator <code>(n-1)</code>.', 'Sample variance uses denominator <code>(n-1)</code>.') !!}</li>
                        <li>{{ $t('Syarat minimum: tiap grup minimal memiliki 2 baris.', 'Minimum requirement: each group must have at least 2 rows.') }}</li>
                        <li>{!! $t('Edge case <code>standardError == 0</code> ditangani eksplisit (grup konstan).', 'Edge case <code>standardError == 0</code> is handled explicitly (constant groups).') !!}</li>
                        <li>{!! $t('<code>p_value</code> memakai fungsi aproksimasi (normal CDF untuk df besar, bin kasar untuk df kecil).', '<code>p_value</code> uses approximation function (normal CDF for large df, coarse bins for small df).') !!}</li>
                        <li>{{ $t('Dashboard menampilkan panel T-test terpisah untuk latency dan power.', 'Dashboard renders separate T-test panels for latency and power.') }}</li>
                    </ul>
                </section>

                {{-- SECTION 10: Database schema + relations --}}
                <section id="database" class="doc-card rounded-2xl border border-white/10 p-4 shadow-glow sm:p-5 lg:p-6">
                    <h2 class="text-lg font-semibold sm:text-xl">{{ $t('Struktur Database dan Relasi Tabel', 'Database Structure and Table Relations') }}</h2>
                    <div class="mt-3 overflow-x-auto rounded-xl border border-white/10 bg-slate-950 p-4">
<pre class="text-xs leading-5 text-slate-200">
devices (id PK)
  |-< eksperimens.device_id FK (1:N)
  |-< simulated_eksperimens.device_id FK (1:N)
  '- 1:1 device_firmware_profiles.device_id FK (unique)

app_settings (standalone key-value runtime overrides)
</pre>
                    </div>
                    <div class="mt-4 grid gap-3 text-sm md:grid-cols-2">
                        <div class="rounded-xl border border-white/10 bg-slate-800/50 p-4">
                            <p class="font-medium text-brand-300">{!! $t('Tabel telemetry (<code>eksperimens</code>)', 'Telemetry table (<code>eksperimens</code>)') !!}</p>
                            <p class="mt-2 text-slate-300">{!! $t('Kolom inti: <code>device_id</code>, <code>protokol</code>, <code>suhu</code>, <code>kelembapan</code>, <code>timestamp_esp</code>, <code>timestamp_server</code>, <code>latency_ms</code>, <code>daya_mw</code>, plus kolom diagnostik lainnya.', 'Core columns: <code>device_id</code>, <code>protokol</code>, <code>suhu</code>, <code>kelembapan</code>, <code>timestamp_esp</code>, <code>timestamp_server</code>, <code>latency_ms</code>, <code>daya_mw</code>, plus additional diagnostics columns.') !!}</p>
                            <p class="mt-2 text-slate-300">{!! $t('Unique key untuk idempotent ingestion: <code>(device_id, protokol, packet_seq)</code>.', 'Unique key for idempotent ingestion: <code>(device_id, protokol, packet_seq)</code>.') !!}</p>
                        </div>
                        <div class="rounded-xl border border-white/10 bg-slate-800/50 p-4">
                            <p class="font-medium text-brand-300">{{ $t('Tabel telemetry simulasi', 'Simulation telemetry table') }}</p>
                            <p class="mt-2 text-slate-300">{!! $t('<code>simulated_eksperimens</code> mencerminkan bentuk telemetry yang sama dengan unique key yang sama, namun dipisah dari tabel telemetry real.', '<code>simulated_eksperimens</code> mirrors the same telemetry shape with the same unique key, but remains isolated from real telemetry table.') !!}</p>
                        </div>
                    </div>

                    <p class="mt-4 text-sm font-medium text-brand-300">{{ $t('Contoh query (troubleshooting runtime)', 'Query examples (runtime troubleshooting)') }}</p>
                    <div class="mt-2 rounded-xl border border-white/10 bg-slate-950 p-4">
<pre class="overflow-x-auto text-xs leading-5 text-slate-200"><code>-- Latest real rows by protocol
SELECT id, device_id, protokol, packet_seq, latency_ms, daya_mw, timestamp_server
FROM eksperimens
WHERE protokol IN ('MQTT', 'HTTP')
ORDER BY COALESCE(timestamp_server, created_at) DESC
LIMIT 20;

-- Required field completeness audit for MQTT scope
SELECT
  COUNT(*) AS total_rows,
  SUM(CASE WHEN suhu IS NULL THEN 1 ELSE 0 END) AS missing_suhu,
  SUM(CASE WHEN kelembapan IS NULL THEN 1 ELSE 0 END) AS missing_kelembapan,
  SUM(CASE WHEN packet_seq IS NULL THEN 1 ELSE 0 END) AS missing_packet_seq
FROM eksperimens
WHERE UPPER(protokol) = 'MQTT';</code></pre>
                    </div>
                </section>

                {{-- SECTION 11: Data flow to dashboard and component map --}}
                <section id="flow" class="doc-card rounded-2xl border border-white/10 p-4 shadow-glow sm:p-5 lg:p-6">
                    <h2 class="text-lg font-semibold sm:text-xl">{{ $t('Alur: Data Masuk sampai Render Dashboard', 'Flow: Data Ingest to Dashboard Rendering') }}</h2>
                    <ol class="mt-3 list-decimal space-y-2 pl-5 text-sm text-slate-300">
                        <li>{{ $t('ESP32 mengirim payload HTTP dan MQTT dengan field telemetry lengkap.', 'ESP32 sends HTTP and MQTT payloads with full telemetry fields.') }}</li>
                        <li>{!! $t('Jalur HTTP masuk ke <code>ApiController</code>; jalur MQTT masuk ke <code>mqtt_worker.php</code>.', 'HTTP path enters <code>ApiController</code>; MQTT path enters <code>mqtt_worker.php</code>.') !!}</li>
                        <li>{!! $t('Kedua jalur menghitung latency dan melakukan idempotent upsert ke <code>eksperimens</code>.', 'Both paths compute latency and perform idempotent upsert into <code>eksperimens</code>.') !!}</li>
                        <li>{!! $t('<code>DashboardController@index</code> mengambil sampel MQTT dan HTTP (window analisis: ' . $analysisWindow . ', window chart: ' . ($chartWindow === 0 ? 'tidak terbatas' : $chartWindow) . ').', '<code>DashboardController@index</code> fetches MQTT and HTTP samples (analysis window: ' . $analysisWindow . ', chart window: ' . ($chartWindow === 0 ? 'unlimited' : $chartWindow) . ').') !!}</li>
                        <li>{!! $t('<code>StatisticsService</code> menghitung summary stats, reliability, dan T-test.', '<code>StatisticsService</code> computes summary stats, reliability, and T-test.') !!}</li>
                        <li>{{ $t('View dashboard me-render metric cards, warning list, panel diagnostik, chart latency, chart power, dan section analisis statistik.', 'Dashboard view renders metric cards, warning list, diagnostics panel, latency chart, power chart, and statistical analysis section.') }}</li>
                        <li>{{ $t('Auto-refresh frontend memperbarui cards, charts, dan diagnostics secara periodik.', 'Frontend auto-refresh updates cards, charts, and diagnostics periodically.') }}</li>
                    </ol>

                    <div class="mt-4 rounded-xl border border-white/10 bg-slate-800/50 p-4 text-sm">
                        <p class="font-medium text-brand-300">{{ $t('Komponen statistik dashboard yang saat ini ditampilkan', 'Dashboard statistics components currently displayed') }}</p>
                        <ul class="mt-2 list-disc space-y-1 pl-5 text-slate-300">
                            <li>{{ $t('Header metrics: rata-rata temperatur dan rata-rata kelembapan.', 'Header metrics: average temperature and average humidity.') }}</li>
                            <li>{{ $t('Realtime badges: ESP32 ON/OFF, MQTT Connected/Disconnected, HTTP Connected/Disconnected.', 'Realtime badges: ESP32 ON/OFF, MQTT Connected/Disconnected, HTTP Connected/Disconnected.') }}</li>
                            <li>{{ $t('8 protocol cards: total data, avg latency, avg power, reliability (MQTT + HTTP).', '8 protocol cards: total data, avg latency, avg power, reliability (MQTT + HTTP).') }}</li>
                            <li>{{ $t('Data quality warnings dan panel field completeness.', 'Data quality warnings and field completeness panel.') }}</li>
                            <li>{{ $t('Protocol payload diagnostics (row MQTT/HTTP terbaru + nilai delta).', 'Protocol payload diagnostics (latest MQTT/HTTP rows + delta values).') }}</li>
                            <li>{{ $t('Chart komparatif: latency dan power.', 'Comparative charts: latency and power.') }}</li>
                            <li>{{ $t('Hasil independent sample T-test untuk latency dan power.', 'Independent sample T-test results for latency and power.') }}</li>
                            <li>{{ $t('Widget floating realtime link monitor (ping/throughput).', 'Floating realtime link monitor widget (ping/throughput).') }}</li>
                        </ul>
                    </div>
                </section>

                {{-- SECTION 12: Validation checks --}}
                <section id="validation" class="doc-card rounded-2xl border border-white/10 p-4 shadow-glow sm:p-5 lg:p-6">
                    <h2 class="text-lg font-semibold sm:text-xl">{{ $t('Validasi pada Ingest dan Statistik Backend', 'Validation Used by Backend Statistics and Ingest') }}</h2>
                    <div class="mt-3 grid gap-3 text-sm md:grid-cols-2">
                        <div class="rounded-xl border border-white/10 bg-slate-800/50 p-4">
                            <p class="font-medium text-brand-300">{{ $t('Validasi ingest', 'Ingest validation') }}</p>
                            <ul class="mt-2 list-disc space-y-1 pl-5 text-slate-300">
                                <li>{!! $t('<code>device_id</code> harus ada di tabel <code>devices</code>.', '<code>device_id</code> must exist in <code>devices</code>.') !!}</li>
                                <li>{!! $t('<code>kelembapan</code> dibatasi pada rentang 0..100.', '<code>kelembapan</code> constrained to 0..100.') !!}</li>
                                <li>{!! $t('<code>timestamp_esp</code> dibatasi pada rentang epoch 1000000000..4102444800.', '<code>timestamp_esp</code> constrained to epoch range 1000000000..4102444800.') !!}</li>
                                <li>{!! $t('<code>packet_seq</code> harus >= 1.', '<code>packet_seq</code> must be >= 1.') !!}</li>
                                <li>{!! $t('<code>rssi_dbm</code> dibatasi pada rentang -120..0.', '<code>rssi_dbm</code> constrained to -120..0.') !!}</li>
                                <li>{{ $t('Jalur HTTP memakai validator Laravel; jalur MQTT memakai pengecekan manual di worker.', 'HTTP path uses Laravel validator; MQTT path uses manual checks in worker.') }}</li>
                            </ul>
                        </div>
                        <div class="rounded-xl border border-white/10 bg-slate-800/50 p-4">
                            <p class="font-medium text-brand-300">{{ $t('Aturan validasi statistik', 'Statistical validation rules') }}</p>
                            <ul class="mt-2 list-disc space-y-1 pl-5 text-slate-300">
                                <li>{{ $t('T-test mensyaratkan minimal 2 baris data per protokol.', 'T-test requires at least 2 rows per protocol.') }}</li>
                                <li>{{ $t('Menolak kasus df=0 dan standard error denominator=0.', 'Rejects df=0 and standard error denominator=0 cases.') }}</li>
                                <li>{!! $t('Scope reliability memprioritaskan baris dengan <code>packet_seq</code> non-null saat tersedia.', 'Reliability scope prioritizes rows with non-null <code>packet_seq</code> when available.') !!}</li>
                                <li>{{ $t('Kelengkapan field wajib mengecek 15 field telemetry yang konsisten untuk MQTT dan HTTP.', 'Required field completeness checks 15 telemetry fields consistently for MQTT and HTTP.') }}</li>
                            </ul>
                        </div>
                    </div>
                </section>

                {{-- SECTION 13: Known limitations --}}
                <section id="limits" class="doc-card rounded-2xl border border-white/10 p-4 shadow-glow sm:p-5 lg:p-6">
                    <h2 class="text-lg font-semibold sm:text-xl">{{ $t('Batasan Sistem (Berdasarkan Kode Saat Ini)', 'System Limitations (Based on Current Code)') }}</h2>
                    <ul class="mt-3 list-disc space-y-1 pl-5 text-sm text-slate-300">
                        <li>{!! $t('<code>p_value</code> pada T-test memakai aproksimasi, terutama kasar untuk df kecil.', '<code>p_value</code> in T-test uses approximation, especially coarse for small df.') !!}</li>
                        <li>{{ $t('Critical value di kode bersifat tetap (+/-1.96), tidak menyesuaikan df secara dinamis.', 'Critical value is fixed at +/-1.96 in code, not dynamically adjusted by df.') }}</li>
                        <li>{!! $t('<code>daya_mw</code> adalah estimasi sisi firmware, bukan pembacaan sensor listrik langsung.', '<code>daya_mw</code> is a firmware-side estimate, not direct electrical sensor measurement.') !!}</li>
                        <li>{{ $t('Latency bergantung pada validitas jam ESP32; drift NTP atau epoch tidak sinkron akan menurunkan kualitas metrik.', 'Latency depends on ESP32 clock validity; NTP drift or unsynced epoch affects metric quality.') }}</li>
                        <li>{{ $t('Sequence reliability memakai aturan segmentasi (reboot/jump handling), sehingga lompatan sangat besar diperlakukan sebagai segmen baru, bukan loss kontinu.', 'Sequence reliability uses segmentation rules (reboot/jump handling), so very large jumps become new segments instead of continuous loss.') }}</li>
                        <li>{{ $t('Ingestion MQTT bergantung pada proses worker mandiri; jika worker down, telemetry MQTT tidak tersimpan.', 'MQTT ingestion depends on standalone worker process availability; if worker is down, MQTT telemetry is not persisted.') }}</li>
                        <li>{{ $t('Dokumentasi ini implementation-bound; update halaman ini saat schema telemetry, formula, atau runtime path berubah.', 'This documentation is implementation-bound; update this page whenever telemetry schema, formulas, or runtime paths change.') }}</li>
                    </ul>
                </section>
            </main>
        </div>
    </div>

    <a href="#top" class="fixed bottom-4 right-4 rounded-full border border-white/15 bg-slate-900/90 px-3 py-2 text-xs font-medium text-slate-100 shadow-glow hover:border-brand-400 hover:text-white">
        {{ $t('Ke Atas', 'Top') }}
    </a>

    <script>
        // Toggle blur only when mobile/tablet nav is actually stuck at the top.
        (function () {
            const nav = document.getElementById('mobile-doc-nav');
            const sentinel = document.getElementById('mobile-doc-nav-sentinel');

            if (!nav || !sentinel || !('IntersectionObserver' in window)) {
                return;
            }

            const observer = new IntersectionObserver((entries) => {
                const entry = entries[0];
                nav.dataset.stuck = entry && entry.isIntersecting ? 'false' : 'true';
            }, {
                root: null,
                threshold: 0,
                rootMargin: '0px'
            });

            observer.observe(sentinel);

            window.addEventListener('beforeunload', () => observer.disconnect(), { once: true });
        })();
    </script>
</body>
</html>


