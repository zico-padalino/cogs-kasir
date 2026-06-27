/**
 * Mobile navigation drawer & responsive table stacking.
 */
export function initMobileNav() {
    const sidebar = document.getElementById('mobile-sidebar');
    const overlay = document.getElementById('mobile-overlay');
    const toggles = document.querySelectorAll('[data-mobile-menu-toggle]');

    if (!sidebar || !overlay) {
        return;
    }

    const open = () => {
        sidebar.classList.remove('-translate-x-full');
        sidebar.classList.add('translate-x-0');
        overlay.classList.remove('pointer-events-none', 'opacity-0');
        overlay.classList.add('pointer-events-auto', 'opacity-100');
        document.body.classList.add('overflow-hidden', 'touch-none');
    };

    const close = () => {
        sidebar.classList.add('-translate-x-full');
        sidebar.classList.remove('translate-x-0');
        overlay.classList.add('pointer-events-none', 'opacity-0');
        overlay.classList.remove('pointer-events-auto', 'opacity-100');
        document.body.classList.remove('overflow-hidden', 'touch-none');
    };

    toggles.forEach((toggle) => {
        toggle.addEventListener('click', () => {
            if (sidebar.classList.contains('-translate-x-full')) {
                open();
            } else {
                close();
            }
        });
    });

    overlay.addEventListener('click', close);

    sidebar.querySelectorAll('a').forEach((link) => {
        link.addEventListener('click', close);
    });

    window.addEventListener('resize', () => {
        if (window.innerWidth >= 768) {
            close();
        }
    });

    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape') {
            close();
        }
    });
}

export function initResponsiveTables() {
    document.querySelectorAll('.table-default').forEach((table) => {
        const headers = [...table.querySelectorAll('thead th')].map((th) => th.textContent.trim());

        table.querySelectorAll('tbody tr').forEach((row) => {
            row.querySelectorAll('td').forEach((cell, index) => {
                if (headers[index] && !cell.classList.contains('col-actions')) {
                    cell.setAttribute('data-label', headers[index]);
                }
            });
        });

        table.classList.add('table-stacked');
    });
}

export function syncBottomNavHeight() {
    const nav = document.getElementById('bottom-nav');
    const spacer = document.getElementById('bottom-nav-spacer');

    if (!nav) {
        return;
    }

    const update = () => {
        if (window.innerWidth >= 768) {
            document.documentElement.style.removeProperty('--bottom-nav-height');

            if (spacer) {
                spacer.style.height = '';
            }

            return;
        }

        const height = `${nav.getBoundingClientRect().height}px`;
        document.documentElement.style.setProperty('--bottom-nav-height', height);

        if (spacer) {
            spacer.style.height = height;
        }
    };

    update();

    window.addEventListener('resize', update);
    window.addEventListener('orientationchange', () => {
        window.setTimeout(update, 100);
    });

    if (typeof ResizeObserver !== 'undefined') {
        new ResizeObserver(update).observe(nav);
    }

    if (document.fonts?.ready) {
        document.fonts.ready.then(update);
    }
}

document.addEventListener('DOMContentLoaded', () => {
    initMobileNav();
    initResponsiveTables();
    syncBottomNavHeight();
});

window.addEventListener('load', syncBottomNavHeight);
