@forelse ($stockLogs as $log)
    <article class="material-history-item">
        <div class="material-history-item-top">
            <span class="badge {{ $log->actionBadgeClass() }}">{{ $log->actionLabel() }}</span>
            <time class="material-history-item-time" datetime="{{ $log->created_at?->toIso8601String() }}">
                {{ $log->created_at?->format('d/m/Y H:i') }}
            </time>
        </div>
        <p class="material-history-item-name">{{ $log->product_name }}</p>
        <p class="material-history-item-meta">
            @if ($log->quantity_before !== null || $log->quantity_after !== null)
                Stok
                {{ $log->quantity_before !== null ? $format::number($log->quantity_before) : '-' }}
                →
                {{ $log->quantity_after !== null ? $format::number($log->quantity_after) : '-' }}
                {{ $log->product_unit }}
                @if ($log->quantity_delta !== null && (float) $log->quantity_delta != 0)
                    <span class="{{ (float) $log->quantity_delta > 0 ? 'material-history-delta-up' : 'material-history-delta-down' }}">
                        ({{ (float) $log->quantity_delta > 0 ? '+' : '' }}{{ $format::number($log->quantity_delta) }})
                    </span>
                @endif
            @endif
        </p>
        @if ($log->note || $log->lot_number || $log->unit_cost !== null)
            <p class="material-history-item-note">
                @if ($log->note)
                    {{ $log->note }}
                @endif
                @if ($log->lot_number)
                    · Batch {{ $log->lot_number }}
                @endif
                @if ($log->unit_cost !== null)
                    · {{ $format::rupiah($log->unit_cost) }}/{{ $log->product_unit }}
                @endif
            </p>
        @endif
        @if ($log->user)
            <p class="material-history-item-user">oleh {{ $log->user->name }}</p>
        @endif
    </article>
@empty
    <div class="material-history-empty">
        <p>Belum ada riwayat untuk periode ini.</p>
        <p class="empty-hint">Pilih tanggal/bulan lain, atau lakukan update stok dulu.</p>
    </div>
@endforelse
