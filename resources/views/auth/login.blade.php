@extends('layouts.guest')

@section('title', 'Masuk')

@section('content')
    <div class="auth-shell">
        <div class="auth-brand">
            <div class="auth-brand-inner">
                <div class="auth-logo">C</div>
                <p class="auth-brand-text">Satu aplikasi untuk perhitungan biaya produk dan operasional kasir.</p>

                <div class="auth-module-preview">
                    <div class="auth-module-card" data-module-preview="cogs">
                        <span class="auth-module-icon">📊</span>
                        <div>
                            <p class="font-semibold">Modul COGS</p>
                            <p class="text-sm text-indigo-100/90">Overhead, produk, stok, produksi, dan hasil biaya.</p>
                        </div>
                    </div>
                    <div class="auth-module-card" data-module-preview="kasir">
                        <span class="auth-module-icon">🧾</span>
                        <div>
                            <p class="font-semibold">Modul Kasir</p>
                            <p class="text-sm text-indigo-100/90">Penjualan harian dan transaksi kasir.</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="auth-panel">
            <div class="auth-panel-inner">
                <div class="mb-6 flex items-center gap-3 lg:hidden">
                    <div class="flex h-11 w-11 items-center justify-center rounded-xl bg-brand-600 text-lg font-bold text-white">C</div>
                    <div>
                        <p class="font-bold text-slate-900">COGS Sederhana</p>
                        <p class="text-xs text-slate-500">Pilih modul lalu masuk</p>
                    </div>
                </div>

                <div class="mb-6">
                    <h2 class="text-lg font-bold text-slate-900 sm:text-2xl">Masuk ke sistem</h2>
                    <p class="mt-1 text-sm text-slate-500">Pilih modul, lalu masukkan email dan password Anda.</p>
                </div>

                @if ($errors->any())
                    <div class="auth-alert-error mb-6">
                        {{ $errors->first() }}
                    </div>
                @endif

                <form action="{{ route('login.store') }}" method="POST" class="space-y-5" id="login-form">
                    @csrf

                    <div>
                        <p class="form-label mb-2">Pilih modul</p>
                        <div class="auth-module-tabs" role="tablist">
                            @foreach ($modules as $module)
                                <button
                                    type="button"
                                    class="auth-module-tab {{ old('module', 'cogs') === $module->value ? 'is-active' : '' }}"
                                    data-module="{{ $module->value }}"
                                    role="tab"
                                    aria-selected="{{ old('module', 'cogs') === $module->value ? 'true' : 'false' }}"
                                >
                                    <span class="auth-module-tab-label">{{ $module->label() }}</span>
                                    <span class="auth-module-tab-desc">{{ $module->description() }}</span>
                                </button>
                            @endforeach
                        </div>
                        <input type="hidden" name="module" id="module-input" value="{{ old('module', 'cogs') }}">
                    </div>

                    <div>
                        <label class="form-label" for="email">Email</label>
                        <input
                            type="email"
                            name="email"
                            id="email"
                            class="form-input"
                            value="{{ old('email') }}"
                            placeholder="nama@perusahaan.com"
                            autocomplete="username"
                            required
                            autofocus
                        >
                    </div>

                    <div>
                        <label class="form-label" for="password">Password</label>
                        <div class="password-field">
                            <input
                                type="password"
                                name="password"
                                id="password"
                                class="form-input password-field-input"
                                placeholder="••••••••"
                                autocomplete="current-password"
                                required
                            >
                            <button
                                type="button"
                                class="password-field-toggle"
                                data-password-toggle
                                aria-label="Tampilkan password"
                                aria-pressed="false"
                                aria-controls="password"
                            >
                                <span class="password-field-icon password-field-icon-show" data-password-icon-show aria-hidden="true">
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                        <path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7-10-7-10-7Z" />
                                        <circle cx="12" cy="12" r="3" />
                                    </svg>
                                </span>
                                <span class="password-field-icon password-field-icon-hide hidden" data-password-icon-hide aria-hidden="true">
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                        <path d="M9.88 9.88a3 3 0 1 0 4.24 4.24" />
                                        <path d="M10.73 5.08A10.43 10.43 0 0 1 12 5c6.5 0 10 7 10 7a18.34 18.34 0 0 1-2.16 3.19" />
                                        <path d="M6.61 6.61A18.9 18.9 0 0 0 2 12s3.5 7 10 7a10.66 10.66 0 0 0 5.17-1.32" />
                                        <line x1="2" x2="22" y1="2" y2="22" />
                                    </svg>
                                </span>
                            </button>
                        </div>
                    </div>

                    <label class="flex items-center gap-2 text-sm text-slate-600">
                        <input type="checkbox" name="remember" value="1" class="rounded border-slate-300 text-brand-600 focus:ring-brand-500" @checked(old('remember'))>
                        Ingat saya di perangkat ini
                    </label>

                    <button type="submit" class="btn-primary w-full py-3 text-base" id="login-submit">
                        Masuk ke <span id="module-label">{{ old('module', 'cogs') === 'kasir' ? 'Kasir' : 'COGS' }}</span>
                    </button>
                </form>

                @if (config('app.debug'))
                    <div class="auth-demo-box mt-8">
                        <p class="font-semibold text-slate-700">Akun demo (development)</p>
                        <ul class="mt-2 space-y-1 text-xs text-slate-600">
                            <li><strong>COGS:</strong> cogs@local.test / password</li>
                            <li><strong>Kasir:</strong> kasir@local.test / password</li>
                        </ul>
                    </div>
                @endif
            </div>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const moduleInput = document.getElementById('module-input');
            const moduleLabel = document.getElementById('module-label');
            const tabs = document.querySelectorAll('.auth-module-tab');
            const previews = document.querySelectorAll('[data-module-preview]');

            const labels = { cogs: 'COGS', kasir: 'Kasir' };

            function setModule(module) {
                moduleInput.value = module;
                moduleLabel.textContent = labels[module] || module;

                tabs.forEach((tab) => {
                    const active = tab.dataset.module === module;
                    tab.classList.toggle('is-active', active);
                    tab.setAttribute('aria-selected', active ? 'true' : 'false');
                });

                previews.forEach((preview) => {
                    preview.classList.toggle('is-active', preview.dataset.modulePreview === module);
                });
            }

            tabs.forEach((tab) => {
                tab.addEventListener('click', () => setModule(tab.dataset.module));
            });

            setModule(moduleInput.value || 'cogs');

            const passwordInput = document.getElementById('password');
            const passwordToggle = document.querySelector('[data-password-toggle]');
            const iconShow = document.querySelector('[data-password-icon-show]');
            const iconHide = document.querySelector('[data-password-icon-hide]');

            passwordToggle?.addEventListener('click', () => {
                const isHidden = passwordInput.type === 'password';

                passwordInput.type = isHidden ? 'text' : 'password';
                passwordToggle.setAttribute('aria-pressed', isHidden ? 'true' : 'false');
                passwordToggle.setAttribute('aria-label', isHidden ? 'Sembunyikan password' : 'Tampilkan password');
                iconShow?.classList.toggle('hidden', isHidden);
                iconHide?.classList.toggle('hidden', ! isHidden);
            });
        });
    </script>
@endsection
