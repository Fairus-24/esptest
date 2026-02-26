<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reset Data Eksperimen - IoT Research Dashboard</title>
    <link rel="icon" type="image/svg+xml" href="{{ asset('project-favicon.svg') }}?v=20260226">
    <link rel="icon" type="image/png" sizes="32x32" href="{{ asset('project-favicon.png') }}?v=20260226">
    <link rel="icon" type="image/x-icon" href="{{ asset('favicon.ico') }}?v=20260226">
    <link rel="apple-touch-icon" sizes="180x180" href="{{ asset('project-favicon.png') }}?v=20260226">
    <meta name="theme-color" content="#1f4fd7">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Manrope:wght@400;500;600;700;800&family=Space+Grotesk:wght@500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --primary: #1f4fd7;
            --secondary: #0e8c63;
            --mqtt-blue: #1654e6;
            --http-green: #09a26b;
            --danger-a: #dc2626;
            --danger-b: #e11d48;
            --warning-bg: rgba(254, 243, 199, 0.9);
            --warning-border: rgba(245, 158, 11, 0.35);
            --warning-text: #92400e;
            --bg-light: #f4f7fb;
            --text-dark: #111827;
            --text-light: #667085;
            --dashboard-bg-main: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%);
            --shadow: 0 16px 40px rgba(15, 23, 42, 0.08);
            --shadow-hover: 0 20px 44px rgba(15, 23, 42, 0.14);
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Manrope', 'Segoe UI', sans-serif;
            background: var(--dashboard-bg-main);
            background-attachment: fixed;
            min-height: 100vh;
            color: var(--text-dark);
            padding: 24px 16px;
            -webkit-font-smoothing: antialiased;
            -moz-osx-font-smoothing: grayscale;
        }

        .page-wrap {
            max-width: 980px;
            margin: 0 auto;
        }

        .hero {
            background: linear-gradient(136deg, rgba(13, 37, 84, 0.94), rgba(13, 86, 116, 0.92));
            border: 1px solid rgba(255, 255, 255, 0.22);
            border-radius: 20px;
            padding: 28px 24px;
            box-shadow: 0 20px 42px rgba(15, 23, 42, 0.22);
            color: #f8fafc;
            margin-bottom: 22px;
        }

        .hero h1 {
            display: flex;
            align-items: center;
            gap: 10px;
            font-family: 'Space Grotesk', 'Manrope', sans-serif;
            font-size: clamp(1.35rem, 1.1rem + 1vw, 1.95rem);
            margin-bottom: 8px;
            letter-spacing: 0.2px;
        }

        .hero p {
            color: rgba(248, 250, 252, 0.92);
            font-size: 0.96rem;
            line-height: 1.6;
        }

        .content-card {
            background: #ffffff;
            border-radius: 18px;
            border: 1px solid rgba(15, 23, 42, 0.06);
            box-shadow: var(--shadow);
            padding: 24px;
        }

        .summary-grid {
            margin: 6px 0 20px;
            display: grid;
            grid-template-columns: repeat(4, minmax(0, 1fr));
            gap: 12px;
        }

        .summary-item {
            background: var(--bg-light);
            border: 1px solid rgba(15, 23, 42, 0.08);
            border-radius: 14px;
            padding: 14px;
        }

        .summary-item .label {
            display: flex;
            align-items: center;
            gap: 8px;
            color: var(--text-light);
            font-size: 0.8rem;
            text-transform: uppercase;
            letter-spacing: 0.4px;
            margin-bottom: 7px;
            font-weight: 700;
        }

        .summary-item .value {
            font-size: 1.35rem;
            font-weight: 800;
            line-height: 1.1;
            color: var(--text-dark);
        }

        .summary-item .sub {
            margin-top: 4px;
            color: #475467;
            font-size: 0.82rem;
            font-weight: 600;
        }

        .summary-item.mqtt .value,
        .summary-item.mqtt .label i {
            color: var(--mqtt-blue);
        }

        .summary-item.http .value,
        .summary-item.http .label i {
            color: var(--http-green);
        }

        .warning-box {
            border: 1px solid var(--warning-border);
            background: var(--warning-bg);
            color: var(--warning-text);
            border-radius: 12px;
            padding: 12px 14px;
            margin-bottom: 18px;
            font-size: 0.9rem;
            font-weight: 700;
            line-height: 1.5;
        }

        .result-banner {
            border-radius: 12px;
            padding: 12px 14px;
            margin: 0 0 16px;
            font-size: 0.9rem;
            font-weight: 700;
            line-height: 1.5;
            border: 1px solid transparent;
        }

        .result-banner.success {
            color: #065f46;
            background: rgba(16, 185, 129, 0.12);
            border-color: rgba(16, 185, 129, 0.25);
        }

        .result-banner.error {
            color: #991b1b;
            background: rgba(239, 68, 68, 0.12);
            border-color: rgba(239, 68, 68, 0.28);
        }

        .actions {
            display: flex;
            align-items: center;
            gap: 10px;
            flex-wrap: wrap;
            margin-top: 18px;
        }

        .btn {
            border: none;
            text-decoration: none;
            border-radius: 12px;
            padding: 11px 16px;
            font-weight: 700;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            transition: transform 0.2s ease, box-shadow 0.2s ease, opacity 0.2s ease;
        }

        .btn:hover {
            transform: translateY(-1px);
        }

        .btn:disabled {
            opacity: 0.5;
            cursor: not-allowed;
            transform: none;
        }

        .btn-back {
            background: #e8eefb;
            color: #1f3a8a;
            border: 1px solid rgba(31, 58, 138, 0.16);
        }

        .btn-reset {
            background: linear-gradient(135deg, var(--danger-a), var(--danger-b));
            color: #fff;
            box-shadow: 0 12px 24px rgba(220, 38, 38, 0.26);
        }

        .confirm-wrap {
            border-top: 1px solid rgba(15, 23, 42, 0.08);
            margin-top: 18px;
            padding-top: 16px;
            display: grid;
            gap: 12px;
        }

        .confirm-check {
            display: flex;
            align-items: flex-start;
            gap: 10px;
            font-size: 0.92rem;
            color: #1f2937;
            font-weight: 600;
        }

        .confirm-check input {
            width: 18px;
            height: 18px;
            margin-top: 1px;
        }

        .confirm-label {
            font-size: 0.9rem;
            font-weight: 700;
            color: #374151;
        }

        .confirm-input {
            width: 100%;
            border: 1px solid rgba(15, 23, 42, 0.18);
            border-radius: 10px;
            padding: 10px 12px;
            font-size: 0.95rem;
            font-weight: 600;
            color: #111827;
            background: #fff;
            text-transform: uppercase;
        }

        .confirm-input:focus {
            outline: none;
            border-color: #1f4fd7;
            box-shadow: 0 0 0 3px rgba(31, 79, 215, 0.18);
        }

        .confirm-hint {
            font-size: 0.82rem;
            color: #6b7280;
            font-weight: 600;
        }

        .empty-note {
            margin-top: 6px;
            font-size: 0.88rem;
            color: #065f46;
            background: rgba(16, 185, 129, 0.12);
            border: 1px solid rgba(16, 185, 129, 0.24);
            border-radius: 10px;
            padding: 10px 12px;
            font-weight: 700;
        }

        .footer-note {
            margin-top: 20px;
            color: #f9fafb;
            text-align: center;
            font-size: 0.84rem;
            font-weight: 600;
            text-shadow: 0 1px 8px rgba(15, 23, 42, 0.3);
        }

        @media (max-width: 900px) {
            .summary-grid {
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }

            .content-card {
                padding: 18px;
            }
        }

        @media (max-width: 580px) {
            body {
                padding: 16px 12px;
            }

            .hero {
                padding: 18px 14px;
                border-radius: 16px;
            }

            .summary-grid {
                grid-template-columns: 1fr;
                gap: 10px;
            }

            .actions {
                flex-direction: column;
                align-items: stretch;
            }

            .btn {
                justify-content: center;
            }
        }
    </style>
</head>
<body>
    @php
        $statusMessage = $statusMessage ?? session('status');
        $statusType = $statusType ?? (is_string($statusMessage) && str_starts_with(strtolower($statusMessage), 'gagal') ? 'error' : 'success');
        $basePath = rtrim(request()->getBaseUrl(), '/');
        $resetDataPath = ($basePath !== '' ? $basePath : '') . '/reset-data';
        $dashboardPath = $basePath !== '' ? $basePath . '/' : '/';
    @endphp
    <main class="page-wrap">
        <section class="hero">
            <h1><i class="fas fa-triangle-exclamation"></i> Reset Data Eksperimen</h1>
            <p>
                Halaman ini digunakan untuk menghapus seluruh data eksperimen MQTT dan HTTP secara permanen
                dari database. Lakukan hanya jika Anda ingin memulai pengujian dari nol.
            </p>
        </section>

        <section class="content-card">
            @if(is_string($statusMessage) && $statusMessage !== '')
                <div class="result-banner {{ $statusType === 'error' ? 'error' : 'success' }}">
                    <i class="fas {{ $statusType === 'error' ? 'fa-circle-xmark' : 'fa-circle-check' }}"></i>
                    {{ $statusMessage }}
                </div>
            @endif

            <div class="summary-grid">
                <article class="summary-item">
                    <div class="label"><i class="fas fa-database"></i> Total Data</div>
                    <div class="value">{{ number_format($totalRows) }}</div>
                    <div class="sub">baris eksperimen tersimpan</div>
                </article>
                <article class="summary-item mqtt">
                    <div class="label"><i class="fas fa-broadcast-tower"></i> Data MQTT</div>
                    <div class="value">{{ number_format($mqttRows) }}</div>
                    <div class="sub">protokol MQTT</div>
                </article>
                <article class="summary-item http">
                    <div class="label"><i class="fas fa-server"></i> Data HTTP</div>
                    <div class="value">{{ number_format($httpRows) }}</div>
                    <div class="sub">protokol HTTP</div>
                </article>
                <article class="summary-item">
                    <div class="label"><i class="fas fa-clock"></i> Update Terakhir</div>
                    <div class="value" style="font-size:1rem; line-height:1.4;">{{ $latestWib }}</div>
                    <div class="sub">zona waktu Surabaya (WIB)</div>
                </article>
            </div>

            <div class="warning-box">
                <i class="fas fa-circle-exclamation"></i>
                Aksi reset akan menghapus semua data eksperimen dan tidak bisa di-undo.
                Setelah reset, dashboard akan kembali kosong sampai data baru masuk dari ESP32.
            </div>

            @if($totalRows === 0)
                <div class="empty-note">
                    Tidak ada data tersimpan saat ini. Tombol reset dinonaktifkan otomatis.
                </div>
            @endif

            <form id="resetDataPageForm" method="POST" action="{{ $resetDataPath }}">
                @csrf
                <div class="confirm-wrap">
                    <label class="confirm-check" for="confirmRisk">
                        <input type="checkbox" id="confirmRisk" name="confirm_risk">
                        <span>Saya memahami bahwa seluruh data MQTT dan HTTP akan dihapus permanen.</span>
                    </label>

                    <label class="confirm-label" for="confirmText">Ketik <strong>RESET</strong> untuk konfirmasi:</label>
                    <input id="confirmText" class="confirm-input" type="text" autocomplete="off" autocapitalize="characters" spellcheck="false" placeholder="Ketik RESET">
                    <div class="confirm-hint">Konfirmasi ini ditujukan untuk mencegah reset tidak sengaja.</div>
                </div>

                <div class="actions">
                    <button id="submitResetBtn" type="submit" class="btn btn-reset" disabled>
                        <i class="fas fa-trash-alt"></i>
                        Reset Data Eksperimen
                    </button>
                    <a href="{{ $dashboardPath }}" class="btn btn-back">
                        <i class="fas fa-arrow-left"></i>
                        Kembali ke Dashboard
                    </a>
                </div>
            </form>
        </section>

        <p class="footer-note">
            IoT Research Dashboard • MQTT vs HTTP • Laravel + ESP32 + Mosquitto
        </p>
    </main>

    <script>
        (function () {
            const totalRows = Number(@json($totalRows));
            const confirmRisk = document.getElementById('confirmRisk');
            const confirmText = document.getElementById('confirmText');
            const submitBtn = document.getElementById('submitResetBtn');

            if (!confirmRisk || !confirmText || !submitBtn) {
                return;
            }

            const syncButtonState = () => {
                const normalized = (confirmText.value || '').toUpperCase();
                if (confirmText.value !== normalized) {
                    confirmText.value = normalized;
                }
                const typed = normalized.trim();
                const hasData = totalRows > 0;
                submitBtn.disabled = !(hasData && confirmRisk.checked && typed === 'RESET');
            };

            confirmRisk.addEventListener('change', syncButtonState);
            confirmText.addEventListener('input', syncButtonState);
            syncButtonState();
        }());
    </script>
</body>
</html>

