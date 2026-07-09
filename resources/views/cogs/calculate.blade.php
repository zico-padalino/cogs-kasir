@extends('layouts.app')

@section('title', 'Hitung Manual')
@section('heading', 'Hitung Biaya')
@section('subheading', 'Simulasi atau catat penjualan')

@section('content')
    <div class="mx-auto max-w-lg">
        <div class="card p-4 sm:p-5">
            <form action="{{ route('cogs.process') }}" method="POST" class="space-y-4" id="cogs-form">
                @csrf

                <div class="grid gap-4 sm:grid-cols-2">
                    <div class="sm:col-span-2">
                        <label class="form-label">Produk</label>
                        <select name="product_id" class="form-input" required>
                            <option value="">Pilih produk...</option>
                            @foreach ($products as $p)
                                <option value="{{ $p->id }}" @selected(old('product_id') == $p->id)>{{ $p->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="form-label">Jumlah</label>
                        <input type="number" name="quantity" class="form-input" min="1" step="1" value="{{ old('quantity', 1) }}" required>
                    </div>
                </div>

                <div class="space-y-2 rounded-lg border border-slate-200 bg-slate-50/50 p-3">
                    <label class="flex cursor-pointer items-start gap-2.5">
                        <input type="checkbox" name="consume_inventory" value="1" class="mt-0.5 rounded border-slate-300 text-brand-600" @checked(old('consume_inventory'))>
                        <span class="text-sm text-slate-700">Kurangi stok gudang <span class="text-slate-500">(kosongkan = simulasi saja)</span></span>
                    </label>
                    <label class="flex cursor-pointer items-start gap-2.5">
                        <input type="checkbox" name="record_sale" value="1" id="record_sale" class="mt-0.5 rounded border-slate-300 text-brand-600" onchange="toggleSaleFields()" @checked(old('record_sale'))>
                        <span class="text-sm font-medium text-slate-800">Catat sebagai penjualan</span>
                    </label>
                </div>

                <div id="sale-fields" class="hidden space-y-3 rounded-lg border border-brand-100 bg-brand-50/60 p-3">
                    <div>
                        <label class="form-label">No. nota</label>
                        <input type="text" name="invoice_number" class="form-input" value="{{ old('invoice_number', 'INV-'.now()->format('YmdHis')) }}">
                    </div>
                    <div>
                        <x-rupiah-input name="selling_price" label="Harga jual per unit" :value="old('selling_price', 25000)" placeholder="25.000" />
                    </div>
                </div>

                <button type="submit" class="btn-primary w-full">Hitung</button>
            </form>
        </div>
    </div>

    <script>
        function toggleSaleFields() {
            const checked = document.getElementById('record_sale').checked;
            document.getElementById('sale-fields').classList.toggle('hidden', !checked);
        }
        toggleSaleFields();
    </script>
@endsection
