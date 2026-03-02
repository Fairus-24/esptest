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
        }
        .btn.is-disabled {
            pointer-events: none;
            cursor: not-allowed;
            background: #334155;
            color: #cbd5e1;
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
        <p>Login admin menggunakan akun Google yang diizinkan untuk membuka panel konfigurasi runtime, provisioning device ESP32, dan generator firmware.</p>

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
            <span>Login dengan Google</span>
        </a>

        <div class="hint">
            @if ($googleLoginConfigured)
                Hanya email <strong>{{ $allowedGoogleEmail }}</strong> yang diizinkan.
            @else
                Google OAuth belum dikonfigurasi lengkap di server.
            @endif
            <br>
            <a href="{{ route('dashboard', [], false) }}">Kembali ke Dashboard</a>
        </div>
    </div>
</body>
</html>
