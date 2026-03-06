<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Admin Config - IoT Research</title>
    <style>
        :root {
            --bg: #091422;
            --panel: #102235;
            --panel-2: #0d1c2e;
            --line: #244057;
            --text: #e6edf6;
            --muted: #9bb2c6;
            --accent: #3dd4ff;
            --ok: #16a34a;
            --warn: #f59e0b;
            --danger: #ef4444;
        }

        * { box-sizing: border-box; }

        body {
            margin: 0;
            color: var(--text);
            background:
                radial-gradient(circle at 10% -10%, #1e3a5f 0%, transparent 36%),
                radial-gradient(circle at 110% -20%, #113248 0%, transparent 30%),
                linear-gradient(160deg, var(--bg), #050b13 72%);
            font-family: "Segoe UI", Tahoma, Geneva, Verdana, sans-serif;
        }

        .wrap {
            max-width: 1360px;
            margin: 0 auto;
            padding: 18px;
        }

        .topbar {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
            flex-wrap: wrap;
            margin-bottom: 14px;
        }

        .title h1 {
            margin: 0;
            font-size: 1.35rem;
            letter-spacing: .2px;
        }

        .title p {
            margin: 4px 0 0;
            color: var(--muted);
            font-size: .9rem;
        }

        .actions {
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
        }

        .btn {
            border: 1px solid var(--line);
            color: var(--text);
            background: #10253a;
            border-radius: 10px;
            padding: 9px 12px;
            text-decoration: none;
            font-weight: 600;
            font-size: .85rem;
            cursor: pointer;
        }

        .btn.primary {
            border-color: #22d3ee;
            background: linear-gradient(90deg, #36cfff, #22d3ee);
            color: #08222a;
        }

        .btn.warn {
            border-color: rgba(245, 158, 11, .45);
            background: rgba(245, 158, 11, .12);
            color: #fcd34d;
        }

        .btn.danger {
            border-color: rgba(239, 68, 68, .5);
            background: rgba(239, 68, 68, .12);
            color: #fecaca;
        }

        .btn:disabled {
            opacity: .55;
            cursor: not-allowed;
            filter: saturate(.6);
        }

        .flash {
            border-radius: 10px;
            padding: 10px 12px;
            margin-bottom: 12px;
            font-size: .9rem;
        }

        .flash.ok {
            border: 1px solid rgba(22, 163, 74, .45);
            background: rgba(22, 163, 74, .14);
            color: #bbf7d0;
        }

        .flash.err {
            border: 1px solid rgba(239, 68, 68, .45);
            background: rgba(239, 68, 68, .14);
            color: #fecaca;
        }

        .layout {
            display: grid;
            grid-template-columns: 1.2fr 1fr;
            gap: 14px;
        }

        .panel {
            background: linear-gradient(175deg, var(--panel), var(--panel-2));
            border: 1px solid var(--line);
            border-radius: 14px;
            padding: 14px;
        }

        .panel h2 {
            margin: 0 0 6px;
            font-size: 1rem;
        }

        .sub {
            margin: 0 0 12px;
            color: var(--muted);
            font-size: .84rem;
            line-height: 1.45;
        }

        .stack {
            display: flex;
            flex-direction: column;
            gap: 10px;
        }

        .row {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 10px;
        }

        .row-3 {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: 10px;
        }

        .field {
            border: 1px solid var(--line);
            background: #0b1829;
            border-radius: 10px;
            padding: 10px;
        }

        .field label {
            display: block;
            margin-bottom: 6px;
            font-size: .8rem;
            font-weight: 700;
        }

        .field small {
            display: block;
            margin-top: 6px;
            color: var(--muted);
            font-size: .73rem;
            line-height: 1.32;
        }

        input, select, textarea {
            width: 100%;
            border: 1px solid #2d4d67;
            background: #07111d;
            color: var(--text);
            border-radius: 8px;
            padding: 8px 9px;
            font-size: .85rem;
        }

        textarea {
            min-height: 130px;
            resize: vertical;
            font-family: Consolas, "Courier New", monospace;
        }

        .tag {
            display: inline-flex;
            border-radius: 999px;
            border: 1px solid rgba(61, 212, 255, .5);
            color: #9be8ff;
            padding: 3px 8px;
            font-size: .72rem;
            margin-left: 6px;
        }

        .tag.warn {
            border-color: rgba(245, 158, 11, .45);
            color: #fcd34d;
        }

        .table {
            width: 100%;
            border-collapse: collapse;
            font-size: .83rem;
        }

        .table th,
        .table td {
            border-bottom: 1px solid var(--line);
            padding: 8px 6px;
            text-align: left;
            vertical-align: top;
        }

        .table th {
            color: #a5d8ff;
            font-weight: 700;
        }

        .cell-actions {
            display: flex;
            flex-wrap: wrap;
            gap: 6px;
        }

        .note {
            color: var(--muted);
            font-size: .78rem;
            line-height: 1.4;
        }

        .code-preview-grid {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 10px;
        }

        .code-preview-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 10px;
            margin-bottom: 8px;
        }

        .code-preview-header label {
            margin-bottom: 0;
            display: inline-flex;
            align-items: center;
            flex-wrap: wrap;
            gap: 6px;
        }

        .icon-btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 34px;
            height: 34px;
            border: 1px solid rgba(61, 212, 255, .45);
            border-radius: 9px;
            background: rgba(61, 212, 255, .09);
            color: #9be8ff;
            cursor: pointer;
            flex-shrink: 0;
        }

        .icon-btn:hover {
            background: rgba(61, 212, 255, .15);
        }

        .icon-btn svg {
            width: 15px;
            height: 15px;
        }

        .code-preview-textarea {
            min-height: 290px;
        }

        .webflash-log {
            min-height: 180px;
            white-space: pre-wrap;
            word-break: break-word;
        }

        .serial-monitor-log {
            min-height: 180px;
            white-space: pre-wrap;
            word-break: break-word;
        }

        details summary {
            cursor: pointer;
            font-weight: 700;
            color: #9be8ff;
            margin-bottom: 10px;
        }

        .section-gap {
            margin-top: 14px;
        }

        @media (max-width: 1120px) {
            .layout {
                grid-template-columns: 1fr;
            }

            .row,
            .row-3,
            .code-preview-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <div class="wrap">
        @php
            $quickRuntimeKeys = [
                'APP_URL',
                'MQTT_HOST',
                'MQTT_PORT',
                'MQTT_TOPIC',
                'HTTP_INGEST_KEY',
                'DASHBOARD_PROTOCOL_FRESHNESS_SECONDS',
                'DASHBOARD_ESP32_FRESHNESS_SECONDS',
                'DATA_RETENTION_DAYS',
            ];

            $quickRuntimeItems = collect($quickRuntimeKeys)
                ->map(fn (string $key) => $settings[$key] ?? null)
                ->filter();
            $firmwareCliResult = session('firmware_cli_result');
            $effectiveMqttServer = null;
            $firmwareTargetWarnings = [];
            if ($selectedProfile !== null) {
                $brokerCandidate = trim((string) ($selectedProfile->mqtt_broker ?? ''));
                $serverCandidate = trim((string) ($selectedProfile->server_host ?? ''));
                $effectiveMqttServer = $brokerCandidate !== ''
                    ? $brokerCandidate
                    : $serverCandidate;

                $unsafeHosts = ['localhost', '127.0.0.1', '::1', '0.0.0.0', 'esp_mqtt_broker'];
                $effectiveMqttLower = strtolower($effectiveMqttServer ?? '');
                if ($effectiveMqttLower === '' || in_array($effectiveMqttLower, $unsafeHosts, true) || str_starts_with($effectiveMqttLower, '127.')) {
                    $firmwareTargetWarnings[] = 'MQTT target masih localhost/loopback/placeholder. ESP32 di jaringan tidak akan bisa kirim data ke target ini.';
                }

                $httpBaseHost = strtolower((string) (parse_url((string) ($selectedProfile->http_base_url ?? ''), PHP_URL_HOST) ?: ''));
                if ($httpBaseHost === '' || in_array($httpBaseHost, $unsafeHosts, true) || str_starts_with($httpBaseHost, '127.')) {
                    $firmwareTargetWarnings[] = 'HTTP Base URL host tidak valid untuk ESP32 (localhost/loopback). Ganti ke domain/IP server yang bisa diakses ESP32.';
                }
            }
            $serialBaudOptions = [
                1200,
                2400,
                4800,
                9600,
                14400,
                19200,
                28800,
                38400,
                57600,
                74880,
                115200,
                128000,
                230400,
                250000,
                256000,
                460800,
                500000,
                512000,
                921600,
                1000000,
                1500000,
                2000000,
            ];
            $serialDefaultBaud = max(1200, (int) (($selectedProfile->monitor_speed ?? null) ?: 115200));
            if (!in_array($serialDefaultBaud, $serialBaudOptions, true)) {
                $serialBaudOptions[] = $serialDefaultBaud;
                sort($serialBaudOptions);
            }
        @endphp

        <div class="topbar">
            <div class="title">
                <h1>Admin Configuration Console</h1>
                <p>Production-ready runtime tuning, multi-device provisioning, and firmware generation from one GUI.</p>
            </div>
            <div class="actions">
                <a class="btn" href="{{ route('dashboard', [], false) }}">Dashboard</a>
                <a class="btn" href="{{ route('simulation.index', [], false) }}">Simulation</a>
                <form method="POST" action="{{ route('admin.logout', [], false) }}">
                    @csrf
                    <button type="submit" class="btn warn">Logout Admin</button>
                </form>
            </div>
        </div>

        @if (session('admin_status'))
            <div class="flash ok">{{ session('admin_status') }}</div>
        @endif
        @if ($errors->any())
            <div class="flash err">{{ $errors->first() }}</div>
        @endif

        <div class="layout">
            <div class="stack">
                <div class="panel">
                    <h2>Quick Setup Runtime</h2>
                    <p class="sub">Edit core production values first. This saves runtime overrides to database without manual `.env` editing.</p>
                    <p class="note">Runtime overrides affect Laravel worker/app runtime. Existing per-device firmware profile values are not overwritten automatically.</p>

                    <form id="quick-runtime-form" method="POST" action="{{ route('admin.config.runtime.save', [], false) }}" class="stack">
                        @csrf
                        <div class="row">
                            @foreach ($quickRuntimeItems as $item)
                                @php
                                    $key = $item['key'];
                                    $isBool = ($item['type'] ?? '') === 'boolean';
                                    $isSecret = (bool) ($item['secret'] ?? false);
                                    $inputValue = (string) ($item['input_value'] ?? '');
                                @endphp
                                <div class="field">
                                    <label for="quick_{{ $key }}">
                                        {{ $item['label'] }}
                                        @if ($item['stored'])
                                            <span class="tag">override</span>
                                        @endif
                                    </label>
                                    @if ($isBool)
                                        <select id="quick_{{ $key }}" name="{{ $key }}">
                                            <option value="">Use default</option>
                                            <option value="1" @selected($inputValue === '1')>true</option>
                                            <option value="0" @selected($inputValue === '0')>false</option>
                                        </select>
                                    @else
                                        <input
                                            id="quick_{{ $key }}"
                                            name="{{ $key }}"
                                            type="{{ $isSecret ? 'password' : 'text' }}"
                                            value="{{ $inputValue }}"
                                            placeholder="{{ $item['placeholder'] ?? '' }}"
                                            autocomplete="off"
                                        >
                                    @endif
                                    <small>{{ $item['help'] ?? '' }}</small>
                                </div>
                            @endforeach
                        </div>
                        <div class="actions">
                            <button id="quick-runtime-save-btn" class="btn primary" type="submit" disabled>Save Quick Runtime Setup</button>
                        </div>
                    </form>
                </div>

                {{-- Advanced Runtime Overrides and Deploy Snippet removed to keep admin focused on operational controls. --}}
            </div>

            <div class="stack">
                <div class="panel">
                    <h2>Device CRUD</h2>
                    <p class="sub">Create, edit, and remove ESP32 devices from one place. New device can clone firmware profile from existing unit.</p>
                    <form method="POST" action="{{ route('admin.config.devices.store', [], false) }}" class="stack">
                        @csrf
                        <div class="row">
                            <div class="field">
                                <label for="new_nama_device">Device Name</label>
                                <input id="new_nama_device" name="nama_device" placeholder="ESP32-LAB-01" required>
                            </div>
                            <div class="field">
                                <label for="new_lokasi">Location</label>
                                <input id="new_lokasi" name="lokasi" placeholder="Lab Praktikum A">
                            </div>
                        </div>
                        <div class="field">
                            <label for="clone_profile_from_device_id">Clone Profile From (Optional)</label>
                            <select id="clone_profile_from_device_id" name="clone_profile_from_device_id">
                                <option value="">No clone</option>
                                @foreach ($devices as $deviceOption)
                                    <option value="{{ $deviceOption->id }}">#{{ $deviceOption->id }} - {{ $deviceOption->nama_device }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="actions">
                            <button class="btn primary" type="submit">Create Device</button>
                        </div>
                    </form>
                </div>

                <div class="panel">
                    <h2>Registered Devices</h2>
                    <table class="table">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Name</th>
                                <th>Location</th>
                                <th>Rows</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($devices as $device)
                                <tr>
                                    <td>{{ $device->id }}</td>
                                    <td>{{ $device->nama_device }}</td>
                                    <td>{{ $device->lokasi ?: '-' }}</td>
                                    <td>{{ $device->eksperimens_count ?? 0 }}</td>
                                    <td>
                                        <div class="cell-actions">
                                            @if ($selectedDevice && (int) $selectedDevice->id === (int) $device->id)
                                                <button class="btn" type="button" disabled>Selected</button>
                                            @else
                                                <a class="btn" href="{{ route('admin.config.index', ['device_id' => $device->id], false) }}">Select</a>
                                            @endif
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr><td colspan="5">No devices yet.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                @if ($selectedDevice)
                    <div class="panel">
                        <h2>Edit Selected Device</h2>
                        <p class="sub">Selected: #{{ $selectedDevice->id }} - {{ $selectedDevice->nama_device }}</p>

                        <form id="edit-device-form" method="POST" action="{{ route('admin.config.devices.update', $selectedDevice, false) }}" class="stack">
                            @csrf
                            @method('PATCH')
                            <div class="row">
                                <div class="field">
                                    <label for="edit_nama_device">Device Name</label>
                                    <input id="edit_nama_device" name="nama_device" value="{{ $selectedDevice->nama_device }}" required>
                                </div>
                                <div class="field">
                                    <label for="edit_lokasi">Location</label>
                                    <input id="edit_lokasi" name="lokasi" value="{{ $selectedDevice->lokasi }}">
                                </div>
                            </div>
                            <div class="actions">
                                <button id="update-device-btn" class="btn primary" type="submit" disabled>Update Device</button>
                            </div>
                        </form>

                        <div class="section-gap"></div>

                        <form id="delete-device-form" method="POST" action="{{ route('admin.config.devices.destroy', $selectedDevice, false) }}" class="stack" data-has-rows="{{ ((int) ($selectedDevice->eksperimens_count ?? 0)) > 0 ? '1' : '0' }}">
                            @csrf
                            @method('DELETE')
                            <div class="field">
                                <label for="confirm_delete">Confirm Delete</label>
                                <input id="confirm_delete" name="confirm_delete" placeholder="Type DELETE to confirm" required>
                                <small>Type exactly <strong>DELETE</strong> to remove this device.</small>
                            </div>
                            <div class="field">
                                <label style="display:flex;align-items:center;gap:8px;">
                                    <input id="purge_experiments" type="checkbox" name="purge_experiments" value="1" style="width:auto;">
                                    Purge all experiment rows linked to this device
                                </label>
                                <small>Required when the device still has measurement rows because DB foreign key blocks delete.</small>
                            </div>
                            <div class="actions">
                                <button id="delete-device-btn" class="btn danger" type="submit" disabled>Delete Device</button>
                            </div>
                        </form>
                    </div>
                @endif
            </div>
        </div>

        @if ($selectedDevice && $selectedProfile && $renderedFirmware)
            <div class="panel section-gap">
                <h2>Firmware Profile - Device #{{ $selectedDevice->id }} ({{ $selectedDevice->nama_device }})</h2>
                <p class="sub">Use production-ready network targets here. Firmware generator will output consistent `main.cpp` and `platformio.ini` for this device.</p>
                <p class="note">Active target is locked to <strong>Device ID {{ $selectedDevice->id }}</strong> from the current selection. Build, upload, and webflash always follow this selected device context.</p>
                @if ($firmwareTargetWarnings !== [])
                    <div class="flash err section-gap">
                        <strong>Firmware target warning:</strong>
                        <ul style="margin:8px 0 0 18px; padding:0;">
                            @foreach ($firmwareTargetWarnings as $warn)
                                <li>{{ $warn }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif
                <details class="section-gap">
                    <summary>How this firmware panel works</summary>
                    <div class="note">
                        <p>1) <strong>Save Firmware Profile</strong> writes values to `device_firmware_profiles` for this selected device.</p>
                        <p>2) <strong>Apply to Workspace</strong> syncs generated `main.cpp` and `platformio.ini` into `ESP32_Firmware/*`.</p>
                        <p>3) <strong>Build / Build & Upload / Web Flash</strong> always regenerate from saved profile before running action.</p>
                        <p>4) <strong>MQTT Broker Override</strong> is the primary MQTT server for generated firmware. `MQTT Host` is legacy fallback only.</p>
                    </div>
                </details>

                <form
                    id="firmware-profile-form"
                    method="POST"
                    action="{{ route('admin.config.devices.profile.save', $selectedDevice, false) }}"
                    class="stack"
                >
                    @csrf
                    <div class="row-3">
                        <div class="field">
                            <label>Board</label>
                            <select name="board">
                                @foreach ($boardOptions as $board)
                                    <option value="{{ $board }}" @selected($selectedProfile->board === $board)>{{ $board }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="field">
                            <label>DHT Model</label>
                            <select name="dht_model">
                                @foreach ($dhtModels as $model)
                                    <option value="{{ $model }}" @selected($selectedProfile->dht_model === $model)>{{ $model }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="field">
                            <label>DHT Pin</label>
                            <input name="dht_pin" type="number" min="0" max="39" value="{{ $selectedProfile->dht_pin }}" required>
                        </div>
                    </div>

                    <div class="row">
                        <div class="field">
                            <label>WiFi SSID</label>
                            <input name="wifi_ssid" value="{{ $selectedProfile->wifi_ssid }}" required>
                        </div>
                        <div class="field">
                            <label>WiFi Password</label>
                            <input name="wifi_password" value="{{ $selectedProfile->wifi_password }}" required>
                        </div>
                    </div>

                    <div class="row">
                        <div class="field">
                            <label>HTTP Base URL</label>
                            <input name="http_base_url" value="{{ $selectedProfile->http_base_url }}" placeholder="https://espdht.mufaza.my.id" required>
                            <small>Used for `ESP_HTTP_BASE_URL` in `platformio.ini`.</small>
                        </div>
                        <div class="field">
                            <label>HTTP Endpoint</label>
                            <input name="http_endpoint" value="{{ $selectedProfile->http_endpoint }}" placeholder="/api/http-data" required>
                        </div>
                    </div>
                    <p class="note">Effective MQTT server for generated firmware: <strong>{{ $effectiveMqttServer ?: '-' }}</strong> (resolved with priority: `mqtt_broker` -> `server_host`).</p>

                    <div class="row">
                        <div class="field">
                            <label>MQTT Broker Override</label>
                            <input name="mqtt_broker" value="{{ $selectedProfile->mqtt_broker }}" placeholder="202.154.58.51" required>
                            <small>Primary MQTT server. Used for `ESP_MQTT_BROKER` and generated firmware output.</small>
                        </div>
                        <div class="field">
                            <label>Server Host (Auto)</label>
                            <input value="{{ $selectedProfile->server_host }}" readonly>
                            <small>Auto-derived from HTTP Base URL host. This field is managed automatically.</small>
                        </div>
                    </div>

                    <div class="row-3">
                        <div class="field">
                            <label>MQTT Port</label>
                            <input name="mqtt_port" type="number" min="1" max="65535" value="{{ $selectedProfile->mqtt_port }}" required>
                        </div>
                        <div class="field">
                            <label>MQTT Topic</label>
                            <input name="mqtt_topic" value="{{ $selectedProfile->mqtt_topic }}" required>
                        </div>
                        <div class="field">
                            <label>HTTP TLS Insecure</label>
                            <select name="http_tls_insecure">
                                <option value="1" @selected((bool) $selectedProfile->http_tls_insecure)>true</option>
                                <option value="0" @selected(!(bool) $selectedProfile->http_tls_insecure)>false</option>
                            </select>
                            <small>Set true when using HTTPS endpoint without CA bundle on ESP32.</small>
                        </div>
                    </div>

                    <div class="row">
                        <div class="field">
                            <label>MQTT User</label>
                            <input name="mqtt_user" value="{{ $selectedProfile->mqtt_user }}" required>
                        </div>
                        <div class="field">
                            <label>MQTT Password</label>
                            <input name="mqtt_password" value="{{ $selectedProfile->mqtt_password }}" required>
                        </div>
                    </div>

                    <div class="row-3">
                        <div class="field">
                            <label>Sensor Interval (ms)</label>
                            <input name="sensor_interval_ms" type="number" min="500" max="3600000" value="{{ $selectedProfile->sensor_interval_ms ?? 5000 }}">
                            <small>Maps to `ESP_SENSOR_INTERVAL_MS` in `platformio.ini` and `INTERVAL_SENSOR` in `main.cpp`.</small>
                        </div>
                        <div class="field">
                            <label>HTTP Interval (ms)</label>
                            <input name="http_interval_ms" type="number" min="500" max="3600000" value="{{ $selectedProfile->http_interval_ms ?? 10000 }}">
                            <small>Maps to `ESP_HTTP_INTERVAL_MS` and `INTERVAL_HTTP`.</small>
                        </div>
                        <div class="field">
                            <label>MQTT Interval (ms)</label>
                            <input name="mqtt_interval_ms" type="number" min="500" max="3600000" value="{{ $selectedProfile->mqtt_interval_ms ?? 10000 }}">
                            <small>Maps to `ESP_MQTT_INTERVAL_MS` and `INTERVAL_MQTT`.</small>
                        </div>
                    </div>

                    <div class="row-3">
                        <div class="field">
                            <label>DHT Min Read Interval (ms)</label>
                            <input name="dht_min_read_interval_ms" type="number" min="250" max="120000" value="{{ $selectedProfile->dht_min_read_interval_ms ?? 1500 }}">
                            <small>Maps to `ESP_DHT_MIN_READ_INTERVAL_MS` and `DHT_MIN_READ_INTERVAL_MS`.</small>
                        </div>
                        <div class="field">
                            <label>HTTP Read Timeout (ms)</label>
                            <input name="http_read_timeout_ms" type="number" min="1000" max="120000" value="{{ $selectedProfile->http_read_timeout_ms ?? 5000 }}">
                            <small>Maps to `ESP_HTTP_READ_TIMEOUT_MS` and `HTTP_CLIENT_TIMEOUT`.</small>
                        </div>
                        <div class="field">
                            <label>MQTT Max Packet Size</label>
                            <input name="mqtt_max_packet_size" type="number" min="256" max="65535" value="{{ $selectedProfile->mqtt_max_packet_size ?? 2048 }}">
                            <small>Maps to `MQTT_MAX_PACKET_SIZE` build flag.</small>
                        </div>
                    </div>

                    <div class="row-3">
                        <div class="field">
                            <label>Core Debug Level (0-5)</label>
                            <input name="core_debug_level" type="number" min="0" max="5" value="{{ $selectedProfile->core_debug_level ?? 0 }}">
                            <small>Maps to `CORE_DEBUG_LEVEL` build flag.</small>
                        </div>
                        <div class="field">
                            <label>Monitor Speed</label>
                            <input name="monitor_speed" type="number" min="1200" max="3000000" value="{{ $selectedProfile->monitor_speed ?? 115200 }}">
                            <small>Maps to `monitor_speed` in `platformio.ini`.</small>
                        </div>
                        <div class="field">
                            <label>DHT Model Note</label>
                            <small>
                                For this firmware base (Adafruit DHT), `AM2302` is emitted as `DHT22`, and `AUTO_DETECT` fallback uses `DHT11`.
                            </small>
                        </div>
                    </div>

                    <div class="row">
                        <div class="field">
                            <label>Monitor Port (optional)</label>
                            <input name="monitor_port" value="{{ $selectedProfile->monitor_port }}" placeholder="COM5 / /dev/ttyUSB0">
                            <small>If empty, `monitor_port` line is removed so PlatformIO can auto-detect.</small>
                        </div>
                        <div class="field">
                            <label>Upload Port (optional)</label>
                            <input name="upload_port" value="{{ $selectedProfile->upload_port }}" placeholder="COM5 / /dev/ttyUSB0">
                            <small>If empty, `upload_port` line is removed so PlatformIO can auto-detect.</small>
                        </div>
                    </div>

                    <div class="field">
                        <label>Extra Build Flags (optional, one per line)</label>
                        <textarea name="extra_build_flags">{{ $selectedProfile->extra_build_flags }}</textarea>
                        <small>Managed macros (including `ESP_DEVICE_ID`) are reserved by this panel and will be ignored if added here.</small>
                    </div>

                    <div class="actions">
                        <button id="firmware-profile-save-btn" type="submit" class="btn primary" disabled>Save Firmware Profile</button>
                    </div>
                </form>
            </div>

            <div class="panel section-gap">
                <h2>Generated Firmware Bundle</h2>
                <p class="sub">Output below is generated from selected device profile + active runtime override.</p>
                <p class="note">Generated firmware target: <strong>Device ID {{ $selectedDevice->id }}</strong>.</p>
                <p id="firmware-action-state" class="note">
                    @if ($workspaceInSync ?? false)
                        Workspace firmware sudah sinkron dengan profile tersimpan.
                    @else
                        Workspace firmware berbeda dengan profile tersimpan. Gunakan Apply to Workspace untuk sinkronisasi.
                    @endif
                </p>
                <div class="actions" style="margin-bottom:10px;">
                    <a class="btn" href="{{ route('admin.config.devices.firmware.main', $selectedDevice, false) }}">Download main.cpp</a>
                    <a class="btn" href="{{ route('admin.config.devices.firmware.platformio', $selectedDevice, false) }}">Download platformio.ini</a>
                    <form id="firmware-apply-form" method="POST" action="{{ route('admin.config.devices.firmware.apply', $selectedDevice, false) }}">
                        @csrf
                        <button
                            id="firmware-apply-btn"
                            class="btn primary"
                            type="submit"
                            @disabled($workspaceInSync ?? false)
                            data-workspace-sync="{{ ($workspaceInSync ?? false) ? '1' : '0' }}"
                        >
                            Apply to Workspace
                        </button>
                    </form>
                    <form method="POST" action="{{ route('admin.config.devices.firmware.build', $selectedDevice, false) }}">
                        @csrf
                        <button class="btn" type="submit">Build Firmware</button>
                    </form>
                    <form method="POST" action="{{ route('admin.config.devices.firmware.upload', $selectedDevice, false) }}">
                        @csrf
                        <button class="btn warn" type="submit">Build & Upload</button>
                    </form>
                </div>
                <p class="note">Build/Upload will always regenerate latest firmware from selected profile, apply to workspace, then run PlatformIO command in <code>ESP32_Firmware</code>. Manual edits directly in generated workspace files will be overwritten by this flow.</p>
                <p class="note">Use the pencil icon to edit the full file in a dedicated editor. Saved source edits only override the selected device, while device lain tetap memakai generated standard template.</p>

                @if (is_array($firmwareCliResult))
                    <div class="field section-gap">
                        <label>Last Firmware CLI Result</label>
                        <small>
                            Mode: <strong>{{ strtoupper((string) ($firmwareCliResult['mode'] ?? '-')) }}</strong> |
                            Status: <strong>{{ !empty($firmwareCliResult['ok']) ? 'SUCCESS' : 'FAILED' }}</strong> |
                            Exit: <strong>{{ (int) ($firmwareCliResult['exit_code'] ?? -1) }}</strong> |
                            Timeout: <strong>{{ (int) ($firmwareCliResult['timeout_seconds'] ?? 0) }}s</strong>
                        </small>
                        <small>Command: <code>{{ (string) ($firmwareCliResult['command'] ?? '-') }}</code></small>
                        <small>Workdir: <code>{{ (string) ($firmwareCliResult['workdir'] ?? '-') }}</code></small>
                        @if (!empty($firmwareCliResult['backup_dir']))
                            <small>Workspace backup: <code>{{ (string) $firmwareCliResult['backup_dir'] }}</code></small>
                        @endif
                        <textarea readonly>{{ (string) ($firmwareCliResult['output'] ?? '') }}</textarea>
                    </div>
                @endif

                <div
                    id="webflash-panel"
                    class="field section-gap"
                    data-prepare-url="{{ route('admin.config.devices.firmware.webflash.prepare', $selectedDevice, false) }}"
                >
                    <label>Web Flash (Remote Build + Client USB)</label>
                    <small>
                        Cocok untuk server remote tanpa USB. Build dilakukan di server, lalu flashing dilakukan di browser client yang terhubung langsung ke ESP32 via Web Serial.
                    </small>
                    <div class="actions section-gap">
                        <button type="button" class="btn" id="webflash-prepare-btn">Prepare Web Flash Build</button>
                        <button type="button" class="btn" id="webflash-connect-btn">Connect USB Device</button>
                        <button type="button" class="btn primary" id="webflash-flash-btn">Flash From Browser</button>
                    </div>
                    <div id="webflash-status" class="note section-gap">Status: idle</div>
                    <textarea id="webflash-log" class="webflash-log" readonly></textarea>

                    <div class="actions section-gap">
                        <div class="field" style="max-width: 180px; margin: 0;">
                            <label for="serial-monitor-baud">Serial Baud</label>
                            <select id="serial-monitor-baud">
                                @foreach ($serialBaudOptions as $baudOption)
                                    <option value="{{ $baudOption }}" @selected((int) $baudOption === $serialDefaultBaud)>{{ $baudOption }}</option>
                                @endforeach
                            </select>
                        </div>
                        <button type="button" class="btn" id="serial-monitor-toggle-btn">Start Serial Monitor</button>
                        <button type="button" class="btn" id="serial-monitor-clear-btn">Clear Serial Log</button>
                    </div>
                    <div id="serial-monitor-status" class="note section-gap">Serial Monitor: idle</div>
                    <textarea id="serial-monitor-log" class="serial-monitor-log" readonly></textarea>
                </div>

                @php
                    $mainOverrideActive = filled($selectedProfile->custom_main_cpp ?? null);
                    $platformioOverrideActive = filled($selectedProfile->custom_platformio_ini ?? null);
                @endphp
                <div class="code-preview-grid">
                    <div class="field">
                        <div class="code-preview-header">
                            <label>
                                main.cpp
                                <span class="tag {{ $mainOverrideActive ? 'warn' : '' }}">
                                    {{ $mainOverrideActive ? 'Custom Override' : 'Generated Standard' }}
                                </span>
                            </label>
                            <button
                                type="button"
                                class="icon-btn firmware-edit-btn"
                                title="Edit full main.cpp"
                                aria-label="Edit full main.cpp"
                                data-file-label="main.cpp"
                                data-edit-url="{{ route('admin.config.devices.firmware.editor', ['device' => $selectedDevice, 'target' => 'main-cpp'], false) }}"
                            >
                                <svg viewBox="0 0 24 24" fill="none" aria-hidden="true">
                                    <path d="M4 20h4l10-10-4-4L4 16v4Z" stroke="currentColor" stroke-width="1.8" stroke-linejoin="round"/>
                                    <path d="m12 6 4 4" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/>
                                </svg>
                            </button>
                        </div>
                        <textarea readonly class="code-preview-textarea">{{ $renderedFirmware['main_cpp'] }}</textarea>
                    </div>
                    <div class="field">
                        <div class="code-preview-header">
                            <label>
                                platformio.ini
                                <span class="tag {{ $platformioOverrideActive ? 'warn' : '' }}">
                                    {{ $platformioOverrideActive ? 'Custom Override' : 'Generated Standard' }}
                                </span>
                            </label>
                            <button
                                type="button"
                                class="icon-btn firmware-edit-btn"
                                title="Edit full platformio.ini"
                                aria-label="Edit full platformio.ini"
                                data-file-label="platformio.ini"
                                data-edit-url="{{ route('admin.config.devices.firmware.editor', ['device' => $selectedDevice, 'target' => 'platformio-ini'], false) }}"
                            >
                                <svg viewBox="0 0 24 24" fill="none" aria-hidden="true">
                                    <path d="M4 20h4l10-10-4-4L4 16v4Z" stroke="currentColor" stroke-width="1.8" stroke-linejoin="round"/>
                                    <path d="m12 6 4 4" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/>
                                </svg>
                            </button>
                        </div>
                        <textarea readonly class="code-preview-textarea">{{ $renderedFirmware['platformio_ini'] }}</textarea>
                    </div>
                </div>
                <div class="field section-gap">
                    <label>Upload Instructions</label>
                    <textarea readonly>{{ $renderedFirmware['instructions'] }}</textarea>
                </div>
            </div>
        @endif
    </div>

    <script>
        (function () {
            document.querySelectorAll('.firmware-edit-btn').forEach((button) => {
                button.addEventListener('click', () => {
                    const editUrl = button.getAttribute('data-edit-url') || '';
                    const fileLabel = button.getAttribute('data-file-label') || 'firmware file';
                    if (!editUrl) {
                        return;
                    }

                    const confirmed = window.confirm(
                        'Edit full ' + fileLabel + ' untuk device terpilih? Save dari editor akan override generated standard hanya untuk device ini.'
                    );

                    if (confirmed) {
                        window.location.href = editUrl;
                    }
                });
            });

            const quickForm = document.getElementById('quick-runtime-form');
            const quickSaveButton = document.getElementById('quick-runtime-save-btn');
            if (quickForm && quickSaveButton) {
                const quickFields = Array.from(quickForm.querySelectorAll('input[name], select[name], textarea[name]'));
                const snapshotField = (field) => {
                    const type = (field.getAttribute('type') || '').toLowerCase();
                    if (type === 'checkbox' || type === 'radio') {
                        return field.checked ? '1' : '0';
                    }

                    return (field.value || '').trim();
                };
                const snapshotQuickForm = () => quickFields.map((field) => `${field.name}=${snapshotField(field)}`).join('&');
                const initialQuickSnapshot = snapshotQuickForm();
                const refreshQuickSaveButton = () => {
                    quickSaveButton.disabled = snapshotQuickForm() === initialQuickSnapshot;
                };

                quickForm.addEventListener('input', refreshQuickSaveButton);
                quickForm.addEventListener('change', refreshQuickSaveButton);
                refreshQuickSaveButton();
            }

            const editForm = document.getElementById('edit-device-form');
            const updateDeviceButton = document.getElementById('update-device-btn');
            if (editForm && updateDeviceButton) {
                const editFields = Array.from(editForm.querySelectorAll('input[name]'));
                const snapshotEditForm = () => editFields
                    .map((field) => `${field.name}=${(field.value || '').trim()}`)
                    .join('&');
                const initialEditSnapshot = snapshotEditForm();
                const refreshUpdateButton = () => {
                    updateDeviceButton.disabled = snapshotEditForm() === initialEditSnapshot;
                };

                editForm.addEventListener('input', refreshUpdateButton);
                editForm.addEventListener('change', refreshUpdateButton);
                refreshUpdateButton();
            }

            const deleteForm = document.getElementById('delete-device-form');
            const deleteButton = document.getElementById('delete-device-btn');
            const confirmDeleteInput = document.getElementById('confirm_delete');
            const purgeCheckbox = document.getElementById('purge_experiments');
            if (deleteForm && deleteButton && confirmDeleteInput && purgeCheckbox) {
                const hasRows = deleteForm.dataset.hasRows === '1';
                const refreshDeleteButton = () => {
                    const confirmOk = (confirmDeleteInput.value || '').trim().toUpperCase() === 'DELETE';
                    const purgeOk = !hasRows || purgeCheckbox.checked;
                    deleteButton.disabled = !(confirmOk && purgeOk);
                };

                confirmDeleteInput.addEventListener('input', refreshDeleteButton);
                purgeCheckbox.addEventListener('change', refreshDeleteButton);
                refreshDeleteButton();
            }
        })();
    </script>

    <script>
        (function () {
            const profileForm = document.getElementById('firmware-profile-form');
            const saveButton = document.getElementById('firmware-profile-save-btn');
            const applyButton = document.getElementById('firmware-apply-btn');
            const stateNode = document.getElementById('firmware-action-state');

            if (!profileForm || !saveButton || !applyButton) {
                return;
            }

            const workspaceSynced = applyButton.dataset.workspaceSync === '1';
            const trackedFields = Array.from(profileForm.querySelectorAll('input[name], select[name], textarea[name]'));
            const snapshotField = (field) => {
                const type = (field.getAttribute('type') || '').toLowerCase();
                if (type === 'checkbox' || type === 'radio') {
                    return field.checked ? '1' : '0';
                }

                return (field.value || '').trim();
            };
            const snapshotForm = () => trackedFields
                .map((field) => `${field.name}=${snapshotField(field)}`)
                .join('&');

            const initialSnapshot = snapshotForm();

            const refreshActionButtons = () => {
                const dirty = snapshotForm() !== initialSnapshot;
                saveButton.disabled = !dirty;
                applyButton.disabled = !(dirty || !workspaceSynced);

                if (!stateNode) {
                    return;
                }

                if (dirty) {
                    stateNode.textContent = 'Unsaved profile changes detected. Save Firmware Profile first, then Apply to Workspace if workspace files must follow latest saved profile.';
                } else if (workspaceSynced) {
                    stateNode.textContent = 'Workspace firmware sudah sinkron dengan profile tersimpan.';
                } else {
                    stateNode.textContent = 'Saved profile and workspace firmware differ. Click Apply to Workspace to synchronize source files.';
                }
            };

            profileForm.addEventListener('input', refreshActionButtons);
            profileForm.addEventListener('change', refreshActionButtons);
            refreshActionButtons();
        })();
    </script>

    <script type="module">
        (function () {
            const panel = document.getElementById('webflash-panel');
            if (!panel) {
                return;
            }

            const prepareUrl = panel.getAttribute('data-prepare-url');
            const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';

            const prepareButton = document.getElementById('webflash-prepare-btn');
            const connectButton = document.getElementById('webflash-connect-btn');
            const flashButton = document.getElementById('webflash-flash-btn');
            const statusNode = document.getElementById('webflash-status');
            const logNode = document.getElementById('webflash-log');
            const serialMonitorToggleButton = document.getElementById('serial-monitor-toggle-btn');
            const serialMonitorClearButton = document.getElementById('serial-monitor-clear-btn');
            const serialMonitorStatusNode = document.getElementById('serial-monitor-status');
            const serialMonitorLogNode = document.getElementById('serial-monitor-log');
            const serialMonitorBaudSelect = document.getElementById('serial-monitor-baud');

            let manifest = null;
            let transport = null;
            let esploader = null;
            let device = null;
            let serialMonitorPort = null;
            let serialMonitorReader = null;
            let serialMonitorRunning = false;
            let serialMonitorTail = '';
            let serialMonitorLastChunkAt = 0;

            const terminal = {
                clean() {
                    if (logNode) {
                        logNode.value = '';
                    }
                },
                write(data) {
                    appendLog(typeof data === 'string' ? data : String(data), false);
                },
                writeLine(data) {
                    appendLog(typeof data === 'string' ? data : String(data), true);
                }
            };

            function setStatus(message, error = false) {
                if (!statusNode) {
                    return;
                }
                statusNode.textContent = 'Status: ' + message;
                statusNode.style.color = error ? '#fecaca' : '#9bb2c6';
            }

            function appendLog(message, newline = true) {
                if (!logNode) {
                    return;
                }
                logNode.value += message + (newline ? '\n' : '');
                logNode.scrollTop = logNode.scrollHeight;
            }

            function hasManifestReady() {
                return Boolean(manifest && Array.isArray(manifest.images) && manifest.images.length > 0);
            }

            function updateFlashButtonState() {
                if (!flashButton) {
                    return;
                }

                const enabled = hasManifestReady();
                flashButton.disabled = !enabled;
                flashButton.style.opacity = enabled ? '1' : '0.55';
                flashButton.style.cursor = enabled ? 'pointer' : 'not-allowed';
                flashButton.title = enabled ? '' : 'Jalankan "Prepare Web Flash Build" terlebih dahulu.';
            }

            function isUsbConnected() {
                return Boolean(esploader && transport);
            }

            function updateConnectButton() {
                if (!connectButton) {
                    return;
                }

                if (serialMonitorRunning) {
                    connectButton.textContent = 'Connect USB Device';
                    connectButton.classList.remove('danger');
                    connectButton.disabled = true;
                    connectButton.title = 'Stop Serial Monitor terlebih dahulu.';
                    return;
                }

                connectButton.disabled = false;
                connectButton.title = '';

                if (isUsbConnected()) {
                    connectButton.textContent = 'Disconnect USB';
                    connectButton.classList.add('danger');
                } else {
                    connectButton.textContent = 'Connect USB Device';
                    connectButton.classList.remove('danger');
                }
            }

            function setSerialMonitorStatus(message, error = false) {
                if (!serialMonitorStatusNode) {
                    return;
                }
                serialMonitorStatusNode.textContent = 'Serial Monitor: ' + message;
                serialMonitorStatusNode.style.color = error ? '#fecaca' : '#9bb2c6';
            }

            function appendSerialMonitorLog(message, newline = true) {
                if (!serialMonitorLogNode) {
                    return;
                }
                serialMonitorLogNode.value += message + (newline ? '\n' : '');
                serialMonitorLogNode.scrollTop = serialMonitorLogNode.scrollHeight;
            }

            function resolveSerialMonitorBaudRate() {
                const requestedBaud = Number(serialMonitorBaudSelect?.value || 115200);
                if (!Number.isFinite(requestedBaud) || requestedBaud < 1200) {
                    return 115200;
                }

                return Math.floor(requestedBaud);
            }

            function flushSerialMonitorTail(force = false) {
                if (!serialMonitorTail) {
                    return;
                }

                const now = Date.now();
                const readyToFlush = force
                    || serialMonitorTail.length >= 512
                    || (serialMonitorLastChunkAt > 0 && now - serialMonitorLastChunkAt >= 800);

                if (!readyToFlush) {
                    return;
                }

                appendSerialMonitorLog(serialMonitorTail, false);
                serialMonitorTail = '';
            }

            function sleep(ms) {
                return new Promise((resolve) => setTimeout(resolve, ms));
            }

            function updateSerialMonitorToggleButton() {
                if (!serialMonitorToggleButton) {
                    return;
                }

                if (serialMonitorRunning) {
                    serialMonitorToggleButton.textContent = 'Stop Serial Monitor';
                    serialMonitorToggleButton.classList.add('danger');
                } else {
                    serialMonitorToggleButton.textContent = 'Start Serial Monitor';
                    serialMonitorToggleButton.classList.remove('danger');
                }
            }

            function bytesToBinaryString(uint8Array) {
                const chunkSize = 0x8000;
                let output = '';

                for (let i = 0; i < uint8Array.length; i += chunkSize) {
                    const chunk = uint8Array.subarray(i, i + chunkSize);
                    output += String.fromCharCode(...chunk);
                }

                return output;
            }

            async function loadEspToolLib() {
                // Use bundled ESM build so browser does not fail on bare imports like `pako`.
                const candidates = [
                    'https://esm.sh/esptool-js@0.5.7?bundle',
                    'https://cdn.jsdelivr.net/npm/esptool-js@0.5.7/+esm',
                ];

                let lastError = null;
                for (const url of candidates) {
                    try {
                        return await import(url);
                    } catch (error) {
                        lastError = error;
                        appendLog('WARN: gagal memuat modul esptool-js dari ' + url + ' -> ' + (error?.message || String(error)));
                    }
                }

                throw lastError || new Error('Gagal memuat modul esptool-js.');
            }

            async function closeSerialMonitorPort() {
                if (serialMonitorReader) {
                    try {
                        await serialMonitorReader.cancel();
                    } catch (error) {
                        appendSerialMonitorLog('WARN: serial reader cancel issue: ' + (error?.message || String(error)));
                    }
                    try {
                        serialMonitorReader.releaseLock();
                    } catch (_) {
                        // ignore
                    }
                    serialMonitorReader = null;
                }

                if (!serialMonitorPort) {
                    return;
                }

                try {
                    if (serialMonitorPort.readable || serialMonitorPort.writable) {
                        await serialMonitorPort.close();
                    }
                } catch (error) {
                    appendSerialMonitorLog('WARN: serial close issue: ' + (error?.message || String(error)));
                }
            }

            async function stopSerialMonitor(options = {}) {
                const silent = options && options.silent === true;
                serialMonitorRunning = false;
                flushSerialMonitorTail(true);
                serialMonitorTail = '';
                await closeSerialMonitorPort();
                updateSerialMonitorToggleButton();
                updateConnectButton();

                if (!silent) {
                    setSerialMonitorStatus('stopped');
                    appendSerialMonitorLog('Serial monitor stopped.');
                }
            }

            async function startSerialMonitor() {
                if (!('serial' in navigator)) {
                    setSerialMonitorStatus('Web Serial not supported in this browser.', true);
                    appendSerialMonitorLog('ERROR: Web Serial API is not available.');
                    return;
                }

                if (isUsbConnected()) {
                    await disconnectDevice();
                    appendSerialMonitorLog('Info: USB flasher transport disconnected before serial monitor.');
                }

                try {
                    setSerialMonitorStatus('requesting USB serial access...');

                    if (!serialMonitorPort) {
                        serialMonitorPort = device || await navigator.serial.requestPort({});
                    }
                    if (!device) {
                        device = serialMonitorPort;
                    }

                    const baudRate = resolveSerialMonitorBaudRate();

                    serialMonitorRunning = true;
                    serialMonitorTail = '';
                    serialMonitorLastChunkAt = 0;
                    setSerialMonitorStatus('running @ ' + baudRate + ' baud');
                    appendSerialMonitorLog('Serial monitor started @ ' + baudRate + ' baud.');
                    updateSerialMonitorToggleButton();
                    updateConnectButton();

                    const decoder = new TextDecoder();
                    while (serialMonitorRunning) {
                        try {
                            if (!serialMonitorPort.readable && !serialMonitorPort.writable) {
                                await serialMonitorPort.open({
                                    baudRate,
                                    dataBits: 8,
                                    stopBits: 1,
                                    parity: 'none',
                                    flowControl: 'none',
                                    bufferSize: 16384,
                                });
                            }
                            // Keep serial line active but avoid forcing reset state.
                            if (typeof serialMonitorPort.setSignals === 'function') {
                                try {
                                    await serialMonitorPort.setSignals({
                                        dataTerminalReady: false,
                                        requestToSend: false,
                                    });
                                } catch (_) {
                                    // Ignore adapter that does not expose signal control.
                                }
                            }

                            if (!serialMonitorPort.readable) {
                                await sleep(250);
                                continue;
                            }

                            const reader = serialMonitorPort.readable.getReader();
                            serialMonitorReader = reader;
                            while (serialMonitorRunning) {
                                const { value, done } = await reader.read();
                                if (done) {
                                    appendSerialMonitorLog('Info: serial stream closed, waiting for reconnect...');
                                    break;
                                }

                                if (!value) {
                                    continue;
                                }

                                const chunk = decoder.decode(value, { stream: true });
                                serialMonitorLastChunkAt = Date.now();
                                const merged = serialMonitorTail + chunk;
                                const normalized = merged
                                    .replace(/\r\n/g, '\n')
                                    .replace(/\r/g, '\n');
                                const lines = normalized.split('\n');
                                serialMonitorTail = lines.pop() || '';
                                lines.forEach((line) => appendSerialMonitorLog(line));
                                flushSerialMonitorTail(false);
                            }
                        } catch (error) {
                            if (!serialMonitorRunning) {
                                break;
                            }
                            appendSerialMonitorLog('WARN: serial read loop issue: ' + (error?.message || String(error)));
                            await sleep(400);
                        } finally {
                            try {
                                serialMonitorReader?.releaseLock();
                            } catch (_) {
                                // ignore
                            }
                            serialMonitorReader = null;
                        }

                        if (!serialMonitorRunning) {
                            break;
                        }

                        try {
                            if (serialMonitorPort.readable || serialMonitorPort.writable) {
                                await serialMonitorPort.close();
                            }
                        } catch (_) {
                            // ignore close retry
                        }
                        await sleep(350);
                    }
                } catch (error) {
                    setSerialMonitorStatus('failed to start monitor', true);
                    appendSerialMonitorLog('ERROR: ' + (error?.message || String(error)));
                }
            }

            async function ensureConnected() {
                if (serialMonitorRunning) {
                    setStatus('Stop Serial Monitor terlebih dahulu.', true);
                    appendLog('ERROR: Serial monitor is running. Stop it before connecting Web Flash transport.');
                    return false;
                }

                if (esploader && transport) {
                    return true;
                }

                if (!('serial' in navigator)) {
                    setStatus('Web Serial tidak didukung browser ini (gunakan Chrome/Edge).', true);
                    appendLog('ERROR: Web Serial API is not available.');
                    return false;
                }

                try {
                    setStatus('Meminta akses USB serial...');
                    const { ESPLoader, Transport } = await loadEspToolLib();

                    if (!device) {
                        device = serialMonitorPort || await navigator.serial.requestPort({});
                    }

                    transport = new Transport(device, true);
                    esploader = new ESPLoader({
                        transport,
                        baudrate: 115200,
                        romBaudrate: 115200,
                        terminal,
                        debugLogging: false,
                    });

                    const chip = await esploader.main();
                    setStatus('Terhubung ke ' + chip);
                    appendLog('Connected chip: ' + chip);
                    updateConnectButton();
                    return true;
                } catch (error) {
                    setStatus('Gagal connect ke perangkat.', true);
                    appendLog('ERROR: ' + (error?.message || String(error)));
                    transport = null;
                    esploader = null;
                    updateConnectButton();
                    return false;
                }
            }

            async function disconnectDevice() {
                try {
                    if (transport) {
                        await transport.disconnect();
                    }
                } catch (error) {
                    appendLog('WARN: disconnect issue: ' + (error?.message || String(error)));
                } finally {
                    transport = null;
                    esploader = null;
                    device = null;
                    updateConnectButton();
                }
            }

            async function prepareArtifacts() {
                if (!prepareUrl) {
                    setStatus('Prepare URL tidak tersedia.', true);
                    return;
                }

                manifest = null;
                updateFlashButtonState();
                setStatus('Menjalankan build webflash di server...');
                appendLog('Preparing webflash artifacts from server...');

                try {
                    const response = await fetch(prepareUrl, {
                        method: 'POST',
                        headers: {
                            'X-CSRF-TOKEN': csrfToken,
                            'Accept': 'application/json',
                            'X-Requested-With': 'XMLHttpRequest',
                        },
                        credentials: 'same-origin',
                    });

                    const rawBody = await response.text();
                    let data = {};
                    try {
                        data = rawBody ? JSON.parse(rawBody) : {};
                    } catch (parseError) {
                        data = {};
                        appendLog('WARN: response prepare bukan JSON valid: ' + (parseError?.message || String(parseError)));
                    }

                    if (!response.ok || !data.ok) {
                        manifest = null;
                        updateFlashButtonState();
                        setStatus('Prepare gagal.', true);
                        appendLog('ERROR: HTTP ' + response.status + ' - ' + (data.message || 'Prepare failed.'));
                        if (data.build && data.build.output) {
                            appendLog(data.build.output);
                        } else if (rawBody) {
                            appendLog(rawBody);
                        }
                        return;
                    }

                    manifest = data;
                    updateFlashButtonState();
                    const totalBytes = (data.images || []).reduce((sum, item) => sum + (item.size || 0), 0);
                    setStatus('Artifacts siap. Total ' + (data.images || []).length + ' file.');
                    appendLog('Webflash artifacts ready. Env: ' + data.environment + ', bytes: ' + totalBytes);
                } catch (error) {
                    manifest = null;
                    updateFlashButtonState();
                    setStatus('Prepare gagal (network/server error).', true);
                    appendLog('ERROR: ' + (error?.message || String(error)));
                }
            }

            async function flashFromBrowser() {
                if (!hasManifestReady()) {
                    setStatus('Artifacts belum siap. Klik Prepare dulu.', true);
                    appendLog('WARN: Flash dibatalkan karena artifact belum siap.');
                    return;
                }

                const connected = await ensureConnected();
                if (!connected) {
                    return;
                }

                try {
                    setStatus('Mengunduh binary artifacts...');
                    const fileArray = [];

                    for (const image of manifest.images) {
                        appendLog('Downloading ' + image.name + '...');
                        const response = await fetch(image.url, {
                            method: 'GET',
                            credentials: 'same-origin',
                        });
                        if (!response.ok) {
                            throw new Error('Failed to download ' + image.name + ' artifact.');
                        }

                        const buffer = await response.arrayBuffer();
                        const binaryData = bytesToBinaryString(new Uint8Array(buffer));
                        fileArray.push({
                            address: Number(image.address),
                            data: binaryData,
                        });
                    }

                    setStatus('Flashing ke ESP32...');
                    appendLog('Starting flash operation...');

                    await esploader.writeFlash({
                        fileArray,
                        flashSize: 'keep',
                        flashMode: 'keep',
                        flashFreq: 'keep',
                        eraseAll: false,
                        compress: true,
                        reportProgress: (fileIndex, written, total) => {
                            const progress = total > 0 ? Math.floor((written / total) * 100) : 0;
                            setStatus('Flashing file #' + (fileIndex + 1) + ': ' + progress + '%');
                        },
                    });
                    await esploader.after();

                    setStatus('Flash selesai.');
                    appendLog('Flash completed successfully.');
                } catch (error) {
                    setStatus('Flash gagal.', true);
                    appendLog('ERROR: ' + (error?.message || String(error)));
                }
            }

            prepareButton?.addEventListener('click', async () => {
                await prepareArtifacts();
            });

            connectButton?.addEventListener('click', async () => {
                if (isUsbConnected()) {
                    await disconnectDevice();
                    setStatus('USB disconnected.');
                    appendLog('Disconnected from device.');
                    return;
                }

                await ensureConnected();
            });

            serialMonitorToggleButton?.addEventListener('click', async () => {
                if (serialMonitorRunning) {
                    await stopSerialMonitor();
                    return;
                }

                await startSerialMonitor();
            });

            serialMonitorClearButton?.addEventListener('click', () => {
                if (serialMonitorLogNode) {
                    serialMonitorLogNode.value = '';
                }
            });

            flashButton?.addEventListener('click', async () => {
                await flashFromBrowser();
            });

            if ('serial' in navigator) {
                navigator.serial.addEventListener('disconnect', async (event) => {
                    if (serialMonitorPort && event?.target === serialMonitorPort) {
                        await stopSerialMonitor({ silent: true });
                        serialMonitorPort = null;
                        setSerialMonitorStatus('USB disconnected.', true);
                        appendSerialMonitorLog('Device disconnected by browser/OS.');
                    }

                    if (device && event?.target === device) {
                        await disconnectDevice();
                        setStatus('USB disconnected.');
                        appendLog('Device disconnected by browser/OS.');
                    }
                });
            }

            updateConnectButton();
            updateSerialMonitorToggleButton();
            setSerialMonitorStatus('idle');
            updateFlashButtonState();
        })();
    </script>
</body>
</html>
