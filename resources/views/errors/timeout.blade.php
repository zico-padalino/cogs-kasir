<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="theme-color" content="#5c4033">
    <title>Server sibuk / timeout — Kedai Tjoan</title>
    <style>
        :root {
            --bg: #f6f1ea;
            --ink: #1c1410;
            --muted: #6b584a;
            --brand: #5c4033;
            --card: #ffffff;
            --line: #e0d5c8;
            --amber: #92400e;
            --amber-bg: #fffbeb;
            --amber-line: #fde68a;
        }
        * { box-sizing: border-box; }
        body {
            margin: 0;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 24px;
            font-family: "Segoe UI", system-ui, sans-serif;
            color: var(--ink);
            background:
                radial-gradient(circle at top left, rgba(184, 149, 108, 0.18), transparent 40%),
                linear-gradient(180deg, #fbf7f2 0%, var(--bg) 100%);
        }
        .card {
            width: 100%;
            max-width: 420px;
            background: var(--card);
            border: 1px solid var(--line);
            border-radius: 20px;
            padding: 28px 24px;
            box-shadow: 0 12px 40px rgba(28, 20, 16, 0.08);
        }
        .badge {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 6px 10px;
            border-radius: 999px;
            background: var(--amber-bg);
            border: 1px solid var(--amber-line);
            color: var(--amber);
            font-size: 12px;
            font-weight: 700;
            letter-spacing: 0.02em;
            text-transform: uppercase;
        }
        h1 {
            margin: 16px 0 8px;
            font-size: 1.55rem;
            line-height: 1.25;
        }
        p {
            margin: 0 0 12px;
            color: var(--muted);
            font-size: 0.95rem;
            line-height: 1.55;
        }
        .actions {
            display: grid;
            gap: 10px;
            margin-top: 22px;
        }
        a.btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-height: 44px;
            padding: 10px 16px;
            border-radius: 12px;
            text-decoration: none;
            font-weight: 700;
            font-size: 0.95rem;
        }
        .btn-primary {
            background: var(--brand);
            color: #fff;
        }
        .btn-secondary {
            background: #fff;
            color: var(--brand);
            border: 1px solid var(--line);
        }
        .hint {
            margin-top: 18px;
            font-size: 0.8rem;
            color: #8a7360;
        }
    </style>
</head>
<body>
    <main class="card" role="alert">
        <div class="badge">Timeout / server sibuk</div>
        <h1>Halaman terlalu lama dimuat</h1>
        <p>
            Saat isi resep atau tambah menu, server butuh waktu lebih lama dari batas hosting.
            Data biasanya tetap aman — coba buka lagi.
        </p>
        <div class="actions">
            @if (! empty($retryUrl))
                <a class="btn btn-primary" href="{{ $retryUrl }}">Coba buka lagi</a>
            @endif
            <a class="btn {{ empty($retryUrl) ? 'btn-primary' : 'btn-secondary' }}" href="{{ $productsUrl ?? url('/products') }}">
                Kembali ke daftar menu
            </a>
            <a class="btn btn-secondary" href="{{ $homeUrl ?? url('/') }}">Beranda</a>
        </div>
        <p class="hint">Kalau berulang, tunggu 10–20 detik lalu refresh. Hosting sedang penuh proses.</p>
    </main>
</body>
</html>
