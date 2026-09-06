<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="theme-color" content="#5c4033">
    <title>Server Error — Kedai Tjoan</title>
    <style>
        :root {
            --bg: #f6f1ea;
            --ink: #1c1410;
            --muted: #6b584a;
            --brand: #5c4033;
            --card: #ffffff;
            --line: #e0d5c8;
            --rose: #be123c;
            --rose-bg: #fff1f2;
            --rose-line: #fecdd3;
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
                radial-gradient(circle at top right, rgba(225, 29, 72, 0.08), transparent 35%),
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
            padding: 6px 10px;
            border-radius: 999px;
            background: var(--rose-bg);
            border: 1px solid var(--rose-line);
            color: var(--rose);
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
    </style>
</head>
<body>
    <main class="card" role="alert">
        <div class="badge">500 · Server Error</div>
        <h1>Terjadi gangguan sementara</h1>
        <p>
            Silakan coba lagi sebentar.
        </p>
        <div class="actions">
            <a class="btn btn-primary" href="{{ url()->previous() !== url()->current() ? url()->previous() : url('/products') }}">
                Coba lagi
            </a>
            <a class="btn btn-secondary" href="{{ url('/products') }}">Daftar menu</a>
            <a class="btn btn-secondary" href="{{ url('/') }}">Beranda</a>
        </div>
    </main>
</body>
</html>
