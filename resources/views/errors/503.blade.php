<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="theme-color" content="#5c4033">
    <title>Server sibuk — {{ config('pos.shop_name', config('app.name')) }}</title>
    <style>
        :root {
            --brand: #5c4033;
            --bg: #f6f1ea;
            --card: #fff;
            --text: #1e293b;
            --muted: #64748b;
        }
        * { box-sizing: border-box; }
        body {
            margin: 0;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 1.25rem;
            font-family: system-ui, -apple-system, Segoe UI, Roboto, sans-serif;
            background:
                radial-gradient(ellipse at top, rgba(92, 64, 51, 0.12), transparent 55%),
                var(--bg);
            color: var(--text);
        }
        .card {
            width: 100%;
            max-width: 22rem;
            background: var(--card);
            border-radius: 1.25rem;
            padding: 1.5rem 1.25rem;
            box-shadow: 0 12px 40px rgba(28, 25, 23, 0.12);
            text-align: center;
            border: 1px solid rgba(92, 64, 51, 0.12);
        }
        .icon { font-size: 2rem; line-height: 1; margin-bottom: 0.75rem; }
        h1 {
            margin: 0 0 0.5rem;
            font-size: 1.2rem;
            font-weight: 700;
            color: var(--brand);
        }
        p {
            margin: 0 0 1.25rem;
            font-size: 0.95rem;
            line-height: 1.45;
            color: var(--muted);
        }
        .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 100%;
            min-height: 2.75rem;
            border: 0;
            border-radius: 0.85rem;
            background: var(--brand);
            color: #fff;
            font-size: 0.95rem;
            font-weight: 600;
            cursor: pointer;
            text-decoration: none;
        }
        .btn:active { transform: scale(0.98); }
        .hint {
            margin-top: 0.85rem;
            font-size: 0.75rem;
            color: #94a3b8;
        }
    </style>
</head>
<body>
    <div class="card">
        <div class="icon" aria-hidden="true">⏳</div>
        <h1>Server sedang sibuk</h1>
        <p>Koneksi penuh sementara. Tunggu sebentar, lalu muat ulang halaman untuk melanjutkan pesanan atau kasir.</p>
        <button type="button" class="btn" onclick="window.location.reload()">Muat ulang</button>
        <p class="hint">Kode: 503</p>
    </div>
</body>
</html>
