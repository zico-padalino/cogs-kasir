@php
    $sources = $metric_sources ?? null;
@endphp

@if (is_array($sources))
    <div class="card mb-5 overflow-hidden p-0">
        <div class="border-b border-slate-100 px-4 py-3">
            <h2 class="text-sm font-semibold text-slate-900">Sumber data ringkasan</h2>
            <p class="mt-0.5 text-xs text-slate-500">Asal perhitungan omzet bersih · {{ $rangeLabel }}</p>
        </div>

        <div class="divide-y divide-slate-100">
            @php
                $sections = [
                    ['key' => 'omzet_kotor', 'title' => 'Omzet kotor', 'amount' => $omzet_kotor ?? 0, 'color' => 'text-slate-900', 'list' => false],
                    ['key' => 'diskon', 'title' => 'Total diskon', 'amount' => $diskon_total ?? 0, 'color' => 'text-amber-800', 'list' => false],
                    ['key' => 'lost_produk', 'title' => 'Total lost produk', 'amount' => $lost_total ?? 0, 'color' => 'text-rose-700', 'list' => true],
                    ['key' => 'gaji', 'title' => 'Pengeluaran gaji', 'amount' => $expense_gaji ?? 0, 'color' => 'text-violet-800', 'list' => true],
                    ['key' => 'gaji_manual', 'title' => 'Gaji manual (Dana Usaha)', 'amount' => $expense_gaji_manual ?? 0, 'color' => 'text-violet-800', 'list' => true],
                    ['key' => 'lainnya', 'title' => 'Pengeluaran lain-lain', 'amount' => $expense_lainnya ?? 0, 'color' => 'text-rose-700', 'list' => true],
                ];
            @endphp

            @foreach ($sections as $section)
                @php
                    $meta = $sources[$section['key']] ?? null;
                    $items = is_array($meta['items'] ?? null) ? $meta['items'] : [];
                    $byCategory = is_array($meta['by_category'] ?? null) ? $meta['by_category'] : [];
                @endphp
                <section class="px-4 py-3">
                    <div class="flex flex-wrap items-start justify-between gap-2">
                        <div class="min-w-0">
                            <p class="text-sm font-semibold text-slate-900">{{ $section['title'] }}</p>
                            @if (is_array($meta))
                                <p class="mt-0.5 text-xs font-medium text-brand-700">{{ $meta['module'] ?? '—' }}</p>
                                <p class="mt-0.5 text-xs text-slate-500">{{ $meta['detail'] ?? '' }}</p>
                            @endif
                        </div>
                        <p class="text-sm font-bold tabular-nums {{ $section['color'] }}">{{ $format::rupiah($section['amount']) }}</p>
                    </div>

                    @if ($section['list'] && $byCategory !== [])
                        <div class="mt-3 flex flex-wrap gap-2">
                            @foreach ($byCategory as $group)
                                <span class="inline-flex items-center rounded-full bg-slate-100 px-2.5 py-1 text-[11px] font-medium text-slate-700">
                                    {{ $group['label'] }} · {{ $format::rupiah($group['total']) }}
                                    <span class="ml-1 text-slate-400">({{ $group['count'] }})</span>
                                </span>
                            @endforeach
                        </div>
                    @endif

                    @if ($section['list'])
                        @if ($items === [])
                            <p class="mt-2 text-xs text-slate-400">Tidak ada data pada periode ini.</p>
                        @else
                            <div class="mt-3 max-h-56 overflow-y-auto rounded-lg border border-slate-100">
                                <div class="divide-y divide-slate-100">
                                    @foreach ($items as $item)
                                        <div class="flex items-start justify-between gap-3 px-3 py-2.5">
                                            <div class="min-w-0">
                                                <p class="truncate text-sm font-medium text-slate-800">{{ $item['label'] ?? '—' }}</p>
                                                @if (! empty($item['detail']))
                                                    <p class="mt-0.5 text-xs text-slate-500 break-words">{{ $item['detail'] }}</p>
                                                @endif
                                                @if (! empty($item['date']))
                                                    <p class="mt-0.5 text-[11px] text-slate-400">
                                                        {{ ($item['date'] instanceof \Carbon\Carbon ? $item['date'] : \Carbon\Carbon::parse($item['date']))->translatedFormat('d M Y') }}
                                                    </p>
                                                @endif
                                            </div>
                                            <p class="shrink-0 text-sm font-semibold tabular-nums text-slate-900">{{ $format::rupiah($item['amount'] ?? 0) }}</p>
                                        </div>
                                    @endforeach
                                </div>
                            </div>
                            <p class="mt-1.5 text-[11px] text-slate-400">{{ count($items) }} entri</p>
                        @endif
                    @endif
                </section>
            @endforeach

            <section class="bg-emerald-50/60 px-4 py-3">
                <div class="flex flex-wrap items-start justify-between gap-2">
                    <div>
                        <p class="text-sm font-semibold text-emerald-900">Omzet bersih</p>
                        <p class="mt-0.5 text-xs text-emerald-800/80">
                            Omzet kotor − diskon − lost produk − total pengeluaran
                        </p>
                    </div>
                    <p class="text-sm font-bold tabular-nums text-emerald-800">{{ $format::rupiah($omzet) }}</p>
                </div>
            </section>
        </div>
    </div>
@endif
