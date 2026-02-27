<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Config - IoT Research</title>
    <style>
        :root {
            --bg: #0b1220;
            --panel: #101a2b;
            --line: #22324b;
            --text: #e5e7eb;
            --muted: #93a3ba;
            --accent: #22d3ee;
            --ok: #22c55e;
            --warn: #f59e0b;
            --err: #ef4444;
        }
        * { box-sizing: border-box; }
        body {
            margin: 0;
            background: radial-gradient(circle at 5% 0%, #1e293b 0%, var(--bg) 40%);
            color: var(--text);
            font-family: "Segoe UI", Tahoma, Geneva, Verdana, sans-serif;
        }
        .wrap { max-width: 1300px; margin: 0 auto; padding: 18px; }
        .topbar {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 14px;
        }
        .title h1 { margin: 0; font-size: 1.25rem; }
        .title p { margin: 4px 0 0; color: var(--muted); font-size: .9rem; }
        .actions { display: flex; gap: 8px; flex-wrap: wrap; }
        .btn {
            border: 1px solid var(--line);
            background: #111d30;
            color: var(--text);
            border-radius: 10px;
            padding: 9px 12px;
            text-decoration: none;
            cursor: pointer;
            font-size: .88rem;
            font-weight: 600;
        }
        .btn.primary {
            border-color: transparent;
            color: #022026;
            background: linear-gradient(90deg, #67e8f9, var(--accent));
        }
        .btn.warn { background: rgba(245, 158, 11, .12); border-color: rgba(245, 158, 11, .35); color: #fcd34d; }
        .grid {
            display: grid;
            grid-template-columns: 1.2fr 1fr;
            gap: 14px;
        }
        .panel {
            background: linear-gradient(170deg, rgba(16, 26, 43, .95), rgba(8, 14, 25, .96));
            border: 1px solid var(--line);
            border-radius: 14px;
            padding: 14px;
        }
        .panel h2 {
            margin: 0 0 8px;
            font-size: 1rem;
        }
        .panel .sub {
            margin: 0 0 12px;
            color: var(--muted);
            font-size: .88rem;
        }
        .alert {
            border-radius: 10px;
            padding: 10px 12px;
            font-size: .9rem;
            margin-bottom: 10px;
        }
        .alert.ok { background: rgba(34, 197, 94, .12); border: 1px solid rgba(34, 197, 94, .35); color: #bbf7d0; }
        .alert.err { background: rgba(239, 68, 68, .12); border: 1px solid rgba(239, 68, 68, .35); color: #fecaca; }
        .env-grid {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 10px;
        }
        .field {
            border: 1px solid var(--line);
            background: #0e1727;
            border-radius: 10px;
            padding: 10px;
        }
        .field label {
            display: block;
            margin-bottom: 6px;
            font-size: .85rem;
            font-weight: 700;
        }
        .field small {
            display: block;
            color: var(--muted);
            font-size: .76rem;
            line-height: 1.3;
            margin-top: 5px;
        }
        input, select, textarea {
            width: 100%;
            background: #09101d;
            border: 1px solid #2a3a53;
            color: var(--text);
            border-radius: 8px;
            padding: 8px 9px;
            font-size: .85rem;
        }
        textarea {
            min-height: 140px;
            font-family: Consolas, "Courier New", monospace;
            resize: vertical;
        }
        .group-label {
            font-size: .82rem;
            color: #7dd3fc;
            margin: 8px 0 2px;
            font-weight: 700;
        }
        .stack { display: flex; flex-direction: column; gap: 10px; }
        .row {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 10px;
        }
        .table {
            width: 100%;
            border-collapse: collapse;
            font-size: .84rem;
        }
        .table th, .table td {
            border-bottom: 1px solid var(--line);
            padding: 8px 6px;
            text-align: left;
        }
        .table th { color: #a5b4fc; font-weight: 700; }
        .chip {
            display: inline-block;
            padding: 3px 7px;
            border-radius: 999px;
            border: 1px solid rgba(34, 211, 238, .4);
            color: #67e8f9;
            font-size: .74rem;
            margin-left: 4px;
        }
        .footer-note {
            margin-top: 12px;
            color: var(--muted);
            font-size: .8rem;
        }
        @media (max-width: 1060px) {
            .grid { grid-template-columns: 1fr; }
            .env-grid { grid-template-columns: 1fr; }
            .row { grid-template-columns: 1fr; }
        }
    </style>
</head>
<body>
    <div class="wrap">
        <div class="topbar">
            <div class="title">
                <h1>Admin Configuration Panel</h1>
                <p>Kelola runtime environment, provisioning multi-device ESP32, dan generator firmware siap upload.</p>
            </div>
            <div class="actions">
                <a class="btn" href="{{ route('dashboard') }}">Dashboard</a>
                <a class="btn" href="{{ route('simulation.index') }}">Simulation</a>
                <form method="POST" action="{{ route('admin.logout') }}">
                    @csrf
                    <button type="submit" class="btn warn">Logout Admin</button>
                </form>
            </div>
        </div>

        @if (session('admin_status'))
            <div class="alert ok">{{ session('admin_status') }}</div>
        @endif
        @if ($errors->any())
            <div class="alert err">{{ $errors->first() }}</div>
        @endif

        <div class="grid">
            <div class="panel">
                <h2>Runtime Environment Overrides</h2>
                <p class="sub">Nilai disimpan di database sebagai runtime override (tanpa edit `.env` manual). Efek langsung dipakai aplikasi saat request berikutnya.</p>

                @php
                    $grouped = collect($settings)->groupBy('group');
                @endphp

                <form method="POST" action="{{ route('admin.config.runtime.save') }}" class="stack">
                    @csrf
                    @foreach ($grouped as $groupName => $items)
                        <div class="group-label">{{ $groupName }}</div>
                        <div class="env-grid">
                            @foreach ($items as $item)
                                @php
                                    $key = $item['key'];
                                    $inputValue = (string) ($item['input_value'] ?? '');
                                    $isBool = ($item['type'] ?? '') === 'boolean';
                                    $isSecret = (bool) ($item['secret'] ?? false);
                                @endphp
                                <div class="field">
                                    <label for="cfg_{{ $key }}">
                                        {{ $item['label'] }}
                                        @if ($item['stored'])
                                            <span class="chip">override</span>
                                        @endif
                                    </label>

                                    @if ($isBool)
                                        <select id="cfg_{{ $key }}" name="{{ $key }}">
                                            <option value="">Use default</option>
                                            <option value="1" @selected($inputValue === '1')>true</option>
                                            <option value="0" @selected($inputValue === '0')>false</option>
                                        </select>
                                    @else
                                        <input
                                            id="cfg_{{ $key }}"
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
                    @endforeach

                    <div class="actions">
                        <button class="btn primary" type="submit">Simpan Runtime Overrides</button>
                    </div>
                </form>

                <div class="group-label">.env Snippet (Generated)</div>
                <textarea readonly>{{ $envSnippet }}</textarea>
                <div class="footer-note">Snippet ini untuk referensi deploy permanen di server production jika nanti perlu sinkron ke file `.env`.</div>
            </div>

            <div class="stack">
                <div class="panel">
                    <h2>Tambah Device ESP32</h2>
                    <p class="sub">Tambah device baru agar langsung punya profil firmware terpisah.</p>
                    <form method="POST" action="{{ route('admin.config.devices.store') }}" class="stack">
                        @csrf
                        <input name="nama_device" placeholder="Nama device (contoh: ESP32-LAB-03)" required>
                        <input name="lokasi" placeholder="Lokasi (contoh: Lab Praktikum A)">
                        <button class="btn primary" type="submit">Tambah Device</button>
                    </form>
                </div>

                <div class="panel">
                    <h2>Daftar Device</h2>
                    <table class="table">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Nama</th>
                                <th>Lokasi</th>
                                <th>Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                        @forelse ($devices as $device)
                            <tr>
                                <td>{{ $device->id }}</td>
                                <td>{{ $device->nama_device }}</td>
                                <td>{{ $device->lokasi ?: '-' }}</td>
                                <td>
                                    <a class="btn" href="{{ route('admin.config.index', ['device_id' => $device->id]) }}">Pilih</a>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="4">Belum ada device.</td></tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        @if ($selectedDevice && $selectedProfile && $renderedFirmware)
            <div class="panel" style="margin-top:14px;">
                <h2>Firmware Profile: Device #{{ $selectedDevice->id }} ({{ $selectedDevice->nama_device }})</h2>
                <p class="sub">Atur board + koneksi device, lalu generate firmware siap upload.</p>

                <form method="POST" action="{{ route('admin.config.devices.profile.save', $selectedDevice) }}" class="stack">
                    @csrf
                    <div class="row">
                        <div><label>Board</label><select name="board">@foreach ($boardOptions as $board)<option value="{{ $board }}" @selected($selectedProfile->board === $board)>{{ $board }}</option>@endforeach</select></div>
                        <div><label>DHT Model</label><select name="dht_model">@foreach ($dhtModels as $model)<option value="{{ $model }}" @selected($selectedProfile->dht_model === $model)>{{ $model }}</option>@endforeach</select></div>
                    </div>
                    <div class="row">
                        <div><label>WiFi SSID</label><input name="wifi_ssid" value="{{ $selectedProfile->wifi_ssid }}" required></div>
                        <div><label>WiFi Password</label><input name="wifi_password" value="{{ $selectedProfile->wifi_password }}" required></div>
                    </div>
                    <div class="row">
                        <div><label>Server Host (HTTP)</label><input name="server_host" value="{{ $selectedProfile->server_host }}" required></div>
                        <div><label>HTTP Endpoint</label><input name="http_endpoint" value="{{ $selectedProfile->http_endpoint }}" required></div>
                    </div>
                    <div class="row">
                        <div><label>MQTT Host</label><input name="mqtt_host" value="{{ $selectedProfile->mqtt_host }}" required></div>
                        <div><label>MQTT Port</label><input name="mqtt_port" type="number" min="1" max="65535" value="{{ $selectedProfile->mqtt_port }}" required></div>
                    </div>
                    <div class="row">
                        <div><label>MQTT Topic</label><input name="mqtt_topic" value="{{ $selectedProfile->mqtt_topic }}" required></div>
                        <div><label>DHT Pin</label><input name="dht_pin" type="number" min="0" max="39" value="{{ $selectedProfile->dht_pin }}" required></div>
                    </div>
                    <div class="row">
                        <div><label>MQTT User</label><input name="mqtt_user" value="{{ $selectedProfile->mqtt_user }}" required></div>
                        <div><label>MQTT Password</label><input name="mqtt_password" value="{{ $selectedProfile->mqtt_password }}" required></div>
                    </div>
                    <div>
                        <label>Extra Build Flags (optional, 1 baris per flag)</label>
                        <textarea name="extra_build_flags">{{ $selectedProfile->extra_build_flags }}</textarea>
                    </div>
                    <button type="submit" class="btn primary">Simpan Profil Device</button>
                </form>
            </div>

            <div class="panel" style="margin-top:14px;">
                <h2>Generated Firmware Output</h2>
                <p class="sub">Template di bawah sudah disesuaikan dengan profile + runtime environment override terbaru.</p>
                <div class="actions" style="margin-bottom:10px;">
                    <a class="btn" href="{{ route('admin.config.devices.firmware.main', $selectedDevice) }}">Download main.cpp</a>
                    <a class="btn" href="{{ route('admin.config.devices.firmware.platformio', $selectedDevice) }}">Download platformio.ini</a>
                    <form method="POST" action="{{ route('admin.config.devices.firmware.apply', $selectedDevice) }}">
                        @csrf
                        <button class="btn primary" type="submit">Apply ke Workspace Firmware</button>
                    </form>
                </div>
                <div class="row">
                    <div>
                        <label>main.cpp (generated)</label>
                        <textarea readonly>{{ $renderedFirmware['main_cpp'] }}</textarea>
                    </div>
                    <div>
                        <label>platformio.ini (generated)</label>
                        <textarea readonly>{{ $renderedFirmware['platformio_ini'] }}</textarea>
                    </div>
                </div>
                <div style="margin-top:10px;">
                    <label>Upload Instructions</label>
                    <textarea readonly>{{ $renderedFirmware['instructions'] }}</textarea>
                </div>
            </div>
        @endif
    </div>
</body>
</html>

