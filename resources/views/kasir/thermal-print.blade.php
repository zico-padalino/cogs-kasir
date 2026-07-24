<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Cetak Thermal #{{ $order->order_number }}</title>
    <style>
        :root { color-scheme: light; }
        body {
            margin: 0;
            font-family: system-ui, -apple-system, Segoe UI, sans-serif;
            background: #f8fafc;
            color: #0f172a;
            padding: 24px 16px 40px;
        }
        .wrap { max-width: 420px; margin: 0 auto; }
        .card {
            background: #fff;
            border: 1px solid #e2e8f0;
            border-radius: 16px;
            padding: 20px;
            box-shadow: 0 1px 2px rgb(15 23 42 / 6%);
        }
        h1 { font-size: 1.15rem; margin: 0 0 8px; }
        p { margin: 0 0 12px; color: #64748b; font-size: 0.9rem; line-height: 1.45; }
        .btn {
            display: block;
            text-align: center;
            text-decoration: none;
            background: #0f766e;
            color: #fff;
            font-weight: 700;
            padding: 14px 16px;
            border-radius: 12px;
            margin: 8px 0 16px;
        }
        .btn-secondary {
            background: #fff;
            color: #0f766e;
            border: 1px solid #99f6e4;
        }
        pre {
            margin: 0;
            padding: 14px;
            background: #f1f5f9;
            border-radius: 12px;
            font-family: ui-monospace, SFMono-Regular, Menlo, Consolas, monospace;
            font-size: 13px;
            line-height: 1.4;
            white-space: pre-wrap;
            word-break: break-word;
        }
        .status { font-size: 0.85rem; color: #0f766e; font-weight: 600; min-height: 1.2em; }
    </style>
</head>
<body>
    <div class="wrap">
        <div class="card">
            <h1>Cetak Thermal</h1>
            <p>
                Struk <strong>#{{ $order->order_number }}</strong>
                · kertas {{ $thermal['paper'] }}.
                Sama seperti Cetak PDF: ketuk sekali untuk membuka Thermer.
            </p>

            <p class="status" id="status">
                @if ($autoPrint)
                    Membuka Thermer…
                @endif
            </p>

            {{-- Link nyata (seperti Cetak PDF) — paling andal di Chrome Android --}}
            <a
                id="thermer-link"
                class="btn"
                href="{{ $thermal['thermer_url'] }}"
            >Cetak di Thermer</a>

            <a class="btn btn-secondary" href="{{ route('kasir.receipt', $order) }}">Kembali ke struk</a>

            <pre>{{ $thermal['thermer_share_text'] }}</pre>
        </div>
    </div>

    <script>
        (function () {
            var link = document.getElementById('thermer-link');
            var statusEl = document.getElementById('status');
            var autoPrint = {{ $autoPrint ? 'true' : 'false' }};
            var thermerUrl = @json($thermal['thermer_url']);

            function openThermer() {
                if (!thermerUrl) {
                    if (statusEl) statusEl.textContent = 'Data Thermer belum siap.';
                    return;
                }
                // Sama pola PDF: navigasi langsung ke target cetak
                window.location.href = thermerUrl;
            }

            if (autoPrint) {
                // Tab baru dari klik user → langsung buka Thermer (seperti PDF viewer)
                setTimeout(openThermer, 80);
                setTimeout(function () {
                    if (statusEl) {
                        statusEl.textContent = 'Jika Thermer belum terbuka, ketuk tombol hijau di atas.';
                    }
                }, 1500);
            }

            if (link) {
                link.addEventListener('click', function (e) {
                    // Biarkan browser handle href; backup via location
                    if (statusEl) statusEl.textContent = 'Membuka Thermer…';
                });
            }
        })();
    </script>
</body>
</html>
