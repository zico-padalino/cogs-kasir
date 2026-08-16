@extends('layouts.admin')

@section('title', 'Log Aktivitas')
@section('heading', 'Log Aktivitas')
@section('subheading', 'Login, transaksi, pesan meja, PIN kasir, absensi, dan perubahan akun')

@section('content')
    <div class="mb-4 grid grid-cols-2 gap-3 xl:grid-cols-4">
        <div class="rounded-xl border border-brand-100 bg-white px-4 py-3 shadow-sm">
            <p class="text-[11px] font-medium uppercase tracking-wide text-slate-500">Semua log</p>
            <p class="mt-1 text-xl font-bold text-espresso">{{ number_format($counts['all']) }}</p>
        </div>
        <div class="rounded-xl border border-brand-100 bg-white px-4 py-3 shadow-sm">
            <p class="text-[11px] font-medium uppercase tracking-wide text-slate-500">Login hari ini</p>
            <p class="mt-1 text-xl font-bold text-espresso">{{ number_format($counts['auth']) }}</p>
        </div>
        <div class="rounded-xl border border-brand-100 bg-white px-4 py-3 shadow-sm">
            <p class="text-[11px] font-medium uppercase tracking-wide text-slate-500">Transaksi hari ini</p>
            <p class="mt-1 text-xl font-bold text-espresso">{{ number_format($counts['transaksi']) }}</p>
        </div>
        <div class="rounded-xl border border-brand-100 bg-white px-4 py-3 shadow-sm">
            <p class="text-[11px] font-medium uppercase tracking-wide text-slate-500">Pesan meja hari ini</p>
            <p class="mt-1 text-xl font-bold text-espresso">{{ number_format($counts['pesan']) }}</p>
        </div>
    </div>

    <form method="GET" action="{{ route('admin.activity-logs.index') }}" class="card mb-4 grid gap-3 p-4 sm:grid-cols-2 lg:grid-cols-6">
        <div>
            <label class="form-label" for="log-q">Cari</label>
            <input id="log-q" type="search" name="q" value="{{ $filters['q'] }}" class="form-input" placeholder="Nama, email, IP, nomor pesanan">
        </div>
        <div>
            <label class="form-label" for="log-category">Kategori</label>
            <select id="log-category" name="category" class="form-input">
                <option value="">Semua</option>
                @foreach ($categories as $key => $label)
                    <option value="{{ $key }}" @selected($filters['category'] === $key)>{{ $label }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label class="form-label" for="log-action">Jenis</label>
            <select id="log-action" name="action" class="form-input">
                <option value="">Semua</option>
                @foreach ($actions as $key => $label)
                    <option value="{{ $key }}" @selected($filters['action'] === $key)>{{ $label }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label class="form-label" for="log-from">Dari tanggal</label>
            <input id="log-from" type="date" name="date_from" value="{{ $filters['date_from'] }}" class="form-input">
        </div>
        <div>
            <label class="form-label" for="log-to">Sampai</label>
            <input id="log-to" type="date" name="date_to" value="{{ $filters['date_to'] }}" class="form-input">
        </div>
        <div class="flex items-end gap-2">
            <button type="submit" class="btn-primary flex-1 justify-center">Filter</button>
            <a href="{{ route('admin.activity-logs.index') }}" class="btn-outline">Reset</a>
        </div>
    </form>

    <div class="space-y-3 md:hidden">
        @forelse ($logs as $log)
            <article class="card space-y-2 p-4">
                <div class="flex flex-wrap items-start justify-between gap-2">
                    <p class="text-sm font-semibold text-slate-900">{{ $log->description }}</p>
                    <span class="badge badge-blue">{{ $log->categoryLabel() }}</span>
                </div>
                <p class="text-xs text-slate-500">{{ $log->created_at?->format('d/m/Y H:i:s') }} · {{ $log->actionLabel() }}</p>
                <dl class="grid grid-cols-1 gap-1 text-xs text-slate-600">
                    <div><span class="text-slate-400">Nama</span> · {{ $log->actor_name ?: '—' }}</div>
                    <div><span class="text-slate-400">Email</span> · {{ $log->actor_email ?: '—' }}</div>
                    <div><span class="text-slate-400">IP</span> · <span class="font-mono">{{ $log->ip_address ?: '—' }}</span></div>
                    <div><span class="text-slate-400">Perangkat</span> · {{ $log->properties['device'] ?? '—' }}</div>
                    <div><span class="text-slate-400">Kanal</span> · {{ $log->channel ?: '—' }}</div>
                    @if (! empty($log->properties['order_number']))
                        <div><span class="text-slate-400">Pesanan</span> · {{ $log->properties['order_number'] }}</div>
                    @endif
                </dl>
                <details class="text-xs text-slate-500">
                    <summary class="cursor-pointer font-medium text-brand-700">Detail lengkap</summary>
                    <pre class="mt-2 overflow-x-auto rounded-lg bg-slate-50 p-3 text-[11px] leading-relaxed text-slate-700">{{ json_encode($log->only(['id','category','action','actor_name','actor_email','ip_address','user_agent','method','url','route_name','channel','session_id','properties']), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) }}</pre>
                </details>
            </article>
        @empty
            <div class="card px-4 py-10 text-center text-sm text-slate-500">Belum ada log aktivitas.</div>
        @endforelse
    </div>

    <div class="table-card hidden md:block">
        <div class="table-scroll">
            <table class="table-default">
                <thead>
                    <tr>
                        <th>Waktu</th>
                        <th>Kategori</th>
                        <th>Aktivitas</th>
                        <th>Nama</th>
                        <th>Email</th>
                        <th>IP</th>
                        <th>Perangkat</th>
                        <th>Kanal</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($logs as $log)
                        <tr>
                            <td class="whitespace-nowrap text-xs text-slate-500">{{ $log->created_at?->format('d/m/Y H:i:s') }}</td>
                            <td><span class="badge badge-blue">{{ $log->categoryLabel() }}</span></td>
                            <td>
                                <p class="text-sm font-medium text-slate-900">{{ $log->description }}</p>
                                <p class="text-[11px] text-slate-400">{{ $log->actionLabel() }}</p>
                            </td>
                            <td class="text-sm">{{ $log->actor_name ?: '—' }}</td>
                            <td class="text-xs text-slate-500">{{ $log->actor_email ?: '—' }}</td>
                            <td class="font-mono text-xs">{{ $log->ip_address ?: '—' }}</td>
                            <td class="text-xs">{{ $log->properties['device'] ?? '—' }}</td>
                            <td class="text-xs">{{ $log->channel ?: '—' }}</td>
                            <td class="col-actions">
                                <details class="text-left">
                                    <summary class="btn-outline btn-sm cursor-pointer list-none">Detail</summary>
                                    <pre class="mt-2 max-w-xl overflow-x-auto rounded-lg bg-slate-50 p-3 text-[11px] leading-relaxed text-slate-700">{{ json_encode($log->only(['id','category','action','actor_name','actor_email','ip_address','user_agent','method','url','route_name','channel','session_id','properties']), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) }}</pre>
                                </details>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="9" class="py-8 text-center text-sm text-slate-500">Belum ada log aktivitas.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    @if ($logs->hasPages())
        <div class="pagination-wrap mt-4">{{ $logs->links() }}</div>
    @endif
@endsection
