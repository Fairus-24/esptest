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
            <a href="#simple-flow" class="rounded-full bg-slate-800 px-3 py-1.5">{{ $t('Alur Sederhana', 'Simple Flow') }}</a>
            <a href="#overview" class="rounded-full bg-slate-800 px-3 py-1.5">{{ $t('Ringkasan', 'Overview') }}</a>
            <a href="#data-flow" class="rounded-full bg-slate-800 px-3 py-1.5">{{ $t('Alur Data', 'Data Flow') }}</a>
            <a href="#architecture" class="rounded-full bg-slate-800 px-3 py-1.5">{{ $t('Arsitektur', 'Architecture') }}</a>
            <a href="#timeline" class="rounded-full bg-slate-800 px-3 py-1.5">{{ $t('Timeline', 'Timeline') }}</a>
            <a href="#payload" class="rounded-full bg-slate-800 px-3 py-1.5">Payload</a>
            <a href="#metrics" class="rounded-full bg-slate-800 px-3 py-1.5">{{ $t('Metrik', 'Metrics') }}</a>
            <a href="#examples" class="rounded-full bg-slate-800 px-3 py-1.5">{{ $t('Contoh', 'Examples') }}</a>
            <a href="#why-design" class="rounded-full bg-slate-800 px-3 py-1.5">{{ $t('Alasan Desain', 'Design Reasons') }}</a>
            <a href="#database" class="rounded-full bg-slate-800 px-3 py-1.5">Database</a>
            <a href="#flow" class="rounded-full bg-slate-800 px-3 py-1.5">{{ $t('Ke Dashboard', 'To Dashboard') }}</a>
            <a href="#limits" class="rounded-full bg-slate-800 px-3 py-1.5">{{ $t('Batasan', 'Limits') }}</a>
            <a href="#glossary" class="rounded-full bg-slate-800 px-3 py-1.5">Glossary</a>
        </nav>

        <div class="grid gap-6 lg:grid-cols-[18rem_minmax(0,1fr)]">
            {{-- Sidebar navigation for desktop --}}
            <aside class="hidden lg:block">
                <div class="sticky top-4 rounded-2xl border border-white/10 bg-slate-900/70 p-4 shadow-glow">
                    <p class="mb-3 text-xs uppercase tracking-[0.2em] text-brand-300">{{ $t('Navigasi', 'Navigation') }}</p>
                    <nav class="space-y-2 text-sm">
                        <a href="#simple-flow" class="block rounded-lg px-2 py-1.5 hover:bg-white/5">{{ $t('Alur Sistem Sederhana', 'Simple System Flow') }}</a>
                        <a href="#overview" class="block rounded-lg px-2 py-1.5 hover:bg-white/5">{{ $t('System Overview', 'System Overview') }}</a>
                        <a href="#data-flow" class="block rounded-lg px-2 py-1.5 hover:bg-white/5">{{ $t('Bagaimana Data Mengalir', 'How Data Flows') }}</a>
                        <a href="#architecture" class="block rounded-lg px-2 py-1.5 hover:bg-white/5">{{ $t('Arsitektur Aktual', 'Actual Architecture') }}</a>
                        <a href="#timeline" class="block rounded-lg px-2 py-1.5 hover:bg-white/5">{{ $t('Timeline HTTP vs MQTT', 'HTTP vs MQTT Timeline') }}</a>
                        <a href="#routes" class="block rounded-lg px-2 py-1.5 hover:bg-white/5">Laravel Routes</a>
                        <a href="#ingress" class="block rounded-lg px-2 py-1.5 hover:bg-white/5">{{ $t('HTTP & MQTT Ingress', 'HTTP & MQTT Ingress') }}</a>
                        <a href="#payload" class="block rounded-lg px-2 py-1.5 hover:bg-white/5">JSON Payload</a>
                        <a href="#metrics" class="block rounded-lg px-2 py-1.5 hover:bg-white/5">{{ $t('Penjelasan Metrik Bertahap', 'Step-by-Step Metric Explanation') }}</a>
                        <a href="#examples" class="block rounded-lg px-2 py-1.5 hover:bg-white/5">{{ $t('Contoh Nyata', 'Real Examples') }}</a>
                        <a href="#why-design" class="block rounded-lg px-2 py-1.5 hover:bg-white/5">{{ $t('Kenapa Desain Ini', 'Why This Design') }}</a>
                        <a href="#database" class="block rounded-lg px-2 py-1.5 hover:bg-white/5">{{ $t('Database & Relasi', 'Database & Relations') }}</a>
                        <a href="#flow" class="block rounded-lg px-2 py-1.5 hover:bg-white/5">{{ $t('Alur Data ke Dashboard', 'Data-to-Dashboard Flow') }}</a>
                        <a href="#validation" class="block rounded-lg px-2 py-1.5 hover:bg-white/5">{{ $t('Validasi Statistik', 'Statistical Validation') }}</a>
                        <a href="#limits" class="block rounded-lg px-2 py-1.5 hover:bg-white/5">{{ $t('Batasan Sistem', 'System Limitations') }}</a>
                        <a href="#glossary" class="block rounded-lg px-2 py-1.5 hover:bg-white/5">Glossary / {{ $t('Istilah', 'Terms') }}</a>
                    </nav>
                </div>
            </aside>

            <main class="min-w-0 space-y-5 sm:space-y-6">
                {{-- SECTION 0A: Simple flow for non-technical readers --}}
                <section id="simple-flow" class="doc-card rounded-2xl border border-white/10 p-4 shadow-glow sm:p-5 lg:p-6">
                    <h2 class="text-lg font-semibold sm:text-xl">{{ $t('Alur Sistem Secara Sederhana', 'System Flow in Simple Language') }}</h2>
                    <p class="mt-3 text-sm text-slate-300">{{ $t('Bayangkan ada dua kurir yang mengantar pesan dari ESP32 ke server: kurir HTTP dan kurir MQTT. Keduanya membawa isi pesan yang sama, lalu server menyimpan hasil kiriman, menghitung kualitas pengiriman, dan menampilkan perbandingan keduanya di dashboard.', 'Imagine two couriers delivering messages from ESP32 to the server: HTTP courier and MQTT courier. Both carry similar message content, then the server stores each delivery, calculates delivery quality, and shows the comparison on the dashboard.') }}</p>
                    <p class="mt-2 text-sm text-slate-300">{!! $t('Secara teknis, firmware di <code>ESP32_Firmware/src/main.cpp</code> mengirim payload periodik ke <code>POST /api/http-data</code> (HTTP) dan ke broker topic <code>' . config('mqtt.topic', 'iot/esp32/suhu') . '</code> (MQTT). Jalur HTTP diproses <code>ApiController::storeHttp</code>, jalur MQTT diproses worker <code>mqtt_worker.php</code>, lalu keduanya tersimpan idempotent ke tabel <code>eksperimens</code>.', 'Technically, firmware in <code>ESP32_Firmware/src/main.cpp</code> sends periodic payloads to <code>POST /api/http-data</code> (HTTP) and broker topic <code>' . config('mqtt.topic', 'iot/esp32/suhu') . '</code> (MQTT). HTTP path is handled by <code>ApiController::storeHttp</code>, MQTT path is handled by worker <code>mqtt_worker.php</code>, then both are stored idempotently in <code>eksperimens</code>.') !!}</p>

                    <div class="mt-4 grid gap-3 md:grid-cols-2 xl:grid-cols-4">
                        <div class="rounded-xl border border-white/10 bg-slate-800/50 p-3 text-sm">
                            <p class="font-semibold text-brand-300">1. ESP32</p>
                            <p class="mt-1 text-slate-300">{{ $t('Membaca sensor lalu menyiapkan paket data.', 'Reads sensor and prepares telemetry packet.') }}</p>
                        </div>
                        <div class="rounded-xl border border-white/10 bg-slate-800/50 p-3 text-sm">
                            <p class="font-semibold text-brand-300">2. HTTP + MQTT</p>
                            <p class="mt-1 text-slate-300">{{ $t('Paket dikirim lewat dua jalur berbeda.', 'Packet is sent through two different channels.') }}</p>
                        </div>
                        <div class="rounded-xl border border-white/10 bg-slate-800/50 p-3 text-sm">
                            <p class="font-semibold text-brand-300">3. Database</p>
                            <p class="mt-1 text-slate-300">{{ $t('Server menyimpan hasil kiriman dan menghitung metrik.', 'Server stores deliveries and calculates metrics.') }}</p>
                        </div>
                        <div class="rounded-xl border border-white/10 bg-slate-800/50 p-3 text-sm">
                            <p class="font-semibold text-brand-300">4. Dashboard</p>
                            <p class="mt-1 text-slate-300">{{ $t('Hasil perbandingan tampil realtime untuk analisis.', 'Comparison results are shown in realtime for analysis.') }}</p>
                        </div>
                    </div>
                </section>

                {{-- SECTION 0B: Data flow explanation with two-layer style --}}
                <section id="data-flow" class="doc-card rounded-2xl border border-white/10 p-4 shadow-glow sm:p-5 lg:p-6">
                    <h2 class="text-lg font-semibold sm:text-xl">{{ $t('Bagaimana Data Mengalir di Sistem Ini', 'How Data Flows in This System') }}</h2>
                    <p class="mt-3 text-sm text-slate-300">{{ $t('Bahasa sederhana: data bergerak dari alat (ESP32), masuk ke server, disimpan di database, dihitung statistiknya, lalu ditampilkan di dashboard.', 'Simple view: data moves from device (ESP32), enters server, is stored in database, gets statistical processing, and is finally shown on dashboard.') }}</p>
                    <p class="mt-2 text-sm text-slate-300">{!! $t('Versi teknis: <code>sendHTTP()</code> dan <code>sendMQTT()</code> di firmware mengirim payload; HTTP diterima <code>ApiController::storeHttp</code>, MQTT diterima callback di <code>mqtt_worker.php</code>; data masuk <code>Eksperimen::updateOrCreate</code>; dashboard memanggil <code>StatisticsService</code> dari <code>DashboardController::index</code>.', 'Technical view: firmware <code>sendHTTP()</code> and <code>sendMQTT()</code> push payloads; HTTP is accepted by <code>ApiController::storeHttp</code>, MQTT is accepted by callback in <code>mqtt_worker.php</code>; data is written via <code>Eksperimen::updateOrCreate</code>; dashboard calls <code>StatisticsService</code> from <code>DashboardController::index</code>.') !!}</p>

                    <div class="mt-4 overflow-x-auto rounded-xl border border-white/10 bg-slate-950 p-4">
<pre class="text-xs leading-5 text-slate-200">
[ESP32 Sensor Read]
        |
        +--> HTTP -> /api/http-data -> ApiController -> eksperimens
        |
        +--> MQTT -> Broker -> mqtt_worker.php -> eksperimens
                                              |
                                              v
                                    StatisticsService (summary, reliability, t-test)
                                              |
                                              v
                                    DashboardController -> dashboard.blade.php
</pre>
                    </div>

                    <div class="mt-4 overflow-x-auto rounded-xl border border-white/10">
                        <table class="min-w-full text-left text-xs sm:text-sm">
                            <thead class="bg-slate-800/60 text-slate-100">
                                <tr>
                                    <th class="px-3 py-2">{{ $t('Langkah', 'Step') }}</th>
                                    <th class="px-3 py-2">{{ $t('Bahasa Sederhana', 'Simple Explanation') }}</th>
                                    <th class="px-3 py-2">{{ $t('Penjelasan Teknis', 'Technical Explanation') }}</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-white/10 text-slate-300">
                                <tr>
                                    <td class="px-3 py-2 font-medium">1</td>
                                    <td class="px-3 py-2">{{ $t('ESP32 membaca suhu dan kelembapan.', 'ESP32 reads temperature and humidity.') }}</td>
                                    <td class="px-3 py-2">{!! $t('Firmware menyimpan ke <code>lastTemperature</code>/<code>lastHumidity</code> lewat <code>captureSensorSnapshot()</code>.', 'Firmware stores values in <code>lastTemperature</code>/<code>lastHumidity</code> via <code>captureSensorSnapshot()</code>.') !!}</td>
                                </tr>
                                <tr>
                                    <td class="px-3 py-2 font-medium">2</td>
                                    <td class="px-3 py-2">{{ $t('Data yang sama konsepnya dikirim lewat dua jalur.', 'Conceptually similar data is sent through two channels.') }}</td>
                                    <td class="px-3 py-2">{!! $t('<code>sendHTTP()</code> kirim ke endpoint API, <code>sendMQTT()</code> publish ke topic broker.', '<code>sendHTTP()</code> posts to API endpoint, <code>sendMQTT()</code> publishes to broker topic.') !!}</td>
                                </tr>
                                <tr>
                                    <td class="px-3 py-2 font-medium">3</td>
                                    <td class="px-3 py-2">{{ $t('Server mengecek apakah data valid.', 'Server checks whether payload is valid.') }}</td>
                                    <td class="px-3 py-2">{{ $t('HTTP memakai validator Laravel, MQTT worker memakai validasi manual field/range.', 'HTTP uses Laravel validator, MQTT worker uses manual field/range validation.') }}</td>
                                </tr>
                                <tr>
                                    <td class="px-3 py-2 font-medium">4</td>
                                    <td class="px-3 py-2">{{ $t('Data disimpan tanpa menggandakan paket yang sama.', 'Data is stored without duplicating the same packet.') }}</td>
                                    <td class="px-3 py-2">{!! $t('Kunci idempotent: <code>(device_id, protokol, packet_seq)</code> melalui <code>updateOrCreate</code> + unique index DB.', 'Idempotent key: <code>(device_id, protokol, packet_seq)</code> using <code>updateOrCreate</code> + DB unique index.') !!}</td>
                                </tr>
                                <tr>
                                    <td class="px-3 py-2 font-medium">5</td>
                                    <td class="px-3 py-2">{{ $t('Sistem menghitung statistik perbandingan.', 'System computes comparative statistics.') }}</td>
                                    <td class="px-3 py-2">{!! $t('<code>StatisticsService</code> menghitung summary, reliability/PDR, dan independent sample T-test.', '<code>StatisticsService</code> computes summary, reliability/PDR, and independent sample T-test.') !!}</td>
                                </tr>
                                <tr>
                                    <td class="px-3 py-2 font-medium">6</td>
                                    <td class="px-3 py-2">{{ $t('Hasil tampil di dashboard untuk dibaca manusia.', 'Results are shown on dashboard for human interpretation.') }}</td>
                                    <td class="px-3 py-2">{{ $t('Dashboard menampilkan card metrik, quality panel, diagnostics, chart latency/daya, dan panel T-test.', 'Dashboard renders metric cards, quality panel, diagnostics, latency/power charts, and T-test panels.') }}</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </section>

                {{-- SECTION 1: System Overview --}}
                <section id="overview" class="doc-card rounded-2xl border border-white/10 p-4 shadow-glow sm:p-5 lg:p-6">
                    <h2 class="text-lg font-semibold sm:text-xl">System Overview</h2>
                    <p class="mt-3 text-sm text-slate-300">{{ $t('Untuk pembaca awam: halaman ini menjelaskan siapa mengirim data, ke mana data pergi, apa yang dihitung, dan bagaimana hasilnya dibaca di dashboard.', 'For general readers: this page explains who sends the data, where it goes, what is calculated, and how the results are read on dashboard.') }}</p>
                    <p class="mt-2 text-sm text-slate-300">{{ $t('Untuk pembaca teknis: sistem membandingkan telemetry MQTT vs HTTP dari ESP32, menyimpan keduanya ke MySQL, lalu mengeksekusi statistik komparatif (summary, reliability, t-test) sebelum render chart dan panel analisis.', 'For technical readers: the system compares MQTT vs HTTP telemetry from ESP32, stores both in MySQL, then runs comparative statistics (summary, reliability, t-test) before rendering charts and analysis panels.') }}</p>
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
                    <p class="mt-3 text-sm text-slate-300">{{ $t('Versi awam: ada dua jalur pengiriman dari ESP32, lalu kedua jalur bertemu di database untuk dianalisis bersama.', 'Simple view: there are two delivery channels from ESP32, then both channels meet in database for joint analysis.') }}</p>
                    <p class="mt-2 text-sm text-slate-300">{{ $t('Versi teknis: diagram berikut mengikuti jalur runtime yang benar-benar ada di repository ini tanpa asumsi tambahan di luar kode.', 'Technical view: the diagram below follows runtime paths that actually exist in this repository with no assumptions beyond code.') }}</p>

                    <div class="mt-4 rounded-xl border border-white/10 bg-slate-950/70 p-3 sm:p-4">
                        <svg viewBox="0 0 980 430" class="h-auto w-full" role="img" aria-label="System architecture flow SVG">
                            <defs>
                                <linearGradient id="gradNode" x1="0%" y1="0%" x2="100%" y2="100%">
                                    <stop offset="0%" stop-color="#1e293b" />
                                    <stop offset="100%" stop-color="#0f172a" />
                                </linearGradient>
                                <linearGradient id="gradBackend" x1="0%" y1="0%" x2="100%" y2="100%">
                                    <stop offset="0%" stop-color="#1d4ed8" stop-opacity="0.24" />
                                    <stop offset="100%" stop-color="#0ea5e9" stop-opacity="0.12" />
                                </linearGradient>
                                <marker id="arrowDoc" viewBox="0 0 10 10" refX="8" refY="5" markerWidth="7" markerHeight="7" orient="auto-start-reverse">
                                    <path d="M 0 0 L 10 5 L 0 10 z" fill="#60a5fa" />
                                </marker>
                            </defs>

                            <rect x="40" y="170" width="150" height="72" rx="12" fill="url(#gradNode)" stroke="#334155" />
                            <text x="115" y="197" fill="#e2e8f0" font-size="14" text-anchor="middle" font-weight="600">ESP32</text>
                            <text x="115" y="218" fill="#94a3b8" font-size="11" text-anchor="middle">main.cpp</text>

                            <rect x="245" y="86" width="180" height="72" rx="12" fill="url(#gradNode)" stroke="#334155" />
                            <text x="335" y="113" fill="#e2e8f0" font-size="13" text-anchor="middle" font-weight="600">HTTP Path</text>
                            <text x="335" y="134" fill="#94a3b8" font-size="11" text-anchor="middle">POST /api/http-data</text>

                            <rect x="245" y="252" width="180" height="72" rx="12" fill="url(#gradNode)" stroke="#334155" />
                            <text x="335" y="279" fill="#e2e8f0" font-size="13" text-anchor="middle" font-weight="600">MQTT Path</text>
                            <text x="335" y="300" fill="#94a3b8" font-size="11" text-anchor="middle">topic {{ config('mqtt.topic', 'iot/esp32/suhu') }}</text>

                            <rect x="470" y="70" width="220" height="290" rx="16" fill="url(#gradBackend)" stroke="#2563eb" stroke-dasharray="6 5" />
                            <text x="580" y="94" fill="#bfdbfe" font-size="12" text-anchor="middle" font-weight="600">Backend Processing</text>

                            <rect x="495" y="108" width="170" height="62" rx="10" fill="url(#gradNode)" stroke="#334155" />
                            <text x="580" y="132" fill="#e2e8f0" font-size="12" text-anchor="middle" font-weight="600">ApiController::storeHttp</text>
                            <text x="580" y="151" fill="#94a3b8" font-size="10.5" text-anchor="middle">throttle + ingest.key</text>

                            <rect x="495" y="188" width="170" height="62" rx="10" fill="url(#gradNode)" stroke="#334155" />
                            <text x="580" y="212" fill="#e2e8f0" font-size="12" text-anchor="middle" font-weight="600">mqtt_worker.php</text>
                            <text x="580" y="231" fill="#94a3b8" font-size="10.5" text-anchor="middle">subscribe + validate</text>

                            <rect x="495" y="268" width="170" height="62" rx="10" fill="url(#gradNode)" stroke="#334155" />
                            <text x="580" y="292" fill="#e2e8f0" font-size="12" text-anchor="middle" font-weight="600">Latency Calculation</text>
                            <text x="580" y="311" fill="#94a3b8" font-size="10.5" text-anchor="middle">abs(server_utc - timestamp_esp)</text>

                            <rect x="730" y="152" width="170" height="78" rx="12" fill="url(#gradNode)" stroke="#334155" />
                            <text x="815" y="180" fill="#e2e8f0" font-size="13" text-anchor="middle" font-weight="600">MySQL</text>
                            <text x="815" y="200" fill="#94a3b8" font-size="11" text-anchor="middle">eksperimens</text>
                            <text x="815" y="216" fill="#94a3b8" font-size="11" text-anchor="middle">updateOrCreate + unique key</text>

                            <rect x="730" y="260" width="170" height="68" rx="12" fill="url(#gradNode)" stroke="#334155" />
                            <text x="815" y="286" fill="#e2e8f0" font-size="12.5" text-anchor="middle" font-weight="600">StatisticsService</text>
                            <text x="815" y="305" fill="#94a3b8" font-size="10.5" text-anchor="middle">summary + reliability + tTest()</text>

                            <rect x="730" y="348" width="170" height="54" rx="12" fill="url(#gradNode)" stroke="#334155" />
                            <text x="815" y="377" fill="#e2e8f0" font-size="12.5" text-anchor="middle" font-weight="600">Dashboard</text>
                            <text x="815" y="392" fill="#94a3b8" font-size="10.5" text-anchor="middle">DashboardController + Blade</text>

                            <line x1="190" y1="193" x2="245" y2="122" stroke="#60a5fa" stroke-width="2.2" marker-end="url(#arrowDoc)" />
                            <line x1="190" y1="219" x2="245" y2="286" stroke="#60a5fa" stroke-width="2.2" marker-end="url(#arrowDoc)" />

                            <line x1="425" y1="122" x2="495" y2="139" stroke="#60a5fa" stroke-width="2.2" marker-end="url(#arrowDoc)" />
                            <line x1="425" y1="286" x2="495" y2="219" stroke="#60a5fa" stroke-width="2.2" marker-end="url(#arrowDoc)" />

                            <line x1="580" y1="170" x2="580" y2="188" stroke="#60a5fa" stroke-width="2.2" marker-end="url(#arrowDoc)" />
                            <line x1="580" y1="250" x2="580" y2="268" stroke="#60a5fa" stroke-width="2.2" marker-end="url(#arrowDoc)" />

                            <line x1="665" y1="299" x2="730" y2="191" stroke="#60a5fa" stroke-width="2.2" marker-end="url(#arrowDoc)" />
                            <line x1="815" y1="230" x2="815" y2="260" stroke="#60a5fa" stroke-width="2.2" marker-end="url(#arrowDoc)" />
                            <line x1="815" y1="328" x2="815" y2="348" stroke="#60a5fa" stroke-width="2.2" marker-end="url(#arrowDoc)" />
                        </svg>
                        <p class="mt-2 text-xs text-slate-400">{{ $t('Diagram SVG inline ini mengikuti jalur kode aktual: perhitungan latency terjadi di ingest backend (HTTP controller + MQTT worker), sedangkan T-Test terjadi di StatisticsService sebelum dashboard dirender.', 'This inline SVG follows actual code paths: latency is calculated in backend ingest (HTTP controller + MQTT worker), while T-Test runs in StatisticsService before dashboard rendering.') }}</p>
                    </div>

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

                {{-- SECTION 2B: HTTP vs MQTT timeline --}}
                <section id="timeline" class="doc-card rounded-2xl border border-white/10 p-4 shadow-glow sm:p-5 lg:p-6">
                    <h2 class="text-lg font-semibold sm:text-xl">{{ $t('Timeline Visual: HTTP Request/Response vs MQTT Publish/Subscribe', 'Visual Timeline: HTTP Request/Response vs MQTT Publish/Subscribe') }}</h2>
                    <p class="mt-3 text-sm text-slate-300">{{ $t('Versi awam: kedua protokol berangkat dari titik waktu sensor yang sama, tetapi pola perjalanannya berbeda. HTTP menunggu balasan request, sedangkan MQTT publish lalu diproses subscriber worker.', 'Simple view: both protocols start from sensor timestamp, but the travel pattern differs. HTTP waits for request response, while MQTT publishes and is then processed by subscriber worker.') }}</p>
                    <p class="mt-2 text-sm text-slate-300">{!! $t('Versi teknis: timeline ini memetakan <code>timestamp_esp</code>, fase transmit, momen server menerima data, dan langkah <code>latency_ms</code> dihitung pada backend ingest. Jalur HTTP terkait <code>sendHTTP()</code> + <code>ApiController::storeHttp</code>; jalur MQTT terkait <code>sendMQTT()</code> + <code>mqtt_worker.php</code>.', 'Technical view: this timeline maps <code>timestamp_esp</code>, transmit phase, server receive moment, and where <code>latency_ms</code> is computed in backend ingest. HTTP path maps to <code>sendHTTP()</code> + <code>ApiController::storeHttp</code>; MQTT path maps to <code>sendMQTT()</code> + <code>mqtt_worker.php</code>.') !!}</p>

                    <div class="mt-4 rounded-xl border border-white/10 bg-slate-950/70 p-3 sm:p-4">
                        <svg viewBox="0 0 1080 380" class="h-auto w-full" role="img" aria-label="HTTP and MQTT sequence timeline SVG">
                            <defs>
                                <marker id="arrowTimeDoc" viewBox="0 0 10 10" refX="8" refY="5" markerWidth="7" markerHeight="7" orient="auto-start-reverse">
                                    <path d="M 0 0 L 10 5 L 0 10 z" fill="#38bdf8" />
                                </marker>
                            </defs>

                            <rect x="24" y="24" width="1032" height="330" rx="14" fill="#0b1220" stroke="#23324a" />
                            <text x="54" y="52" fill="#e2e8f0" font-size="13" font-weight="600">{{ $t('Arah waktu ->', 'Time direction ->') }}</text>
                            <line x1="190" y1="48" x2="1010" y2="48" stroke="#475569" stroke-width="1.4" marker-end="url(#arrowTimeDoc)" />

                            <rect x="44" y="78" width="992" height="118" rx="10" fill="#111b2f" stroke="#1f3a63" />
                            <text x="66" y="102" fill="#93c5fd" font-size="12.5" font-weight="700">HTTP</text>
                            <line x1="190" y1="132" x2="1010" y2="132" stroke="#60a5fa" stroke-width="1.6" />

                            <rect x="44" y="208" width="992" height="118" rx="10" fill="#111f26" stroke="#14532d" />
                            <text x="66" y="232" fill="#86efac" font-size="12.5" font-weight="700">MQTT</text>
                            <line x1="190" y1="262" x2="1010" y2="262" stroke="#34d399" stroke-width="1.6" />

                            <!-- HTTP milestones -->
                            <circle cx="240" cy="132" r="7" fill="#bfdbfe" />
                            <text x="240" y="118" fill="#e2e8f0" font-size="10" text-anchor="middle">timestamp_esp</text>
                            <text x="240" y="154" fill="#94a3b8" font-size="10" text-anchor="middle">T0</text>

                            <circle cx="420" cy="132" r="7" fill="#bfdbfe" />
                            <text x="420" y="118" fill="#e2e8f0" font-size="10" text-anchor="middle">HTTP transmit</text>

                            <circle cx="635" cy="132" r="7" fill="#bfdbfe" />
                            <text x="635" y="118" fill="#e2e8f0" font-size="10" text-anchor="middle">ApiController receive</text>
                            <text x="635" y="154" fill="#94a3b8" font-size="10" text-anchor="middle">timestamp_server</text>

                            <circle cx="790" cy="132" r="7" fill="#bfdbfe" />
                            <text x="790" y="118" fill="#e2e8f0" font-size="10" text-anchor="middle">latency_ms computed</text>

                            <circle cx="930" cy="132" r="7" fill="#bfdbfe" />
                            <text x="930" y="118" fill="#e2e8f0" font-size="10" text-anchor="middle">HTTP response</text>

                            <line x1="240" y1="132" x2="420" y2="132" stroke="#93c5fd" stroke-width="2.1" marker-end="url(#arrowTimeDoc)" />
                            <line x1="420" y1="132" x2="635" y2="132" stroke="#93c5fd" stroke-width="2.1" marker-end="url(#arrowTimeDoc)" />
                            <line x1="635" y1="132" x2="790" y2="132" stroke="#93c5fd" stroke-width="2.1" marker-end="url(#arrowTimeDoc)" />
                            <line x1="790" y1="132" x2="930" y2="132" stroke="#93c5fd" stroke-width="2.1" marker-end="url(#arrowTimeDoc)" />
                            <text x="708" y="176" fill="#7dd3fc" font-size="10.5" text-anchor="middle">latency_ms = abs(server_utc - timestamp_esp)</text>

                            <!-- MQTT milestones -->
                            <circle cx="240" cy="262" r="7" fill="#bbf7d0" />
                            <text x="240" y="248" fill="#e2e8f0" font-size="10" text-anchor="middle">timestamp_esp</text>
                            <text x="240" y="284" fill="#94a3b8" font-size="10" text-anchor="middle">T0</text>

                            <circle cx="420" cy="262" r="7" fill="#bbf7d0" />
                            <text x="420" y="248" fill="#e2e8f0" font-size="10" text-anchor="middle">MQTT publish</text>

                            <circle cx="560" cy="262" r="7" fill="#bbf7d0" />
                            <text x="560" y="248" fill="#e2e8f0" font-size="10" text-anchor="middle">Broker route</text>

                            <circle cx="705" cy="262" r="7" fill="#bbf7d0" />
                            <text x="705" y="248" fill="#e2e8f0" font-size="10" text-anchor="middle">mqtt_worker receive</text>
                            <text x="705" y="284" fill="#94a3b8" font-size="10" text-anchor="middle">timestamp_server</text>

                            <circle cx="860" cy="262" r="7" fill="#bbf7d0" />
                            <text x="860" y="248" fill="#e2e8f0" font-size="10" text-anchor="middle">latency_ms computed</text>

                            <line x1="240" y1="262" x2="420" y2="262" stroke="#86efac" stroke-width="2.1" marker-end="url(#arrowTimeDoc)" />
                            <line x1="420" y1="262" x2="560" y2="262" stroke="#86efac" stroke-width="2.1" marker-end="url(#arrowTimeDoc)" />
                            <line x1="560" y1="262" x2="705" y2="262" stroke="#86efac" stroke-width="2.1" marker-end="url(#arrowTimeDoc)" />
                            <line x1="705" y1="262" x2="860" y2="262" stroke="#86efac" stroke-width="2.1" marker-end="url(#arrowTimeDoc)" />
                            <text x="780" y="305" fill="#6ee7b7" font-size="10.5" text-anchor="middle">{{ $t('Publish/subscribe berjalan asinkron; tidak ada HTTP-style response body.', 'Publish/subscribe is asynchronous; there is no HTTP-style response body.') }}</text>
                        </svg>
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
                    <p class="mt-3 text-sm text-slate-300">{{ $t('Versi awam: pintu HTTP dijaga oleh Laravel, sedangkan pintu MQTT dijaga oleh worker khusus. Keduanya mengecek data sebelum menyimpan.', 'Simple view: HTTP gate is handled by Laravel, while MQTT gate is handled by a dedicated worker. Both validate data before storing it.') }}</p>
                    <p class="mt-2 text-sm text-slate-300">{!! $t('Versi teknis: route API menggunakan middleware <code>throttle:http-data</code> + <code>ingest.key</code>. MQTT berjalan sebagai proses subscriber tersendiri dengan validasi manual, lalu keduanya melakukan upsert idempotent ke tabel yang sama.', 'Technical view: API route uses <code>throttle:http-data</code> + <code>ingest.key</code> middleware. MQTT runs as separate subscriber process with manual validation, then both perform idempotent upsert into the same table.') !!}</p>
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
                    <p class="mt-3 text-sm text-slate-300">{{ $t('Versi awam: payload adalah "isi paket" yang dibawa dari ESP32 ke server. Di dalamnya ada data utama sensor dan data tambahan untuk diagnosis kualitas jaringan.', 'Simple view: payload is "package content" carried from ESP32 to server. It contains primary sensor data and additional fields for network-quality diagnostics.') }}</p>
                    <p class="mt-2 text-sm text-slate-300">{!! $t('Versi teknis: builder payload firmware (<code>fillProtocolPayload()</code>) selalu mengirim field telemetry wajib ditambah field diagnostik seperti RSSI, TX duration, payload bytes, dan counter keberhasilan/kegagalan kirim.', 'Technical view: firmware payload builder (<code>fillProtocolPayload()</code>) always emits required telemetry fields plus diagnostics such as RSSI, TX duration, payload bytes, and send success/fail counters.') !!}</p>
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

                {{-- SECTION 5B: Step-by-step metric explanations --}}
                <section id="metrics" class="doc-card rounded-2xl border border-white/10 p-4 shadow-glow sm:p-5 lg:p-6">
                    <h2 class="text-lg font-semibold sm:text-xl">{{ $t('Penjelasan Bertahap untuk Setiap Metrik', 'Step-by-Step Explanation for Each Metric') }}</h2>
                    <p class="mt-3 text-sm text-slate-300">{{ $t('Bagian ini sengaja ditulis dua lapis: paragraf pertama untuk pemahaman awam, paragraf kedua untuk akurasi teknis sesuai kode.', 'This section is intentionally two-layered: first paragraph for non-technical understanding, second paragraph for technical accuracy based on code.') }}</p>

                    <div class="mt-4 space-y-4 text-sm">
                        <article class="rounded-xl border border-white/10 bg-slate-800/50 p-4">
                            <h3 class="font-semibold text-brand-300">Latency</h3>
                            <p class="mt-2 text-slate-300">{{ $t('Versi awam: latency adalah lama waktu dari data dikirim oleh alat sampai diterima server. Semakin kecil, data terasa semakin cepat sampai.', 'Simple view: latency is how long it takes data to travel from the device to the server. Smaller latency means faster delivery.') }}</p>
                            <p class="mt-2 text-slate-300">{!! $t('Versi teknis: baik HTTP maupun MQTT menghitung <code>latency_ms = abs(server_utc - timestamp_esp)</code> menggunakan <code>Carbon::floatDiffInMilliseconds()</code> di <code>ApiController</code> dan <code>mqtt_worker.php</code>, lalu disimpan ke <code>eksperimens.latency_ms</code>.', 'Technical view: both HTTP and MQTT compute <code>latency_ms = abs(server_utc - timestamp_esp)</code> using <code>Carbon::floatDiffInMilliseconds()</code> in <code>ApiController</code> and <code>mqtt_worker.php</code>, then store it in <code>eksperimens.latency_ms</code>.') !!}</p>
                        </article>

                        <article class="rounded-xl border border-white/10 bg-slate-800/50 p-4">
                            <h3 class="font-semibold text-brand-300">{{ $t('Konsumsi Daya', 'Power Consumption') }}</h3>
                            <p class="mt-2 text-slate-300">{{ $t('Versi awam: sistem memperkirakan "biaya energi" setiap kali ESP32 mengirim data. Ini bukan alat ukur listrik langsung, tetapi estimasi berbasis kondisi kirim.', 'Simple view: the system estimates "energy cost" each time ESP32 sends data. This is not a direct electrical meter; it is an estimate based on transmission conditions.') }}</p>
                            <p class="mt-2 text-slate-300">{!! $t('Versi teknis: firmware menghitung <code>powerMw = voltage * totalCurrentMa</code> di <code>estimateProtocolPower()</code> dengan komponen RSSI, ukuran payload, durasi kirim, overhead protokol, fail ratio, suhu, dan kelembapan. Nilai dikirim sebagai field <code>daya</code> lalu disimpan backend ke <code>daya_mw</code>.', 'Technical view: firmware computes <code>powerMw = voltage * totalCurrentMa</code> in <code>estimateProtocolPower()</code> using RSSI, payload size, TX duration, protocol overhead, fail ratio, temperature, and humidity. It is sent as <code>daya</code> then stored in backend as <code>daya_mw</code>.') !!}</p>
                        </article>

                        <article class="rounded-xl border border-white/10 bg-slate-800/50 p-4">
                            <h3 class="font-semibold text-brand-300">{{ $t('Reliability / Packet Delivery Ratio', 'Reliability / Packet Delivery Ratio') }}</h3>
                            <p class="mt-2 text-slate-300">{{ $t('Versi awam: reliability menunjukkan seberapa konsisten data sampai tanpa hilang. Jika banyak nomor paket yang "loncat", skor reliability turun.', 'Simple view: reliability shows how consistently data arrives without loss. If many packet numbers are missing, reliability decreases.') }}</p>
                            <p class="mt-2 text-slate-300">{!! $t('Versi teknis: backend menghitung sequence reliability dari <code>packet_seq</code> per device (<code>received / expected * 100</code>) dengan aturan segmentasi reboot/jump. Skor akhir adalah gabungan sequence, field completeness, dan transmission health via <code>combineReliability()</code>.', 'Technical view: backend computes sequence reliability from <code>packet_seq</code> per device (<code>received / expected * 100</code>) with reboot/jump segmentation rules. Final score combines sequence, field completeness, and transmission health via <code>combineReliability()</code>.') !!}</p>
                        </article>

                        <article class="rounded-xl border border-white/10 bg-slate-800/50 p-4">
                            <h3 class="font-semibold text-brand-300">Independent Sample T-Test</h3>
                            <p class="mt-2 text-slate-300">{{ $t('Versi awam: T-Test membantu menjawab apakah perbedaan MQTT dan HTTP itu kemungkinan "benar berbeda", bukan hanya kebetulan data sesaat.', 'Simple view: T-Test helps answer whether MQTT and HTTP differences are likely real, not just random short-term fluctuations.') }}</p>
                            <p class="mt-2 text-slate-300">{!! $t('Versi teknis: <code>StatisticsService::tTest()</code> menghitung pooled variance, standard error, dan <code>t_value</code> untuk <code>latency_ms</code> dan <code>daya_mw</code>. Signifikansi ditetapkan dengan batas tetap <code>|t| &gt; 1.96</code>, sementara <code>p_value</code> ditampilkan sebagai nilai aproksimasi.', 'Technical view: <code>StatisticsService::tTest()</code> calculates pooled variance, standard error, and <code>t_value</code> for <code>latency_ms</code> and <code>daya_mw</code>. Significance is decided with fixed threshold <code>|t| &gt; 1.96</code>, while <code>p_value</code> is shown as an approximate value.') !!}</p>
                        </article>
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

                {{-- SECTION 9B: Real examples --}}
                <section id="examples" class="doc-card rounded-2xl border border-white/10 p-4 shadow-glow sm:p-5 lg:p-6">
                    <h2 class="text-lg font-semibold sm:text-xl">{{ $t('Contoh Nyata dari Satu Siklus Data', 'Real Examples from One Data Cycle') }}</h2>
                    <p class="mt-3 text-sm text-slate-300">{{ $t('Tujuan bagian ini adalah membuat angka-angka penelitian lebih mudah dibayangkan. Angka contoh di bawah menggunakan pola rumus yang sama dengan kode runtime.', 'This section makes research numbers easier to imagine. Example numbers below follow the same formula pattern used in runtime code.') }}</p>

                    <div class="mt-4 space-y-4">
                        <article class="rounded-xl border border-white/10 bg-slate-800/50 p-4">
                            <h3 class="font-semibold text-brand-300">{{ $t('Contoh 1: Satu siklus kirim data', 'Example 1: One full data cycle') }}</h3>
                            <p class="mt-2 text-sm text-slate-300">{{ $t('Versi awam: ESP32 membaca sensor, lalu mengirim paket ke HTTP dan MQTT hampir berdekatan, kemudian server menyimpan keduanya.', 'Simple view: ESP32 reads the sensor, sends packet to HTTP and MQTT nearly at the same time, then server stores both.') }}</p>
                            <p class="mt-2 text-sm text-slate-300">{!! $t('Versi teknis: firmware membuat payload lewat <code>fillProtocolPayload()</code> (termasuk <code>packet_seq</code>, <code>timestamp_esp</code>, <code>daya</code>, <code>sensor_read_seq</code>), lalu backend melakukan <code>updateOrCreate</code> berdasarkan kunci unik paket.', 'Technical view: firmware builds payload using <code>fillProtocolPayload()</code> (including <code>packet_seq</code>, <code>timestamp_esp</code>, <code>daya</code>, <code>sensor_read_seq</code>), then backend performs <code>updateOrCreate</code> using packet unique key.') !!}</p>
                        </article>

                        <article class="rounded-xl border border-white/10 bg-slate-800/50 p-4">
                            <h3 class="font-semibold text-brand-300">{{ $t('Contoh 2: Perhitungan latency', 'Example 2: Latency calculation') }}</h3>
                            <div class="mt-2 rounded-lg border border-white/10 bg-slate-950 p-3">
<pre class="overflow-x-auto text-xs leading-5 text-slate-200"><code>timestamp_esp   = 1772021517   (UTC second)
timestamp_server= 1772021518.320 (UTC with millisecond)
latency_ms      = abs(1772021518.320 - 1772021517) * 1000
                = 1320 ms</code></pre>
                            </div>
                            <p class="mt-2 text-sm text-slate-300">{{ $t('Versi awam: data sampai sekitar 1,32 detik setelah waktu cap di alat.', 'Simple view: data arrives around 1.32 seconds after the device timestamp.') }}</p>
                            <p class="mt-2 text-sm text-slate-300">{!! $t('Versi teknis: ini persis pola di <code>Carbon::floatDiffInMilliseconds()</code> dengan <code>abs()</code> di jalur HTTP dan MQTT worker.', 'Technical view: this matches the <code>Carbon::floatDiffInMilliseconds()</code> + <code>abs()</code> pattern in both HTTP and MQTT worker paths.') !!}</p>
                        </article>

                        <article class="rounded-xl border border-white/10 bg-slate-800/50 p-4">
                            <h3 class="font-semibold text-brand-300">{{ $t('Contoh 3: Perhitungan daya (estimasi)', 'Example 3: Power estimation') }}</h3>
                            <div class="mt-2 rounded-lg border border-white/10 bg-slate-950 p-3">
<pre class="overflow-x-auto text-xs leading-5 text-slate-200"><code>Assume: protocol=HTTP, rssi=-60, payload=210 bytes, tx=80 ms,
cpu=240MHz, fail ratio HTTP=10%, temp=28C, humidity=60%, success=true.

totalCurrentMa ~ 72 + 2 + 26.4 + 9.75 + 42 + 14.4 + 20 + 3 + 2.7 + 0.5 + 0
               ~ 192.75 mA
powerMw = 3.30 * 192.75 ~ 636.08 mW</code></pre>
                            </div>
                            <p class="mt-2 text-sm text-slate-300">{{ $t('Versi awam: semakin jelek sinyal, semakin lama kirim, dan semakin besar paket, maka daya estimasi cenderung naik.', 'Simple view: weaker signal, longer transmission, and larger payload generally increase estimated power.') }}</p>
                            <p class="mt-2 text-sm text-slate-300">{{ $t('Versi teknis: komponen angka di atas mengikuti komponen real di fungsi estimateProtocolPower() firmware.', 'Technical view: the components above follow the real terms in firmware estimateProtocolPower().') }}</p>
                        </article>

                        <article class="rounded-xl border border-white/10 bg-slate-800/50 p-4">
                            <h3 class="font-semibold text-brand-300">{{ $t('Contoh 4: Membaca p-value dan signifikansi', 'Example 4: Reading p-value and significance') }}</h3>
                            <p class="mt-2 text-sm text-slate-300">{{ $t('Versi awam: jika hasil menunjukkan perbedaan signifikan, artinya dua jalur komunikasi punya performa yang memang berbeda secara statistik.', 'Simple view: if result is significant, it means the two communication paths have a statistically meaningful performance difference.') }}</p>
                            <p class="mt-2 text-sm text-slate-300">{!! $t('Versi teknis: di kode saat ini keputusan signifikan ditentukan oleh <code>|t_value| &gt; 1.96</code>. Nilai <code>p_value</code> ditampilkan sebagai aproksimasi pendukung interpretasi (bukan perhitungan exact Student-t untuk semua df).', 'Technical view: current code determines significance using <code>|t_value| &gt; 1.96</code>. <code>p_value</code> is shown as an approximation to support interpretation (not exact Student-t computation for all df).') !!}</p>
                        </article>
                    </div>
                </section>

                {{-- SECTION 9C: Why this design --}}
                <section id="why-design" class="doc-card rounded-2xl border border-white/10 p-4 shadow-glow sm:p-5 lg:p-6">
                    <h2 class="text-lg font-semibold sm:text-xl">{{ $t('Kenapa Sistem Ini Dibuat Seperti Ini?', 'Why Was This System Designed This Way?') }}</h2>

                    <div class="mt-4 space-y-4">
                        <article class="rounded-xl border border-white/10 bg-slate-800/50 p-4">
                            <h3 class="font-semibold text-brand-300">{{ $t('Alasan membandingkan MQTT dan HTTP', 'Why compare MQTT and HTTP') }}</h3>
                            <p class="mt-2 text-sm text-slate-300">{{ $t('Versi awam: penelitian butuh pembanding yang adil. Karena itu data yang mirip dikirim lewat dua jalur berbeda agar bisa dilihat mana yang lebih cepat dan lebih stabil.', 'Simple view: research needs fair comparison. Similar payload is sent through two different paths so we can observe which one is faster and more stable.') }}</p>
                            <p class="mt-2 text-sm text-slate-300">{!! $t('Versi teknis: firmware mengirim field yang sama ke HTTP dan MQTT, backend menyimpan pada tabel dan skema yang sama (dibedakan kolom <code>protokol</code>) sehingga analisis statistik antar-protokol bisa langsung dibandingkan.', 'Technical view: firmware sends matching fields to HTTP and MQTT, backend stores them in the same table/schema (distinguished by <code>protokol</code>) so cross-protocol statistics can be compared directly.') !!}</p>
                        </article>

                        <article class="rounded-xl border border-white/10 bg-slate-800/50 p-4">
                            <h3 class="font-semibold text-brand-300">{{ $t('Alasan worker MQTT dipisah', 'Why MQTT worker is separated') }}</h3>
                            <p class="mt-2 text-sm text-slate-300">{{ $t('Versi awam: jalur MQTT bersifat "menunggu pesan terus-menerus", jadi lebih cocok ditangani proses khusus yang selalu hidup.', 'Simple view: MQTT path is "always listening", so it fits better as a dedicated always-on process.') }}</p>
                            <p class="mt-2 text-sm text-slate-300">{!! $t('Versi teknis: repository ini memang tidak memiliki controller ingest MQTT di Laravel; ingest MQTT dijalankan oleh proses mandiri <code>mqtt_worker.php</code> dengan reconnect loop, fallback host, dan lock file untuk mencegah worker ganda.', 'Technical view: this repository intentionally has no Laravel MQTT ingest controller; MQTT ingest runs in standalone <code>mqtt_worker.php</code> with reconnect loop, fallback hosts, and lock file to prevent duplicate workers.') !!}</p>
                        </article>

                        <article class="rounded-xl border border-white/10 bg-slate-800/50 p-4">
                            <h3 class="font-semibold text-brand-300">{{ $t('Alasan memakai T-Test', 'Why use T-Test') }}</h3>
                            <p class="mt-2 text-sm text-slate-300">{{ $t('Versi awam: rata-rata saja belum cukup. T-Test dipakai untuk mengecek apakah selisih dua protokol cukup kuat secara statistik.', 'Simple view: averages alone are not enough. T-Test checks whether the difference between protocols is statistically strong enough.') }}</p>
                            <p class="mt-2 text-sm text-slate-300">{!! $t('Versi teknis: <code>StatisticsService</code> menghitung independent sample t-test untuk latency dan daya, lalu dashboard menampilkan parameter utama (N, mean, variance, std dev, t-value, p-value, interpretasi).', 'Technical view: <code>StatisticsService</code> computes independent sample t-tests for latency and power, then dashboard displays key parameters (N, mean, variance, std dev, t-value, p-value, interpretation).') !!}</p>
                        </article>
                    </div>
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
                    <p class="mt-3 text-sm text-slate-300">{{ $t('Versi awam: setelah data disimpan di database, sistem mengolahnya menjadi angka ringkas agar pengguna bisa langsung melihat siapa yang lebih cepat, lebih hemat daya, dan lebih stabil.', 'Simple view: after data is saved into database, the system transforms it into concise numbers so users can quickly see which protocol is faster, more power-efficient, and more stable.') }}</p>
                    <p class="mt-2 text-sm text-slate-300">{!! $t('Versi teknis: <code>DashboardController::index</code> mengambil data protocol dari model telemetry, memanggil <code>StatisticsService</code> untuk summary/reliability/t-test, menyusun payload chart, lalu mengirim semua hasil ke <code>resources/views/dashboard.blade.php</code>.', 'Technical view: <code>DashboardController::index</code> pulls protocol data from telemetry model, calls <code>StatisticsService</code> for summary/reliability/t-test, prepares chart payloads, then sends all results to <code>resources/views/dashboard.blade.php</code>.') !!}</p>
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

                {{-- SECTION 14: Glossary --}}
                <section id="glossary" class="doc-card rounded-2xl border border-white/10 p-4 shadow-glow sm:p-5 lg:p-6">
                    <h2 class="text-lg font-semibold sm:text-xl">Glossary / {{ $t('Daftar Istilah', 'Glossary') }}</h2>
                    <p class="mt-3 text-sm text-slate-300">{{ $t('Setiap istilah ditulis dua lapis: penjelasan sederhana untuk pembaca umum, lalu penjelasan teknis sesuai implementasi kode di repository ini.', 'Each term is written in two layers: simple explanation for general readers, followed by technical explanation based on actual repository implementation.') }}</p>

                    <div class="mt-4 grid gap-3 md:grid-cols-2">
                        <article class="rounded-xl border border-white/10 bg-slate-800/50 p-4 text-sm">
                            <h3 class="font-semibold text-brand-300">idempotent upsert</h3>
                            <p class="mt-2 text-slate-300">{{ $t('Sederhana: mengirim data yang sama berulang kali tidak membuat data dobel.', 'Simple: sending the same data repeatedly does not create duplicates.') }}</p>
                            <p class="mt-2 text-slate-300">{!! $t('Teknis: HTTP dan MQTT memakai <code>updateOrCreate</code> dengan identitas <code>(device_id, protokol, packet_seq)</code>, diperkuat unique index database dengan kombinasi key yang sama.', 'Technical: HTTP and MQTT use <code>updateOrCreate</code> with identity <code>(device_id, protokol, packet_seq)</code>, reinforced by database unique index on the same key combination.') !!}</p>
                        </article>

                        <article class="rounded-xl border border-white/10 bg-slate-800/50 p-4 text-sm">
                            <h3 class="font-semibold text-brand-300">packet_seq</h3>
                            <p class="mt-2 text-slate-300">{{ $t('Sederhana: nomor urut paket agar sistem tahu ada paket yang hilang atau berulang.', 'Simple: packet order number so the system can detect missing or repeated packets.') }}</p>
                            <p class="mt-2 text-slate-300">{!! $t('Teknis: firmware menaikkan <code>httpPacketSeq</code> dan <code>mqttPacketSeq</code> saat kirim; backend memakai nilai ini untuk dedup idempotent dan menghitung sequence reliability.', 'Technical: firmware increments <code>httpPacketSeq</code> and <code>mqttPacketSeq</code> on each send; backend uses this value for idempotent dedup and sequence reliability calculation.') !!}</p>
                        </article>

                        <article class="rounded-xl border border-white/10 bg-slate-800/50 p-4 text-sm">
                            <h3 class="font-semibold text-brand-300">window analysis</h3>
                            <p class="mt-2 text-slate-300">{{ $t('Sederhana: sistem hanya mengambil data terbaru dalam jumlah tertentu agar analisis tetap ringan dan relevan.', 'Simple: the system only takes a recent subset of data so analysis remains lightweight and relevant.') }}</p>
                            <p class="mt-2 text-slate-300">{!! $t('Teknis: <code>DASHBOARD_ANALYSIS_WINDOW</code> (default ' . $analysisWindow . ') dipakai <code>StatisticsService::getProtocolData()</code>; chart memakai <code>DASHBOARD_CHART_WINDOW</code> (default ' . ($chartWindow === 0 ? '0 (unlimited)' : $chartWindow) . ').', 'Technical: <code>DASHBOARD_ANALYSIS_WINDOW</code> (default ' . $analysisWindow . ') is used by <code>StatisticsService::getProtocolData()</code>; chart uses <code>DASHBOARD_CHART_WINDOW</code> (default ' . ($chartWindow === 0 ? '0 (unlimited)' : $chartWindow) . ').') !!}</p>
                        </article>

                        <article class="rounded-xl border border-white/10 bg-slate-800/50 p-4 text-sm">
                            <h3 class="font-semibold text-brand-300">latency_ms</h3>
                            <p class="mt-2 text-slate-300">{{ $t('Sederhana: waktu tempuh data dari ESP32 sampai server, dalam milidetik.', 'Simple: travel time of data from ESP32 to server, in milliseconds.') }}</p>
                            <p class="mt-2 text-slate-300">{!! $t('Teknis: dihitung sebagai <code>abs(server_utc - timestamp_esp)</code> di <code>ApiController::storeHttp</code> dan <code>mqtt_worker.php</code>, lalu disimpan ke kolom <code>latency_ms</code>.', 'Technical: calculated as <code>abs(server_utc - timestamp_esp)</code> in <code>ApiController::storeHttp</code> and <code>mqtt_worker.php</code>, then stored in <code>latency_ms</code> column.') !!}</p>
                        </article>

                        <article class="rounded-xl border border-white/10 bg-slate-800/50 p-4 text-sm">
                            <h3 class="font-semibold text-brand-300">daya_mw</h3>
                            <p class="mt-2 text-slate-300">{{ $t('Sederhana: perkiraan konsumsi daya saat pengiriman data.', 'Simple: estimated power usage during data transmission.') }}</p>
                            <p class="mt-2 text-slate-300">{!! $t('Teknis: firmware menghitung <code>daya</code> lewat <code>estimateProtocolPower()</code> lalu backend menyimpannya ke kolom <code>daya_mw</code>. Ini estimasi model, bukan pembacaan sensor listrik langsung.', 'Technical: firmware computes <code>daya</code> via <code>estimateProtocolPower()</code> then backend stores it in <code>daya_mw</code>. This is a model estimate, not direct electrical sensor reading.') !!}</p>
                        </article>

                        <article class="rounded-xl border border-white/10 bg-slate-800/50 p-4 text-sm">
                            <h3 class="font-semibold text-brand-300">Independent Sample T-Test</h3>
                            <p class="mt-2 text-slate-300">{{ $t('Sederhana: uji statistik untuk mengecek apakah perbedaan dua kelompok data cukup kuat secara ilmiah.', 'Simple: statistical test to check whether differences between two data groups are scientifically strong enough.') }}</p>
                            <p class="mt-2 text-slate-300">{!! $t('Teknis: fungsi <code>StatisticsService::tTest()</code> menghitung pooled variance, standard error, dan <code>t_value</code> untuk data MQTT vs HTTP pada metrik <code>latency_ms</code> dan <code>daya_mw</code>.', 'Technical: <code>StatisticsService::tTest()</code> computes pooled variance, standard error, and <code>t_value</code> for MQTT vs HTTP data on <code>latency_ms</code> and <code>daya_mw</code> metrics.') !!}</p>
                        </article>

                        <article class="rounded-xl border border-white/10 bg-slate-800/50 p-4 text-sm">
                            <h3 class="font-semibold text-brand-300">p-value</h3>
                            <p class="mt-2 text-slate-300">{{ $t('Sederhana: angka yang membantu menilai apakah perbedaan bisa dianggap kebetulan atau tidak.', 'Simple: a number that helps judge whether the observed difference could be random chance.') }}</p>
                            <p class="mt-2 text-slate-300">{!! $t('Teknis: di project ini <code>p_value</code> berasal dari fungsi aproksimasi <code>calculatePValue()</code> (normal CDF untuk df besar, bin kasar untuk df kecil), sehingga bersifat indikatif.', 'Technical: in this project, <code>p_value</code> comes from approximation function <code>calculatePValue()</code> (normal CDF for large df, coarse bins for small df), so it is indicative.') !!}</p>
                        </article>

                        <article class="rounded-xl border border-white/10 bg-slate-800/50 p-4 text-sm">
                            <h3 class="font-semibold text-brand-300">publish/subscribe</h3>
                            <p class="mt-2 text-slate-300">{{ $t('Sederhana: pengirim menaruh pesan ke topik, penerima yang berlangganan topik itu akan memprosesnya.', 'Simple: sender puts message into a topic, and subscribed receivers process it.') }}</p>
                            <p class="mt-2 text-slate-300">{!! $t('Teknis: firmware <code>sendMQTT()</code> melakukan <code>publish</code> ke topic konfigurasi, lalu <code>mqtt_worker.php</code> melakukan <code>subscribe</code> dan menyimpan payload ke database.', 'Technical: firmware <code>sendMQTT()</code> performs <code>publish</code> to configured topic, then <code>mqtt_worker.php</code> performs <code>subscribe</code> and stores payload into database.') !!}</p>
                        </article>

                        <article class="rounded-xl border border-white/10 bg-slate-800/50 p-4 text-sm">
                            <h3 class="font-semibold text-brand-300">request/response</h3>
                            <p class="mt-2 text-slate-300">{{ $t('Sederhana: pengirim meminta server memproses data, lalu server membalas status hasilnya.', 'Simple: sender asks server to process data, then server replies with result status.') }}</p>
                            <p class="mt-2 text-slate-300">{!! $t('Teknis: firmware <code>sendHTTP()</code> mengirim request ke <code>/api/http-data</code>, <code>ApiController::storeHttp</code> memproses/validasi/upsert, kemudian mengembalikan response JSON dengan status kode HTTP.', 'Technical: firmware <code>sendHTTP()</code> posts to <code>/api/http-data</code>, <code>ApiController::storeHttp</code> handles validation/upsert, then returns JSON response with HTTP status code.') !!}</p>
                        </article>
                    </div>
                </section>
            </main>
        </div>
    </div>

    <button
        id="scroll-top-btn"
        type="button"
        aria-label="{{ $t('Ke Atas', 'Scroll to top') }}"
        class="fixed bottom-4 right-4 inline-flex h-11 w-11 items-center justify-center rounded-full border border-white/15 bg-slate-900/90 text-slate-100 shadow-glow transition hover:border-brand-400 hover:text-white"
    >
        <span class="sr-only">{{ $t('Ke Atas', 'Scroll to top') }}</span>
        <svg viewBox="0 0 20 20" fill="currentColor" class="h-5 w-5" aria-hidden="true">
            <path fill-rule="evenodd" d="M10 3a1 1 0 0 1 .707.293l5 5a1 1 0 0 1-1.414 1.414L11 6.414V16a1 1 0 1 1-2 0V6.414L5.707 9.707A1 1 0 0 1 4.293 8.293l5-5A1 1 0 0 1 10 3Z" clip-rule="evenodd" />
        </svg>
    </button>

    <script>
        // Toggle blur only when mobile/tablet nav is actually stuck at the top.
        (function () {
            const nav = document.getElementById('mobile-doc-nav');
            const sentinel = document.getElementById('mobile-doc-nav-sentinel');
            const scrollTopButton = document.getElementById('scroll-top-btn');

            // Scroll-to-top button uses smooth scrolling and works independent of #top anchors.
            if (scrollTopButton) {
                scrollTopButton.addEventListener('click', () => {
                    window.scrollTo({
                        top: 0,
                        behavior: 'smooth'
                    });
                });
            }

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


