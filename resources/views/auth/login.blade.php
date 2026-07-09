@extends('layouts.guest')

@section('title', 'Masuk')

@section('content')
    <div class="auth-shell auth-shell-fintech">
        <div class="auth-brand">
            <div class="auth-brand-inner">
                <div class="auth-logo">P</div>
                <h1 class="auth-brand-title">{{ config('pos.shop_name', 'Point of Sale') }}</h1>
                <p class="auth-brand-text">Masuk ke modul Hitung Biaya atau Kasir. Akun admin diarahkan otomatis ke panel Admin.</p>
            </div>
        </div>

        <div class="auth-panel">
            <div class="auth-panel-inner auth-panel-fintech">
                <div class="mb-6">
                    <h2 class="text-2xl font-bold text-slate-900">Selamat datang</h2>
                    <p class="mt-1 text-sm text-slate-500">Pilih modul, lalu masuk dengan akun Anda.</p>
                </div>

                @if ($errors->any())
                    <div class="auth-alert-error mb-5">{{ $errors->first() }}</div>
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
                                    <span class="auth-module-tab-label">{{ $module->icon() }} {{ $module->label() }}</span>
                                    <span class="auth-module-tab-desc">{{ $module->description() }}</span>
                                </button>
                            @endforeach
                        </div>
                        <input type="hidden" name="module" id="module-input" value="{{ old('module', 'cogs') }}">
                    </div>

                    <div>
                        <label class="form-label" for="email">Email</label>
                        <input type="email" name="email" id="email" class="form-input" value="{{ old('email') }}" required autofocus>
                    </div>

                    <div>
                        <label class="form-label" for="password">Password</label>
                        <input type="password" name="password" id="password" class="form-input" required>
                    </div>

                    <label class="flex items-center gap-2 text-sm text-slate-600">
                        <input type="checkbox" name="remember" value="1" class="rounded border-slate-300 text-brand-600" @checked(old('remember'))>
                        Ingat saya
                    </label>

                    <button type="submit" class="btn-primary w-full py-3 text-base">
                        Masuk ke <span id="module-label">Hitung Biaya</span>
                    </button>
                </form>

                @if (config('app.debug'))
                    <div class="auth-demo-box mt-6">
                        <p class="font-semibold text-slate-700">Akun demo</p>
                        <ul class="mt-2 space-y-1 text-xs text-slate-600">
                            <li><strong>Admin:</strong> admin@local.test / password → panel Admin</li>
                            <li><strong>Hitung Biaya:</strong> cogs@local.test / password</li>
                            <li><strong>Kasir:</strong> kasir@local.test / password</li>
                        </ul>
                    </div>
                @endif
            </div>
        </div>
    </div>

    <script>
        (function () {
            var input = document.getElementById('module-input');
            var label = document.getElementById('module-label');
            var tabs = document.querySelectorAll('.auth-module-tab');
            var labels = { cogs: 'Hitung Biaya', kasir: 'Kasir' };

            function setModule(module) {
                input.value = module;
                label.textContent = labels[module] || module;
                tabs.forEach(function (tab) {
                    var active = tab.dataset.module === module;
                    tab.classList.toggle('is-active', active);
                    tab.setAttribute('aria-selected', active ? 'true' : 'false');
                });
            }

            tabs.forEach(function (tab) {
                tab.addEventListener('click', function () {
                    setModule(tab.dataset.module);
                });
            });

            setModule(input.value || 'cogs');
        })();
    </script>
@endsection
