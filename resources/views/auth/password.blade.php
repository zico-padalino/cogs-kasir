@extends($layout)

@section('title', 'Ubah Password')
@section('heading', 'Ubah Password')
@section('subheading', 'Ganti password akun Anda sendiri')

@section('content')
    <form action="{{ route('password.update') }}" method="POST" class="mx-auto max-w-lg space-y-6">
        @csrf
        @method('PUT')

        <div class="card space-y-5">
            <div>
                <p class="text-sm text-slate-500">
                    Login sebagai <span class="font-semibold text-slate-800">{{ auth()->user()->email }}</span>
                </p>
            </div>

            <div>
                <label class="form-label" for="current_password">Password saat ini</label>
                <div class="password-field">
                    <input
                        type="password"
                        name="current_password"
                        id="current_password"
                        class="form-input password-field-input @error('current_password') border-red-400 @enderror"
                        required
                        autocomplete="current-password"
                    >
                    <button type="button" class="password-field-toggle" data-password-toggle aria-label="Tampilkan password">
                        <span class="password-field-icon" data-password-icon-show>
                            <svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M2.25 12s3.75-6.75 9.75-6.75S21.75 12 21.75 12s-3.75 6.75-9.75 6.75S2.25 12 2.25 12z"/><circle cx="12" cy="12" r="3"/></svg>
                        </span>
                        <span class="password-field-icon hidden" data-password-icon-hide>
                            <svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M3 3l18 18M9.88 9.88A3 3 0 0114.12 14.12M6.1 6.1C4.05 7.57 2.55 9.7 2.25 12c0 0 3.75 6.75 9.75 6.75 1.7 0 3.24-.4 4.55-1.05M17.94 17.94c1.84-1.37 3.2-3.3 3.56-5.94 0 0-3.75-6.75-9.75-6.75-1.05 0-2.04.15-2.95.42"/></svg>
                        </span>
                    </button>
                </div>
                @error('current_password')
                    <p class="mt-1.5 text-xs text-red-600">{{ $message }}</p>
                @enderror
            </div>

            <div>
                <label class="form-label" for="password">Password baru</label>
                <div class="password-field">
                    <input
                        type="password"
                        name="password"
                        id="password"
                        class="form-input password-field-input @error('password') border-red-400 @enderror"
                        required
                        autocomplete="new-password"
                        minlength="8"
                        data-password-strength="#password-strength"
                    >
                    <button type="button" class="password-field-toggle" data-password-toggle aria-label="Tampilkan password">
                        <span class="password-field-icon" data-password-icon-show>
                            <svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M2.25 12s3.75-6.75 9.75-6.75S21.75 12 21.75 12s-3.75 6.75-9.75 6.75S2.25 12 2.25 12z"/><circle cx="12" cy="12" r="3"/></svg>
                        </span>
                        <span class="password-field-icon hidden" data-password-icon-hide>
                            <svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M3 3l18 18M9.88 9.88A3 3 0 0114.12 14.12M6.1 6.1C4.05 7.57 2.55 9.7 2.25 12c0 0 3.75 6.75 9.75 6.75 1.7 0 3.24-.4 4.55-1.05M17.94 17.94c1.84-1.37 3.2-3.3 3.56-5.94 0 0-3.75-6.75-9.75-6.75-1.05 0-2.04.15-2.95.42"/></svg>
                        </span>
                    </button>
                </div>

                <div id="password-strength" class="password-strength" aria-live="polite">
                    <div class="password-strength-head">
                        <span class="password-strength-caption">Kekuatan password</span>
                        <span class="password-strength-label" data-strength-label>Masukkan password baru</span>
                    </div>
                    <div class="password-strength-bars" aria-hidden="true">
                        <span data-strength-bar></span>
                        <span data-strength-bar></span>
                        <span data-strength-bar></span>
                        <span data-strength-bar></span>
                        <span data-strength-bar></span>
                    </div>
                    <ul class="password-strength-tips">
                        <li data-strength-tip="length">Minimal 8 karakter</li>
                        <li data-strength-tip="mixed">Huruf besar &amp; kecil</li>
                        <li data-strength-tip="number">Angka</li>
                        <li data-strength-tip="symbol">Simbol (!@#...)</li>
                    </ul>
                </div>

                @error('password')
                    <p class="mt-1.5 text-xs text-red-600">{{ $message }}</p>
                @enderror
            </div>

            <div>
                <label class="form-label" for="password_confirmation">Ulangi password baru</label>
                <div class="password-field">
                    <input
                        type="password"
                        name="password_confirmation"
                        id="password_confirmation"
                        class="form-input password-field-input"
                        required
                        autocomplete="new-password"
                        minlength="8"
                    >
                    <button type="button" class="password-field-toggle" data-password-toggle aria-label="Tampilkan password">
                        <span class="password-field-icon" data-password-icon-show>
                            <svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M2.25 12s3.75-6.75 9.75-6.75S21.75 12 21.75 12s-3.75 6.75-9.75 6.75S2.25 12 2.25 12z"/><circle cx="12" cy="12" r="3"/></svg>
                        </span>
                        <span class="password-field-icon hidden" data-password-icon-hide>
                            <svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M3 3l18 18M9.88 9.88A3 3 0 0114.12 14.12M6.1 6.1C4.05 7.57 2.55 9.7 2.25 12c0 0 3.75 6.75 9.75 6.75 1.7 0 3.24-.4 4.55-1.05M17.94 17.94c1.84-1.37 3.2-3.3 3.56-5.94 0 0-3.75-6.75-9.75-6.75-1.05 0-2.04.15-2.95.42"/></svg>
                        </span>
                    </button>
                </div>
            </div>
        </div>

        <div class="form-actions">
            <button type="submit" class="btn-primary w-full sm:w-auto">Simpan password</button>
            <a href="{{ url()->previous() !== url()->current() ? url()->previous() : auth()->user()->homeUrl() }}" class="btn-secondary w-full sm:w-auto">Batal</a>
        </div>
    </form>
@endsection
