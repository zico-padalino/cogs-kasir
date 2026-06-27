@extends('layouts.app')

@section('title', 'Hitung COGS')
@section('heading', 'Kalkulator COGS')
@section('subheading', 'Simulasi atau catat penjualan dengan perhitungan COGS otomatis')

@section('content')
    <div class="mx-auto max-w-2xl card">
        <form action="{{ route('cogs.process') }}" method="POST" class="space-y-5" id="cogs-form">
            @csrf

            <div>
                <label class="form-label">Produk</label>
                <select name="product_id" class="form-input" required>
                    <option value="">Pilih produk...</option>
                    @foreach ($products as $p)
                        <option value="{{ $p->id }}" @selected(old('product_id') == $p->id)>{{ $p->name }} ({{ $p->sku }})</option>
                    @endforeach
                </select>
            </div>

            <div>
                <label class="form-label">Jumlah</label>
                <input type="number" name="quantity" class="form-input" min="1" step="1" value="{{ old('quantity', 1) }}" required>
            </div>

            <div class="space-y-3 rounded-lg border border-slate-200 p-4">
                <label class="flex items-center gap-2">
                    <input type="checkbox" name="consume_inventory" value="1" class="rounded border-slate-300 text-brand-600" @checked(old('consume_inventory'))>
                    <span class="text-sm">Kurangi stok persediaan (simulasi tanpa centang)</span>
                </label>
                <label class="flex items-center gap-2">
                    <input type="checkbox" name="record_sale" value="1" id="record_sale" class="rounded border-slate-300 text-brand-600" onchange="toggleSaleFields()" @checked(old('record_sale'))>
                    <span class="text-sm font-medium">Catat sebagai penjualan</span>
                </label>
            </div>

            <div id="sale-fields" class="hidden space-y-4 rounded-lg bg-brand-50 p-4">
                <div>
                    <label class="form-label">No. Invoice</label>
                    <input type="text" name="invoice_number" class="form-input" value="{{ old('invoice_number', 'INV-'.now()->format('YmdHis')) }}">
                </div>
                <div>
                    <x-rupiah-input name="selling_price" label="Harga Jual per Unit" :value="old('selling_price', 25000)" placeholder="25.000" />
                </div>
            </div>

            <button type="submit" class="btn-primary w-full">Hitung COGS</button>
        </form>
    </div>

    <script>
        function toggleSaleFields() {
            const checked = document.getElementById('record_sale').checked;
            document.getElementById('sale-fields').classList.toggle('hidden', !checked);
        }
        toggleSaleFields();
    </script>
@endsection
