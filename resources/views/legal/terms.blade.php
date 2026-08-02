@extends('layouts.legal')

@section('title', 'Syarat & Ketentuan')
@section('heading', 'Syarat & Ketentuan')

@section('content')
    <article class="legal-article">
        <p class="legal-lead">
            Dokumen ini diadaptasi dari template Syarat &amp; Ketentuan Midtrans (Retail)
            untuk situs pemesanan <strong>{{ $shopName }}</strong>
            (<a href="{{ $siteUrl }}" class="legal-inline-link">{{ $siteUrl }}</a>).
            Dengan menggunakan situs ini, Anda dianggap menyetujui ketentuan berikut.
        </p>

        <section>
            <h2>1. Kondisi Penggunaan</h2>
            <p>
                Situs {{ $shopName }} ditawarkan kepada Anda, pengguna, dengan syarat Anda menerima
                syarat, ketentuan, dan pemberitahuan yang tercantum di sini, serta ketentuan tambahan
                yang mungkin berlaku pada bagian tertentu dari situs.
            </p>
        </section>

        <section>
            <h2>2. Ikhtisar</h2>
            <p>
                Penggunaan situs ini merupakan persetujuan Anda atas seluruh syarat dan ketentuan.
                Jika Anda tidak menyetujui ketentuan ini, segera hentikan penggunaan situs dan jangan
                menggunakan informasi atau produk dari situs ini.
            </p>
        </section>

        <section>
            <h2>3. Perubahan Situs dan Syarat &amp; Ketentuan</h2>
            <p>
                {{ $shopName }} berhak mengubah, memodifikasi, memperbarui, atau menghentikan syarat,
                kondisi, tautan, konten, informasi, harga, dan materi lain di situs sewaktu-waktu tanpa
                pemberitahuan. Kami berhak menyesuaikan harga dari waktu ke waktu. Jika terjadi kesalahan
                harga, {{ $shopName }} berhak menolak pesanan. Penggunaan situs secara berkelanjutan
                setelah perubahan berarti Anda menyetujui perubahan tersebut.
            </p>
        </section>

        <section>
            <h2>4. Hak Cipta</h2>
            <p>
                Situs ini dimiliki dan dioperasikan oleh {{ $shopName }}. Kecuali dinyatakan lain,
                seluruh materi, merek, logo, dan konten di situs adalah milik {{ $shopName }} dan
                dilindungi hukum hak cipta Indonesia serta hukum yang berlaku. Materi tidak boleh
                disalin, diperbanyak, diubah, diunggah, diposting, atau didistribusikan tanpa izin
                tertulis sebelumnya dari {{ $shopName }}.
            </p>
        </section>

        <section>
            <h2>5. Pemesanan (Tanpa Wajib Akun)</h2>
            <p>
                Pelanggan dapat memesan melalui halaman publik
                <a href="{{ $siteUrl }}" class="legal-inline-link">{{ $siteUrl }}</a>
                tanpa membuat akun. Anda diminta mengisi nama pemesan dan memilih tipe pesanan
                (makan di tempat / dibawa pulang) sebelum mengirim pesanan ke kasir. Anda bertanggung
                jawab memberikan informasi yang akurat. Jangan menyalahgunakan sistem pemesanan atau
                menyamar sebagai pihak lain.
            </p>
        </section>

        <section>
            <h2>6. Komunikasi Elektronik</h2>
            <p>
                Anda setuju bahwa {{ $shopName }} dapat menghubungi Anda terkait pesanan, perubahan
                layanan, atau informasi lain yang relevan melalui saluran yang tersedia (misalnya email
                atau pesan yang Anda berikan saat pemesanan), sejauh diperlukan untuk menyelesaikan
                pesanan atau layanan.
            </p>
        </section>

        <section>
            <h2>7. Deskripsi Produk / Menu</h2>
            <p>
                Kami berusaha menampilkan informasi menu, harga dalam Rupiah (IDR), dan gambar produk
                seakurat mungkin. Namun tampilan warna atau detail visual dapat berbeda tergantung
                perangkat yang Anda gunakan. Ketersediaan menu dapat berubah sewaktu-waktu.
            </p>
        </section>

        <section>
            <h2>8. Serah Terima Pesanan</h2>
            <p>
                Pesanan {{ $shopName }} bersifat dine-in atau takeaway di lokasi usaha. Risiko dan
                tanggung jawab atas produk beralih kepada Anda setelah pesanan diserahkan di kedai
                atau diambil sebagai takeaway sesuai konfirmasi kasir.
            </p>
        </section>

        <section>
            <h2>9. Pengembalian / Pembatalan</h2>
            <p>
                Makanan dan minuman pada prinsipnya tidak dapat dikembalikan setelah diserahkan.
                Jika terjadi kesalahan pesanan atau masalah kualitas, laporkan pada hari yang sama
                kepada kasir/staf {{ $shopName }} agar dapat ditinjau untuk penggantian atau
                penyesuaian yang wajar. Pembatalan sebelum pesanan diproses mengikuti kebijakan kasir
                di lokasi.
            </p>
        </section>

        <section>
            <h2>10. Kebijakan Privasi</h2>
            <p>
                Informasi Anda aman bersama kami. {{ $shopName }} memahami pentingnya privasi pelanggan.
                Informasi yang Anda kirimkan tidak akan disalahgunakan, disalahgunakan, atau dijual
                kepada pihak lain. Data pribadi digunakan untuk menyelesaikan pesanan Anda.
                Rincian lengkap tersedia di
                <a href="{{ route('legal.privacy') }}" class="legal-inline-link">Kebijakan Privasi</a>.
            </p>
        </section>

        <section>
            <h2>11. Ganti Rugi</h2>
            <p>
                Anda setuju untuk mengganti kerugian, membela, dan membebaskan {{ $shopName }} dari
                segala klaim pihak ketiga, kewajiban, kerugian, atau biaya (termasuk biaya hukum yang
                wajar) yang timbul dari akses dan/atau penggunaan situs oleh Anda.
            </p>
        </section>

        <section>
            <h2>12. Penafian</h2>
            <p>
                {{ $shopName }} tidak menjamin akurasi, ketepatan, ketepatan waktu, atau kelengkapan
                materi di situs. Materi situs tidak selalu diperbarui secara terus-menerus.
                {{ $shopName }} tidak bertanggung jawab menyediakan konten yang telah kedaluwarsa atau
                dihapus.
            </p>
        </section>

        <section>
            <h2>13. Hukum yang Berlaku</h2>
            <p>Syarat &amp; Ketentuan ini diatur oleh hukum yang berlaku di Republik Indonesia.</p>
        </section>

        <section>
            <h2>14. Pertanyaan dan Masukan</h2>
            <p>
                Kami menerima pertanyaan, komentar, dan masukan terkait privasi atau informasi yang
                dikumpulkan dari Anda. Hubungi kami di
                <a href="mailto:{{ $contactEmail }}" class="legal-inline-link">{{ $contactEmail }}</a>.
            </p>
        </section>

        <p class="legal-notice">
            <strong>Pemberitahuan hukum.</strong>
            {{ $shopName }} adalah usaha kuliner yang menyediakan pemesanan menu melalui
            <a href="{{ $siteUrl }}" class="legal-inline-link">{{ $siteUrl }}</a>.
            © {{ date('Y') }} {{ $shopName }}. Semua hak dilindungi.
        </p>
    </article>
@endsection
