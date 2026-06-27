@extends('layouts.app')

@section('title', 'Buat Produksi')
@section('heading', 'Buat Order Produksi')
@section('subheading', 'Pilih barang jadi dan jumlah yang akan diproduksi')

@section('content')
    <div class="mx-auto max-w-2xl">
        <x-step-header number="5" title="Buat Produksi Baru"
            description="Bahan otomatis diambil dari resep (Langkah 3). Setelah dibuat, buka detail untuk Mulai & Selesaikan." />

        <div class="card">
            <form action="{{ route('production-orders.store') }}" method="POST" class="space-y-5">
                @csrf

                <div>
                    <label class="form-label">Produk yang Diproduksi</label>
                    <select name="product_id" class="form-input" required>
                        <option value="">Pilih barang jadi...</option>
                        @foreach ($products as $p)
                            <option value="{{ $p->id }}">{{ $p->name }} ({{ $p->sku }})</option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <label class="form-label">Jumlah produksi</label>
                    <input type="number" name="quantity_planned" class="form-input" min="1" step="1" value="100" required>
                </div>

                <details class="rounded-lg border border-slate-200 p-4">
                    <summary class="cursor-pointer text-sm font-medium">Tenaga kerja & mesin (opsional)</summary>
                    <div class="mt-4 space-y-4">
                        <div>
                            <label class="form-label">Jam mesin</label>
                            <input type="number" name="machine_hours" class="form-input" min="0" step="0.1" value="6">
                        </div>
                        <div id="labor-rows" class="space-y-3">
                            <p class="text-xs text-slate-500">Tenaga kerja langsung:</p>
                        <div class="labor-row">
                            <input type="text" name="labors[0][description]" class="form-input labor-row-desc" value="Operator" placeholder="Nama pekerjaan">
                            <input type="number" name="labors[0][labor_hours]" class="form-input labor-row-hours" step="0.1" value="8" placeholder="Jam">
                            <div class="labor-row-rate">
                                <x-rupiah-input name="labors[0][hourly_rate]" :value="20000" placeholder="20.000" class="text-sm" />
                            </div>
                        </div>
                        </div>
                        <div>
                            <label class="form-label">Catatan</label>
                            <textarea name="notes" class="form-input" rows="2"></textarea>
                        </div>
                    </div>
                </details>

                <div class="form-actions">
                    <button type="submit" class="btn-primary">Buat Order</button>
                    <a href="{{ route('production-orders.index') }}" class="btn-secondary">Batal</a>
                </div>
            </form>
        </div>
    </div>
@endsection
