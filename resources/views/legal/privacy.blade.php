@extends('layouts.legal')

@section('title', 'Kebijakan Privasi')
@section('heading', 'Kebijakan Privasi')

@section('content')
    <article class="legal-article">
        <p class="legal-lead">
            Kebijakan ini menjelaskan bagaimana <strong>{{ $shopName }}</strong> mengumpulkan dan
            menggunakan informasi pada situs pemesanan
            <a href="{{ $siteUrl }}" class="legal-inline-link">{{ $siteUrl }}</a>.
        </p>

        <section>
            <h2>1. Informasi yang Kami Kumpulkan</h2>
            <p>Saat Anda memesan, kami dapat mengumpulkan:</p>
            <ul>
                <li>Nama pemesan dan catatan pesanan yang Anda masukkan</li>
                <li>Detail item menu, jumlah, tipe pesanan (dine-in / takeaway)</li>
                <li>Data teknis sesi yang diperlukan agar keranjang/pesanan berfungsi di perangkat Anda</li>
            </ul>
        </section>

        <section>
            <h2>2. Penggunaan Informasi</h2>
            <p>
                Informasi digunakan hanya untuk memproses, mengonfirmasi, dan menyelesaikan pesanan
                Anda di {{ $shopName }}, serta untuk keperluan operasional terkait (misalnya catatan
                transaksi di kasir).
            </p>
        </section>

        <section>
            <h2>3. Pembagian kepada Pihak Ketiga</h2>
            <p>
                Kami tidak menjual atau menyalahgunakan data pribadi Anda. Informasi tidak dibagikan
                kepada pihak ketiga kecuali diperlukan untuk menyelesaikan pesanan/pembayaran, atau
                jika diwajibkan oleh hukum yang berlaku.
            </p>
        </section>

        <section>
            <h2>4. Keamanan</h2>
            <p>
                Kami mengambil langkah yang wajar untuk menjaga informasi pesanan Anda. Meskipun demikian,
                tidak ada transmisi data melalui internet yang sepenuhnya bebas risiko.
            </p>
        </section>

        <section>
            <h2>5. Penyimpanan</h2>
            <p>
                Data pesanan disimpan selama diperlukan untuk operasional usaha, pembukuan, dan
                kewajiban hukum yang berlaku.
            </p>
        </section>

        <section>
            <h2>6. Perubahan Kebijakan</h2>
            <p>
                {{ $shopName }} dapat memperbarui kebijakan ini dari waktu ke waktu. Versi terbaru
                selalu tersedia di halaman ini.
            </p>
        </section>

        <section>
            <h2>7. Kontak</h2>
            <p>
                Untuk pertanyaan terkait privasi, hubungi
                <a href="mailto:{{ $contactEmail }}" class="legal-inline-link">{{ $contactEmail }}</a>.
                Lihat juga
                <a href="{{ route('legal.terms') }}" class="legal-inline-link">Syarat &amp; Ketentuan</a>.
            </p>
        </section>

        <p class="legal-notice">
            © {{ date('Y') }} {{ $shopName }}. Harga menu ditampilkan dalam Rupiah (IDR).
        </p>
    </article>
@endsection
