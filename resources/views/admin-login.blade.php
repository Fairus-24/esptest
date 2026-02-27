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
        label { display: block; margin-bottom: 8px; font-weight: 600; font-size: .92rem; }
        input {
            width: 100%;
            background: #0b1220;
            border: 1px solid #253047;
            color: var(--text);
            border-radius: 10px;
            padding: 11px 12px;
            font-size: .94rem;
            margin-bottom: 14px;
        }
        input:focus {
            outline: none;
            border-color: var(--accent);
            box-shadow: 0 0 0 2px rgba(34, 211, 238, .18);
        }
        .btn {
            width: 100%;
            border: 0;
            border-radius: 10px;
            padding: 11px 12px;
            font-weight: 700;
            color: #03242b;
            background: linear-gradient(90deg, #67e8f9, var(--accent));
            cursor: pointer;
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
        <p>Gunakan token admin untuk membuka panel konfigurasi runtime, provisioning device ESP32, dan generator firmware.</p>

        @if (session('admin_error'))
            <div class="alert err">{{ session('admin_error') }}</div>
        @endif
        @if (session('admin_status'))
            <div class="alert ok">{{ session('admin_status') }}</div>
        @endif
        @if ($errors->any())
            <div class="alert err">{{ $errors->first() }}</div>
        @endif

        <form method="POST" action="{{ route('admin.login.submit') }}">
            @csrf
            <label for="token">Admin Token</label>
            <input type="password" id="token" name="token" autocomplete="current-password" placeholder="Masukkan token admin">
            <button type="submit" class="btn">Login Admin</button>
        </form>

        <div class="hint">
            @if ($allowWithoutToken)
                Mode dev aktif: token kosong diizinkan oleh konfigurasi server.
            @else
                Token disimpan di environment server (`ADMIN_PANEL_TOKEN`).
            @endif
            <br>
            <a href="{{ route('dashboard') }}">Kembali ke Dashboard</a>
        </div>
    </div>
</body>
</html>

