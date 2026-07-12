@php
    $historyUrl = $historyUrl ?? route('materials.history');
    $historyPeriod = $historyPeriod ?? 'day';
    $historyDate = $historyDate ?? now()->toDateString();
    $historyMonth = now()->format('Y-m');
@endphp

<div
    id="material-history-modal"
    class="material-history-modal"
    data-material-history-modal
    data-history-url="{{ $historyUrl }}"
    aria-hidden="true"
    hidden
    style="display: none;"
>
    <div class="material-history-modal-backdrop" data-material-history-close></div>
    <div
        class="material-history-modal-panel"
        role="dialog"
        aria-modal="true"
        aria-labelledby="material-history-title"
    >
        <div class="material-history-modal-head">
            <div class="min-w-0 flex-1">
                <h2 id="material-history-title" class="material-history-modal-title">Riwayat Stok Bahan</h2>
                <p class="material-history-modal-sub" data-history-label>Hari ini</p>
            </div>
            <button type="button" class="material-history-modal-close" data-material-history-close aria-label="Tutup">×</button>
        </div>

        <div class="material-history-filters">
            <div class="material-history-tabs" role="tablist">
                <button type="button" class="material-history-tab is-active" data-history-period="day">Per Hari</button>
                <button type="button" class="material-history-tab" data-history-period="month">Per Bulan</button>
            </div>

            <div class="material-history-date-row" data-history-day-wrap>
                <label class="material-history-date-label" for="history-day-input">Tanggal</label>
                <input
                    id="history-day-input"
                    type="date"
                    class="material-history-date-input"
                    value="{{ $historyDate }}"
                    data-history-day
                >
            </div>

            <div class="material-history-date-row hidden" data-history-month-wrap>
                <label class="material-history-date-label" for="history-month-input">Bulan</label>
                <input
                    id="history-month-input"
                    type="month"
                    class="material-history-date-input"
                    value="{{ $historyMonth }}"
                    data-history-month
                >
            </div>
        </div>

        <div class="material-history-modal-body" data-history-list>
            @include('materials.partials.history-items', [
                'stockLogs' => $stockLogs,
                'format' => $format,
            ])
        </div>
    </div>
</div>

<style>
    .material-history-modal {
        position: fixed !important;
        inset: 0 !important;
        z-index: 9999 !important;
        display: none;
        align-items: flex-end;
        justify-content: center;
        padding: 0;
    }
    .material-history-modal.is-open {
        display: flex !important;
    }
    @media (min-width: 640px) {
        .material-history-modal {
            align-items: center;
            padding: 1rem;
        }
    }
    .material-history-modal-backdrop {
        position: absolute;
        inset: 0;
        background: rgba(15, 23, 42, 0.55);
    }
    .material-history-modal-panel {
        position: relative;
        z-index: 1;
        display: flex;
        flex-direction: column;
        width: 100%;
        max-width: 32rem;
        max-height: 88dvh;
        overflow: hidden;
        border-radius: 1.5rem 1.5rem 0 0;
        border: 1px solid #e2e8f0;
        background: #fff;
        box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.25);
    }
    @media (min-width: 640px) {
        .material-history-modal-panel {
            border-radius: 1rem;
        }
    }
    .material-history-modal-head {
        display: flex;
        align-items: flex-start;
        gap: 0.75rem;
        padding: 1rem;
        border-bottom: 1px solid #f1f5f9;
        flex-shrink: 0;
    }
    .material-history-modal-title {
        margin: 0;
        font-size: 1.125rem;
        font-weight: 700;
        color: #0f172a;
    }
    .material-history-modal-sub {
        margin: 0.25rem 0 0;
        font-size: 0.75rem;
        color: #64748b;
    }
    .material-history-modal-close {
        display: flex;
        width: 2.5rem;
        height: 2.5rem;
        flex-shrink: 0;
        align-items: center;
        justify-content: center;
        border: 0;
        border-radius: 9999px;
        background: transparent;
        color: #94a3b8;
        font-size: 1.5rem;
        line-height: 1;
        cursor: pointer;
    }
    .material-history-filters {
        display: flex;
        flex-direction: column;
        gap: 0.75rem;
        padding: 0.75rem 1rem;
        border-bottom: 1px solid #f1f5f9;
        background: #f8fafc;
        flex-shrink: 0;
    }
    .material-history-tabs {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 0.5rem;
    }
    .material-history-tab {
        min-height: 2.25rem;
        border: 1px solid #e2e8f0;
        border-radius: 9999px;
        background: #fff;
        color: #475569;
        font-size: 0.8125rem;
        font-weight: 600;
        cursor: pointer;
    }
    .material-history-tab.is-active {
        border-color: #4f46e5;
        background: #4f46e5;
        color: #fff;
    }
    .material-history-date-row {
        display: flex;
        flex-direction: column;
        gap: 0.35rem;
    }
    .material-history-date-row.hidden {
        display: none !important;
    }
    .material-history-date-label {
        font-size: 0.75rem;
        font-weight: 600;
        color: #475569;
    }
    .material-history-date-input {
        width: 100%;
        min-height: 2.5rem;
        border: 1px solid #cbd5e1;
        border-radius: 0.75rem;
        padding: 0.5rem 0.75rem;
        font-size: 0.875rem;
        background: #fff;
    }
    .material-history-modal-body {
        min-height: 0;
        flex: 1;
        overflow-y: auto;
        padding: 1rem;
        display: flex;
        flex-direction: column;
        gap: 0.5rem;
    }
    .material-history-item {
        border: 1px solid #e2e8f0;
        border-radius: 0.75rem;
        background: #f8fafc;
        padding: 0.625rem 0.75rem;
    }
    .material-history-item-top {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 0.5rem;
    }
    .material-history-item-time {
        font-size: 0.6875rem;
        color: #64748b;
        flex-shrink: 0;
    }
    .material-history-item-name {
        margin: 0.375rem 0 0;
        font-size: 0.875rem;
        font-weight: 600;
        color: #0f172a;
    }
    .material-history-item-meta {
        margin: 0.125rem 0 0;
        font-size: 0.75rem;
        color: #475569;
    }
    .material-history-item-note,
    .material-history-item-user {
        margin: 0.25rem 0 0;
        font-size: 0.6875rem;
        color: #94a3b8;
    }
    .material-history-delta-up { color: #047857; }
    .material-history-delta-down { color: #be123c; }
    .material-history-loading,
    .material-history-empty {
        text-align: center;
        padding: 2rem 1rem;
        color: #64748b;
        font-size: 0.875rem;
    }
</style>

<script>
(() => {
    function escapeHtml(value) {
        return String(value ?? '')
            .replaceAll('&', '&amp;')
            .replaceAll('<', '&lt;')
            .replaceAll('>', '&gt;')
            .replaceAll('"', '&quot;')
            .replaceAll("'", '&#39;');
    }

    function renderItems(items) {
        if (!items.length) {
            return '<div class="material-history-empty"><p>Belum ada riwayat untuk periode ini.</p></div>';
        }

        return items.map((item) => {
            let meta = '';
            if (item.quantity_before !== null || item.quantity_after !== null) {
                meta = `Stok ${escapeHtml(item.quantity_before ?? '-')} → ${escapeHtml(item.quantity_after ?? '-')} ${escapeHtml(item.product_unit || '')}`;
                if (item.quantity_delta_label && item.quantity_delta) {
                    const cls = item.quantity_delta > 0 ? 'material-history-delta-up' : 'material-history-delta-down';
                    meta += ` <span class="${cls}">(${escapeHtml(item.quantity_delta_label)})</span>`;
                }
            }

            let note = '';
            const bits = [];
            if (item.note) bits.push(escapeHtml(item.note));
            if (item.lot_number) bits.push('Batch ' + escapeHtml(item.lot_number));
            if (item.unit_cost) bits.push(escapeHtml(item.unit_cost) + '/' + escapeHtml(item.product_unit || ''));
            if (bits.length) {
                note = `<p class="material-history-item-note">${bits.join(' · ')}</p>`;
            }

            const user = item.user_name
                ? `<p class="material-history-item-user">oleh ${escapeHtml(item.user_name)}</p>`
                : '';

            return `
                <article class="material-history-item">
                    <div class="material-history-item-top">
                        <span class="badge ${escapeHtml(item.action_badge)}">${escapeHtml(item.action_label)}</span>
                        <time class="material-history-item-time">${escapeHtml(item.created_at || '')}</time>
                    </div>
                    <p class="material-history-item-name">${escapeHtml(item.product_name)}</p>
                    ${meta ? `<p class="material-history-item-meta">${meta}</p>` : ''}
                    ${note}
                    ${user}
                </article>
            `;
        }).join('');
    }

    function bindMaterialHistory() {
        const modal = document.getElementById('material-history-modal');
        const openBtn = document.querySelector('[data-material-history-open]');
        if (!modal || !openBtn || openBtn.dataset.historyBound === '1') {
            return;
        }

        openBtn.dataset.historyBound = '1';

        if (modal.parentElement !== document.body) {
            document.body.appendChild(modal);
        }

        const listEl = modal.querySelector('[data-history-list]');
        const labelEl = modal.querySelector('[data-history-label]');
        const dayWrap = modal.querySelector('[data-history-day-wrap]');
        const monthWrap = modal.querySelector('[data-history-month-wrap]');
        const dayInput = modal.querySelector('[data-history-day]');
        const monthInput = modal.querySelector('[data-history-month]');
        const tabs = modal.querySelectorAll('[data-history-period]');
        const historyUrl = modal.dataset.historyUrl;

        let period = 'day';

        const syncPeriodUi = () => {
            tabs.forEach((tab) => {
                tab.classList.toggle('is-active', tab.dataset.historyPeriod === period);
            });
            dayWrap?.classList.toggle('hidden', period !== 'day');
            monthWrap?.classList.toggle('hidden', period !== 'month');
        };

        const loadHistory = async () => {
            if (!historyUrl || !listEl) {
                return;
            }

            const date = period === 'month'
                ? (monthInput?.value || '')
                : (dayInput?.value || '');

            listEl.innerHTML = '<div class="material-history-loading">Memuat riwayat...</div>';

            try {
                const url = new URL(historyUrl, window.location.origin);
                url.searchParams.set('period', period);
                url.searchParams.set('date', date);

                const response = await fetch(url.toString(), {
                    headers: {
                        'Accept': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                    credentials: 'same-origin',
                });

                if (!response.ok) {
                    throw new Error('Gagal memuat');
                }

                const data = await response.json();
                if (labelEl) {
                    labelEl.textContent = `${data.label} · ${data.count} aktivitas`;
                }
                listEl.innerHTML = renderItems(data.items || []);
            } catch (error) {
                listEl.innerHTML = '<div class="material-history-empty"><p>Gagal memuat riwayat. Coba lagi.</p></div>';
            }
        };

        const open = (event) => {
            event.preventDefault();
            event.stopPropagation();
            modal.hidden = false;
            modal.removeAttribute('hidden');
            modal.style.display = 'flex';
            modal.classList.add('is-open');
            modal.setAttribute('aria-hidden', 'false');
            document.body.style.overflow = 'hidden';
            syncPeriodUi();
            loadHistory();
        };

        const close = () => {
            modal.hidden = true;
            modal.setAttribute('hidden', 'hidden');
            modal.style.display = 'none';
            modal.classList.remove('is-open');
            modal.setAttribute('aria-hidden', 'true');
            document.body.style.overflow = '';
        };

        openBtn.addEventListener('click', open);
        modal.querySelectorAll('[data-material-history-close]').forEach((el) => {
            el.addEventListener('click', close);
        });
        document.addEventListener('keydown', (event) => {
            if (event.key === 'Escape' && modal.classList.contains('is-open')) {
                close();
            }
        });

        tabs.forEach((tab) => {
            tab.addEventListener('click', () => {
                period = tab.dataset.historyPeriod || 'day';
                syncPeriodUi();
                loadHistory();
            });
        });

        dayInput?.addEventListener('change', () => {
            if (period === 'day') loadHistory();
        });
        monthInput?.addEventListener('change', () => {
            if (period === 'month') loadHistory();
        });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', bindMaterialHistory);
    } else {
        bindMaterialHistory();
    }
})();
</script>
