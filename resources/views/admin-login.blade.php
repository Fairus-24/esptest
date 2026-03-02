<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Login - IoT Research</title>
    <style>
        :root {
            --bg: #0f172a;
            --panel: #111827;
            --line: #1f2937;
            --text: #e5e7eb;
            --muted: #9ca3af;
            --accent: #22d3ee;
            --danger: #ef4444;
            --ok: #22c55e;
        }
        * { box-sizing: border-box; }
        body {
            margin: 0;
            font-family: "Segoe UI", Tahoma, Geneva, Verdana, sans-serif;
            background: radial-gradient(circle at 20% 20%, #1e293b 0%, var(--bg) 55%);
            min-height: 100vh;
            color: var(--text);
            display: grid;
            place-items: center;
            padding: 20px;
        }
        .card {
            width: 100%;
            max-width: 460px;
            background: linear-gradient(160deg, rgba(17, 24, 39, .95), rgba(2, 6, 23, .96));
            border: 1px solid var(--line);
            border-radius: 16px;
            padding: 24px;
            box-shadow: 0 20px 45px rgba(0, 0, 0, .35);
        }
        h1 { margin: 0 0 8px; font-size: 1.2rem; }
        p { margin: 0 0 18px; color: var(--muted); font-size: .94rem; line-height: 1.45; }
        .btn {
            width: 100%;
            border: 0;
            border-radius: 10px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            padding: 11px 12px;
            font-weight: 700;
            color: #03242b;
            background: linear-gradient(90deg, #67e8f9, var(--accent));
            cursor: pointer;
            text-decoration: none;
            margin-bottom: 14px;
            box-shadow: 0 8px 14px rgba(2, 12, 27, 0.2);
            transition: background 0.18s ease, box-shadow 0.18s ease;
        }
        .btn:hover {
            background: linear-gradient(90deg, #45c7de, #17b0c5);
            box-shadow: 0 10px 16px rgba(2, 12, 27, 0.26);
        }
        .btn:active {
            background: linear-gradient(90deg, #3ab4ca, #1496a8);
        }
        .btn:focus-visible {
            outline: none;
            box-shadow: 0 0 0 3px rgba(255, 255, 255, 0.25), 0 0 0 6px rgba(66, 133, 244, 0.42);
        }
        .btn .google-icon {
            width: 18px;
            height: 18px;
            flex: 0 0 18px;
            display: block;
        }
        .btn .google-icon-wrap {
            width: 24px;
            height: 24px;
            border-radius: 999px;
            display: inline-grid;
            place-items: center;
            background: rgba(255, 255, 255, 0.22);
            border: 1px solid rgba(15, 23, 42, 0.18);
            transition: background 0.18s ease, box-shadow 0.18s ease, border-color 0.18s ease;
        }
        .btn .btn-label .label-hover {
            display: none;
        }
        .btn.is-disabled {
            pointer-events: none;
            cursor: not-allowed;
            background: #334155;
            color: #cbd5e1;
            box-shadow: none;
        }
        .btn.is-disabled .google-icon {
            opacity: 0.72;
        }
        .btn:hover .google-icon-wrap {
            background: #ffffff;
            border-color: rgba(15, 23, 42, 0.28);
            box-shadow: 0 2px 6px rgba(15, 23, 42, 0.22);
        }
        .btn:hover .google-icon {
            filter: saturate(1.16) contrast(1.1);
        }
        .btn:hover .btn-label .label-default {
            display: none;
        }
        .btn:hover .btn-label .label-hover {
            display: inline;
        }
        .alert {
            border-radius: 10px;
            padding: 10px 12px;
            margin-bottom: 14px;
            font-size: .9rem;
        }
        .alert.err { background: rgba(239, 68, 68, .12); border: 1px solid rgba(239, 68, 68, .35); color: #fecaca; }
        .alert.ok { background: rgba(34, 197, 94, .11); border: 1px solid rgba(34, 197, 94, .35); color: #bbf7d0; }
        .hint { margin-top: 10px; font-size: .82rem; color: var(--muted); }
        a { color: var(--accent); text-decoration: none; }
    </style>
</head>
<body>
    <div class="card">
        <h1>Admin Configuration Login</h1>
        <p>Login admin untuk membuka panel konfigurasi runtime, provisioning device ESP32, dan generator firmware.</p>

        @if (session('admin_error'))
            <div class="alert err">{{ session('admin_error') }}</div>
        @endif
        @if (session('admin_status'))
            <div class="alert ok">{{ session('admin_status') }}</div>
        @endif
        @if ($errors->any())
            <div class="alert err">{{ $errors->first() }}</div>
        @endif

        <a
            href="{{ $googleLoginConfigured ? route('admin.login.google.redirect', [], false) : '#' }}"
            class="btn{{ $googleLoginConfigured ? '' : ' is-disabled' }}"
            aria-disabled="{{ $googleLoginConfigured ? 'false' : 'true' }}"
        >
            <span class="google-icon-wrap">
                <svg class="google-icon" viewBox="0 0 533.5 544.3" aria-hidden="true" focusable="false">
                    <path fill="#4285F4" d="M533.5 278.4c0-17.8-1.5-35-4.4-51.6H272.1v97.7h146.9c-6.3 34-25.2 62.7-53.8 82v68h86.9c50.9-46.9 80.4-116.1 80.4-196.1z"/>
                    <path fill="#34A853" d="M272.1 544.3c72.6 0 133.5-24.1 178-65.8l-86.9-68c-24.1 16.2-54.8 25.8-91.1 25.8-70 0-129.2-47.3-150.4-110.9h-89.9v69.8c44.4 88 135.5 149.1 240.3 149.1z"/>
                    <path fill="#FBBC04" d="M121.7 325.4c-10.6-31.5-10.6-65.4 0-96.9v-69.8h-89.9c-39.8 79.3-39.8 173.5 0 252.8l89.9-69.8z"/>
                    <path fill="#EA4335" d="M272.1 107.7c38.2-.6 75 13.8 103.1 40.4l77.2-77.2C403.2 24.4 338.7-1.4 272.1 .1 167.3 .1 76.2 61.2 31.8 149.2l89.9 69.8c21.2-63.6 80.4-111.3 150.4-111.3z"/>
                </svg>
            </span>
            <span class="btn-label">
                <span class="label-default">Login dengan Google</span>
                <span class="label-hover">Lanjutkan dengan Google</span>
            </span>
        </a>

        <div class="hint">
            @if ($googleLoginConfigured)
                Lanjutkan dengan akun Google admin.
            @else
                Google OAuth belum dikonfigurasi lengkap di server.
            @endif
            <br>
            <a href="{{ route('dashboard', [], false) }}">Kembali ke Dashboard</a>
        </div>
    </div>
</body>
</html>
