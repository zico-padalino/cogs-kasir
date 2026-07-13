@extends($layout)

@section('title', 'PIN Kasir')
@section('heading', 'PIN Kasir')
@section('subheading', 'PIN pribadi untuk membuka kasir dan menandai siapa yang bertugas')

@section('content')
    @if (! $canUseKasir)
        <div class="card max-w-lg text-sm text-slate-600">
            Akun ini tidak punya akses modul Kasir, jadi PIN kasir tidak diperlukan.
        </div>
    @else
        <form action="{{ route('pin.update') }}" method="POST" class="mx-auto max-w-lg space-y-6" autocomplete="off">
            @csrf
            @method('PUT')

            <div class="card space-y-5">
                <div class="rounded-2xl border border-brand-100 bg-brand-50/60 px-4 py-3 text-sm text-brand-900">
                    @if ($hasPin)
                        PIN sudah aktif. Isi form di bawah untuk mengganti PIN.
                    @else
                        Belum ada PIN. Buat PIN 4–6 digit agar Anda bisa membuka kasir.
                    @endif
                </div>

                <div>
                    <label class="form-label" for="current_password">Password akun</label>
                    <input type="password" name="current_password" id="current_password" class="form-input" required autocomplete="current-password">
                    @error('current_password')
                        <p class="mt-1.5 text-xs text-red-600">{{ $message }}</p>
                    @enderror
                </div>

                <div>
                    <label class="form-label" for="pin">PIN baru (4–6 digit)</label>
                    <input type="password" inputmode="numeric" pattern="[0-9]*" name="pin" id="pin" class="form-input text-center text-xl tracking-[0.35em] font-bold" required maxlength="6" minlength="4" autocomplete="new-password">
                    @error('pin')
                        <p class="mt-1.5 text-xs text-red-600">{{ $message }}</p>
                    @enderror
                </div>

                <div>
                    <label class="form-label" for="pin_confirmation">Ulangi PIN</label>
                    <input type="password" inputmode="numeric" pattern="[0-9]*" name="pin_confirmation" id="pin_confirmation" class="form-input text-center text-xl tracking-[0.35em] font-bold" required maxlength="6" minlength="4" autocomplete="new-password">
                </div>
            </div>

            <div class="form-actions">
                <button type="submit" class="btn-primary w-full sm:w-auto">Simpan PIN</button>
                <a href="{{ auth()->user()->homeUrl() }}" class="btn-secondary w-full sm:w-auto">Selesai</a>
            </div>
        </form>
    @endif
@endsection
