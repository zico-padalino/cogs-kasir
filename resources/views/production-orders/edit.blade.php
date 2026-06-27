@extends('layouts.app')

@section('title', 'Edit Produksi')
@section('heading', 'Edit Order Produksi')
@section('subheading', $order->order_number)

@section('content')
    <div class="mx-auto max-w-2xl">
        <x-step-header number="5" title="Edit Produksi" description="Hanya order berstatus draft yang bisa diedit." />

        <div class="card">
            <form action="{{ route('production-orders.update', $order) }}" method="POST" class="space-y-5">
                @csrf @method('PUT')

                <div>
                    <label class="form-label">Produk</label>
                    <select name="product_id" class="form-input" required>
                        @foreach ($products as $p)
                            <option value="{{ $p->id }}" @selected(old('product_id', $order->product_id) == $p->id)>{{ $p->name }}</option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <label class="form-label">Jumlah produksi</label>
                    <input type="number" name="quantity_planned" class="form-input" min="1" value="{{ old('quantity_planned', $order->quantity_planned) }}" required>
                </div>

                <div>
                    <label class="form-label">Jam mesin</label>
                    <input type="number" name="machine_hours" class="form-input" min="0" step="0.1" value="{{ old('machine_hours', $order->machine_hours) }}">
                </div>

                <div>
                    <label class="form-label">Catatan</label>
                    <textarea name="notes" class="form-input" rows="2">{{ old('notes', $order->notes) }}</textarea>
                </div>

                <div class="space-y-3">
                    <p class="text-sm font-medium">Tenaga Kerja</p>
                    @foreach ($order->labors as $i => $labor)
                        <div class="labor-row">
                            <input type="text" name="labors[{{ $i }}][description]" class="form-input labor-row-desc" value="{{ $labor->description }}">
                            <input type="number" name="labors[{{ $i }}][labor_hours]" class="form-input labor-row-hours" step="0.1" value="{{ $labor->labor_hours }}">
                            <div class="labor-row-rate">
                                <x-rupiah-input name="labors[{{ $i }}][hourly_rate]" :value="$labor->hourly_rate" class="text-sm" />
                            </div>
                        </div>
                    @endforeach
                    @if ($order->labors->isEmpty())
                        <div class="labor-row">
                            <input type="text" name="labors[0][description]" class="form-input labor-row-desc" placeholder="Operator">
                            <input type="number" name="labors[0][labor_hours]" class="form-input labor-row-hours" step="0.1" value="8">
                            <div class="labor-row-rate">
                                <x-rupiah-input name="labors[0][hourly_rate]" :value="20000" class="text-sm" />
                            </div>
                        </div>
                    @endif
                </div>

                <div class="form-actions">
                    <button type="submit" class="btn-primary">Simpan Perubahan</button>
                    <a href="{{ route('production-orders.show', $order) }}" class="btn-secondary">Batal</a>
                </div>
            </form>
        </div>
    </div>
@endsection
