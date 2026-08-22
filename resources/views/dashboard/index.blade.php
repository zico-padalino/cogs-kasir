@extends('layouts.app')

@section('title', 'Beranda')
@section('heading')
    Beranda
@endsection
@section('subheading')
    Ringkasan penjualan, modal, dan data usaha
@endsection

@section('content')
    <section class="mb-10">
        <div class="mb-4 flex flex-wrap items-end justify-between gap-2">
            <div>
                <h2 class="text-lg font-semibold text-slate-900">Penjualan {{ $today['label'] }}</h2>
                <p class="mt-1 text-sm text-slate-500">Omzet sebelum/setelah diskon, modal terjual, dan laba kotor.</p>
            </div>
        </div>

        <div class="mb-4 grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
            <x-stat-card label="Omzet sebelum diskon" :value="$format::rupiah($today['omzet_kotor'] ?? $today['omzet'])" color="slate" />
            <x-stat-card label="Total diskon" :value="$format::rupiah($today['diskon_total'] ?? 0)" color="amber" />
            <x-stat-card label="Omzet bersih" :value="$format::rupiah($today['omzet'])" color="green" />
        </div>

        <div class="mb-4 grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
            <x-stat-card label="Transaksi" :value="$format::number($today['count'], 0)" color="brand" />
            <x-stat-card label="Modal Terjual" :value="$format::rupiah($today['modal'])" color="slate" />
            <x-stat-card
                label="Laba Kotor"
                :value="$format::rupiah($today['laba']).($today['omzet'] > 0 ? ' ('.$format::number($today['margin'], 1).'%)' : '')"
                color="amber"
            />
        </div>

        @if ($today['count'] === 0)
            <div class="mb-4 card border-slate-200 bg-slate-50/60">
                <p class="text-sm font-medium text-slate-800">Belum ada penjualan hari ini</p>
                <p class="mt-1 text-sm text-slate-500">Data muncul otomatis setelah transaksi lunas di Kasir.</p>
            </div>
        @endif

        <div class="grid gap-4 sm:grid-cols-2">
            <div class="card">
                <p class="text-xs font-medium uppercase tracking-wide text-slate-500">Rata-rata per transaksi</p>
                <p class="mt-2 text-xl font-bold text-slate-900">{{ $format::rupiah($today['average']) }}</p>
            </div>
            <div class="card">
                <p class="text-xs font-medium uppercase tracking-wide text-slate-500">Omzet {{ $month['label'] }}</p>
                <p class="mt-2 text-xl font-bold text-slate-900">{{ $format::rupiah($month['omzet']) }}</p>
                <p class="mt-1 text-sm text-slate-500">
                    Sebelum diskon {{ $format::rupiah($month['omzet_kotor'] ?? $month['omzet']) }}
                    @if (($month['diskon_total'] ?? 0) > 0)
                        · diskon {{ $format::rupiah($month['diskon_total']) }}
                    @endif
                </p>
                <p class="mt-1 text-sm text-slate-500">
                    {{ $format::number($month['count'], 0) }} transaksi
                    · laba {{ $format::rupiah($month['laba']) }}
                    @if ($month['omzet'] > 0)
                        ({{ $format::number($month['margin'], 1) }}%)
                    @endif
                </p>
            </div>
        </div>

        <div class="mt-6">
            <x-table-card title="Omzet Harian" subtitle="7 hari terakhir">
                <table class="table-default">
                    <thead>
                        <tr>
                            <th>Tanggal</th>
                            <th>Transaksi</th>
                            <th>Omzet sebelum diskon</th>
                            <th>Diskon</th>
                            <th>Omzet bersih</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($dailyRevenue as $row)
                            <tr>
                                <td class="font-semibold text-slate-900">{{ $row['label'] }}</td>
                                <td>{{ $format::number($row['count'], 0) }}</td>
                                <td class="cell-money">{{ $format::rupiah($row['omzet_kotor']) }}</td>
                                <td class="cell-money">{{ $format::rupiah($row['diskon_total']) }}</td>
                                <td class="cell-highlight">{{ $format::rupiah($row['omzet']) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </x-table-card>
        </div>
    </section>

    <section class="mb-10">
        <x-table-card title="Stok Bahan Baku" subtitle="Sisa stok, nilai uang, dan laporan PDF dalam satu tempat" icon="📦">
            <x-slot:actions>
                <a href="{{ route('materials.index') }}" class="btn-outline btn-sm">Kelola bahan</a>
            </x-slot:actions>

            <div class="grid grid-cols-2 gap-px bg-slate-100 sm:grid-cols-4">
                <div class="bg-white px-4 py-3">
                    <p class="text-[11px] font-semibold uppercase tracking-wide text-slate-500">Jenis bahan</p>
                    <p class="mt-1 text-lg font-bold text-slate-900">{{ $format::number($materialStock['count'], 0) }}</p>
                </div>
                <div class="bg-white px-4 py-3">
                    <p class="text-[11px] font-semibold uppercase tracking-wide text-slate-500">Masih ada</p>
                    <p class="mt-1 text-lg font-bold text-emerald-700">{{ $format::number($materialStock['in_stock'], 0) }}</p>
                </div>
                <div class="bg-white px-4 py-3">
                    <p class="text-[11px] font-semibold uppercase tracking-wide text-slate-500">Habis</p>
                    <p class="mt-1 text-lg font-bold text-rose-700">{{ $format::number($materialStock['empty'], 0) }}</p>
                </div>
                <div class="bg-brand-50 px-4 py-3">
                    <p class="text-[11px] font-semibold uppercase tracking-wide text-brand-700">Total nilai sisa</p>
                    <p class="mt-1 text-lg font-bold tabular-nums text-brand-800">{{ $format::rupiah($materialStock['total_value']) }}</p>
                </div>
            </div>

            <div class="flex flex-col gap-3 border-t border-slate-100 px-4 py-3 sm:flex-row sm:flex-wrap sm:items-end sm:justify-between">
                <div class="min-w-0 flex-1">
                    @if ($materialStock['qty_by_unit'] !== [])
                        <p class="mb-1.5 text-[11px] font-semibold uppercase tracking-wide text-slate-500">Sisa fisik</p>
                        <div class="flex flex-wrap gap-1.5">
                            @foreach ($materialStock['qty_by_unit'] as $unit => $qty)
                                <span class="inline-flex items-center rounded-full border border-slate-200 bg-slate-50 px-2.5 py-1 text-xs font-semibold text-slate-800">
                                    {{ $format::number($qty) }}
                                    <span class="ml-1 font-medium text-slate-500">{{ $unit }}</span>
                                </span>
                            @endforeach
                        </div>
                    @else
                        <p class="text-sm text-slate-500">Belum ada sisa stok tercatat.</p>
                    @endif
                </div>

                <form
                    method="GET"
                    action="{{ route('materials.pdf') }}"
                    target="_blank"
                    class="flex flex-wrap items-end gap-2"
                >
                    <input type="hidden" name="autoprint" value="1">
                    <div>
                        <label class="form-label mb-1 text-[11px]" for="material-pdf-from">Dari</label>
                        <input id="material-pdf-from" type="date" name="from" value="{{ now()->startOfMonth()->toDateString() }}" class="form-input py-1.5 text-sm" required>
                    </div>
                    <div>
                        <label class="form-label mb-1 text-[11px]" for="material-pdf-to">Sampai</label>
                        <input id="material-pdf-to" type="date" name="to" value="{{ now()->toDateString() }}" class="form-input py-1.5 text-sm" required>
                    </div>
                    <button type="submit" class="btn-secondary btn-sm">Cetak PDF</button>
                </form>
            </div>

            @if ($materialStock['items']->isEmpty())
                <div class="border-t border-slate-100 px-5 py-8 text-center text-sm text-slate-500">
                    Belum ada bahan baku.
                    <a href="{{ route('materials.index') }}" class="font-semibold text-brand-700 hover:underline">Tambah di Bahan Baku →</a>
                </div>
            @else
                <table class="table-default border-t border-slate-100">
                    <thead>
                        <tr>
                            <th>Bahan</th>
                            <th>Sisa stok</th>
                            <th>Harga / satuan</th>
                            <th>Nilai sisa</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($materialStock['items'] as $row)
                            <tr class="{{ $row['qty'] <= 0 ? 'opacity-70' : '' }}">
                                <td class="font-semibold text-slate-900">{{ $row['name'] }}</td>
                                <td>
                                    <span class="{{ $row['qty'] <= 0 ? 'font-semibold text-rose-700' : 'text-slate-800' }}">
                                        {{ $format::number($row['qty']) }} {{ $row['unit'] }}
                                    </span>
                                </td>
                                <td class="cell-money">
                                    @if ($row['avg_cost'] > 0)
                                        {{ $format::rupiah($row['avg_cost']) }}/{{ $row['unit'] }}
                                    @else
                                        —
                                    @endif
                                </td>
                                <td class="cell-highlight">{{ $format::rupiah($row['value']) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @endif

            <x-slot:footer>
                <p class="text-sm font-medium text-slate-600">Total nilai sisa stok</p>
                <p class="text-xl font-bold tabular-nums text-brand-800">{{ $format::rupiah($materialStock['total_value']) }}</p>
            </x-slot:footer>
        </x-table-card>
    </section>

    <section class="mb-10">
        <div class="mb-4 flex flex-wrap items-end justify-between gap-3">
            <div>
                <h2 class="text-lg font-semibold text-slate-900">Dana Usaha Hari Ini</h2>
                <p class="mt-1 text-sm text-slate-500">Saldo awal + omzet bersih − pengeluaran (gaji & lain-lain) = saldo akhir.</p>
            </div>
            <a href="{{ route('business-funds.index') }}" class="btn-primary">Buka Dana Usaha</a>
        </div>
        <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
            <x-stat-card label="Saldo awal" :value="$format::rupiah($fundToday['opening'])" color="slate" />
            <x-stat-card label="+ Omzet bersih" :value="$format::rupiah($fundToday['revenue'])" color="green" />
            <x-stat-card label="− Pengeluaran" :value="$format::rupiah($fundToday['expense'])" color="rose" />
            <x-stat-card label="Saldo dana saat ini" :value="$format::rupiah($fundBalance)" color="brand" />
        </div>
        @if (($fundToday['expense'] ?? 0) > 0)
            <div class="mt-3 grid gap-3 sm:grid-cols-2">
                <div class="rounded-xl border border-violet-100 bg-violet-50/70 px-4 py-3 text-sm">
                    <p class="text-xs font-semibold uppercase tracking-wide text-violet-700">Potongan dari gaji</p>
                    <p class="mt-1 font-bold tabular-nums text-violet-950">− {{ $format::rupiah($fundToday['expense_gaji'] ?? 0) }}</p>
                </div>
                <div class="rounded-xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm">
                    <p class="text-xs font-semibold uppercase tracking-wide text-slate-600">Potongan lain-lain</p>
                    <p class="mt-1 font-bold tabular-nums text-slate-900">− {{ $format::rupiah($fundToday['expense_lainnya'] ?? 0) }}</p>
                </div>
            </div>
        @endif
    </section>

    <section class="mb-10">
        <div class="mb-4 flex flex-wrap items-end justify-between gap-3">
            <div>
                <h2 class="text-lg font-semibold text-slate-900">Perkiraan Pengeluaran</h2>
                <p class="mt-1 text-sm text-slate-500">
                    Proyeksi {{ $expenseForecast['month_label'] }} dari riwayat Dana Usaha
                    (hari ke-{{ $expenseForecast['days_elapsed'] }} dari {{ $expenseForecast['days_in_month'] }}).
                </p>
            </div>
            <a href="{{ route('business-funds.index') }}" class="text-sm font-semibold text-brand-700 hover:underline">Catat pengeluaran →</a>
        </div>
        <div class="mb-4 grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
            <x-stat-card
                label="Sudah keluar bulan ini"
                :value="$format::rupiah($expenseForecast['month_to_date'])"
                color="rose"
            />
            <x-stat-card
                label="Rata-rata harian (30 hari)"
                :value="$format::rupiah($expenseForecast['avg_daily_30'])"
                color="amber"
            />
            <x-stat-card
                label="Perkiraan akhir bulan"
                :value="$format::rupiah($expenseForecast['projected_month'])"
                color="brand"
            />
            <x-stat-card
                label="Sisa perkiraan bulan ini"
                :value="$format::rupiah($expenseForecast['remaining_estimate'])"
                color="slate"
            />
        </div>
        <div class="card border-slate-200 bg-slate-50/60">
            <div class="flex flex-wrap items-start justify-between gap-3">
                <div>
                    <p class="text-sm font-medium text-slate-800">Perkiraan gaji karyawan aktif</p>
                    <p class="mt-1 text-sm text-slate-500">
                        Estimasi gaji pokok + harian. Setelah <strong>konfirmasi bayar</strong> di menu Gaji,
                        nominal otomatis mengurangi Dana Usaha (omzet bersih) dengan keterangan gaji.
                    </p>
                </div>
                <p class="text-xl font-bold text-slate-900">{{ $format::rupiah($expenseForecast['estimated_salary']) }}</p>
            </div>
        </div>
    </section>

    <section class="mb-10">
        <div class="mb-4">
            <h2 class="text-lg font-semibold text-slate-900">Data Usaha</h2>
            <p class="mt-1 text-sm text-slate-500">Ringkas jumlah menu dan bahan yang sudah dicatat.</p>
        </div>

        <div class="mb-4 grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
            <x-stat-card label="Menu Aktif" :value="$format::number($snapshot['menu_aktif'], 0)" color="brand" />
            <x-stat-card label="Bahan Baku" :value="$format::number($snapshot['bahan_baku'], 0)" color="green" />
            <x-stat-card label="Bahan Jadi" :value="$format::number($snapshot['bahan_jadi'], 0)" color="amber" />
            <x-stat-card label="Tanpa Harga / HPP" :value="$format::number($snapshot['menu_tanpa_harga'] + $snapshot['menu_tanpa_hpp'], 0)" color="rose" />
        </div>

        @if ($snapshot['menu_tanpa_harga'] > 0 || $snapshot['menu_tanpa_hpp'] > 0)
            <div class="card border-amber-200 bg-amber-50/40">
                <p class="text-sm font-medium text-slate-800">Perlu dilengkapi</p>
                <ul class="mt-2 space-y-1 text-sm text-slate-600">
                    @if ($snapshot['menu_tanpa_harga'] > 0)
                        <li>
                            {{ $format::number($snapshot['menu_tanpa_harga'], 0) }} menu belum punya harga jual —
                            <a href="{{ route('menu-pricing.index') }}" class="font-medium text-brand-700 hover:underline">atur di Harga Jual</a>
                        </li>
                    @endif
                    @if ($snapshot['menu_tanpa_hpp'] > 0)
                        <li>
                            {{ $format::number($snapshot['menu_tanpa_hpp'], 0) }} menu belum punya modal/HPP —
                            <a href="{{ route('products.index') }}" class="font-medium text-brand-700 hover:underline">lengkapi resep</a>
                        </li>
                    @endif
                </ul>
            </div>
        @endif
    </section>

    @if ($topMenus->isNotEmpty())
        <section class="mb-10">
            <x-table-card title="Menu Terlaris {{ $month['label'] }}">
                <table class="table-default">
                    <thead>
                        <tr>
                            <th>Menu</th>
                            <th>Terjual</th>
                            <th>Omzet</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($topMenus as $row)
                            <tr>
                                <td class="font-semibold text-slate-900">{{ $row['name'] }}</td>
                                <td>{{ $format::number($row['quantity'], 0) }}</td>
                                <td class="cell-money">{{ $format::rupiah($row['revenue']) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </x-table-card>
        </section>
    @endif

    @if ($summary['total_records'] > 0)
        <section class="mb-10">
            <div class="mb-4">
                <h2 class="text-lg font-semibold text-slate-900">Ringkasan Modal</h2>
                <p class="mt-1 text-sm text-slate-500">Akumulasi perhitungan HPP dari resep, produksi, dan penjualan.</p>
            </div>

            <div class="mb-6 grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                <x-stat-card label="Total Modal" :value="$format::rupiah($summary['total_cogs'])" color="brand" />
                <x-stat-card label="Bahan Baku" :value="$format::rupiah($summary['total_direct_material'])" color="green" />
                <x-stat-card label="Upah Kerja" :value="$format::rupiah($summary['total_direct_labor'])" color="amber" />
                <x-stat-card label="Biaya Lain" :value="$format::rupiah($summary['total_overhead'])" color="rose" />
            </div>

            @if (count($summary['by_product']) > 0)
                <x-table-card title="Modal per Menu">
                    <table class="table-default">
                        <thead>
                            <tr>
                                <th>Menu</th>
                                <th>Jumlah</th>
                                <th>Total Modal</th>
                                <th>Modal / Porsi</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($summary['by_product'] as $row)
                                <tr>
                                    <td class="font-semibold text-slate-900">{{ $row['name'] }}</td>
                                    <td>{{ $format::number($row['total_quantity'], 0) }}</td>
                                    <td class="cell-money">{{ $format::rupiah($row['total_cogs']) }}</td>
                                    <td class="cell-highlight">{{ $format::rupiah($row['average_unit_cogs']) }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </x-table-card>
            @endif
        </section>
    @endif

    <div class="card border-slate-200">
        <div class="flex flex-col gap-4 sm:flex-row sm:flex-wrap sm:items-center sm:justify-between">
            <div>
                <h3 class="font-semibold text-slate-800">Mulai dari Awal</h3>
                <p class="mt-1 text-sm text-slate-500">Hapus semua data dan ulang dari langkah 1.</p>
            </div>
            <a href="{{ route('reset-data.show') }}" class="btn-danger w-full sm:w-auto">Hapus Semua Data</a>
        </div>
    </div>
@endsection
