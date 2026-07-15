/**
 * Sidebar navigation drawer (mobile) + collapse/hide (desktop).
 */
const SIDEBAR_STORAGE_KEY = 'pos-sidebar-collapsed';
const DESKTOP_BP = 768;

export function initMobileNav() {
    const sidebar = document.getElementById('mobile-sidebar');
    const overlay = document.getElementById('mobile-overlay');
    const toggles = document.querySelectorAll('[data-mobile-menu-toggle]');
    const collapseBtns = document.querySelectorAll('[data-sidebar-collapse]');
    const expandBtns = document.querySelectorAll('[data-sidebar-expand]');

    if (! sidebar || ! overlay) {
        return;
    }

    const isDesktop = () => window.innerWidth >= DESKTOP_BP;

    const readCollapsedPreference = () => {
        try {
            return localStorage.getItem(SIDEBAR_STORAGE_KEY) === '1';
        } catch (_) {
            return false;
        }
    };

    const writeCollapsedPreference = (collapsed) => {
        try {
            localStorage.setItem(SIDEBAR_STORAGE_KEY, collapsed ? '1' : '0');
        } catch (_) {
            // ignore
        }
    };

    const setExpandButtonsVisible = (visible) => {
        expandBtns.forEach((btn) => {
            btn.hidden = ! visible;
            btn.setAttribute('aria-hidden', visible ? 'false' : 'true');
        });
    };

    const openMobile = () => {
        sidebar.classList.remove('-translate-x-full');
        sidebar.classList.add('translate-x-0');
        overlay.classList.remove('pointer-events-none', 'opacity-0');
        overlay.classList.add('pointer-events-auto', 'opacity-100');
        document.body.classList.add('overflow-hidden', 'touch-none');
    };

    const closeMobile = () => {
        sidebar.classList.add('-translate-x-full');
        sidebar.classList.remove('translate-x-0');
        overlay.classList.add('pointer-events-none', 'opacity-0');
        overlay.classList.remove('pointer-events-auto', 'opacity-100');
        document.body.classList.remove('overflow-hidden', 'touch-none');
    };

    const setDesktopCollapsed = (collapsed) => {
        document.body.classList.toggle('is-sidebar-collapsed', collapsed);
        sidebar.classList.toggle('is-collapsed', collapsed);
        setExpandButtonsVisible(isDesktop() && collapsed);
        writeCollapsedPreference(collapsed);

        collapseBtns.forEach((btn) => {
            btn.setAttribute('aria-expanded', collapsed ? 'false' : 'true');
        });
    };

    const syncForViewport = () => {
        if (isDesktop()) {
            closeMobile();
            setDesktopCollapsed(readCollapsedPreference());
        } else {
            document.body.classList.remove('is-sidebar-collapsed');
            sidebar.classList.remove('is-collapsed');
            setExpandButtonsVisible(false);
            closeMobile();
        }
    };

    toggles.forEach((toggle) => {
        toggle.addEventListener('click', () => {
            if (isDesktop()) {
                setDesktopCollapsed(! document.body.classList.contains('is-sidebar-collapsed'));
                return;
            }

            if (sidebar.classList.contains('-translate-x-full')) {
                openMobile();
            } else {
                closeMobile();
            }
        });
    });

    collapseBtns.forEach((btn) => {
        btn.addEventListener('click', () => {
            if (isDesktop()) {
                setDesktopCollapsed(true);
            } else {
                closeMobile();
            }
        });
    });

    expandBtns.forEach((btn) => {
        btn.addEventListener('click', () => {
            if (isDesktop()) {
                setDesktopCollapsed(false);
            } else {
                openMobile();
            }
        });
    });

    overlay.addEventListener('click', closeMobile);

    sidebar.querySelectorAll('a').forEach((link) => {
        link.addEventListener('click', () => {
            if (! isDesktop()) {
                closeMobile();
            }
        });
    });

    window.addEventListener('resize', syncForViewport);

    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape') {
            if (isDesktop() && document.body.classList.contains('is-sidebar-collapsed')) {
                return;
            }
            closeMobile();
        }
    });

    syncForViewport();
}

export function initResponsiveTables() {
    document.querySelectorAll('.table-default').forEach((table) => {
        const headers = [...table.querySelectorAll('thead th')].map((th) => th.textContent.trim());

        table.querySelectorAll('tbody tr').forEach((row) => {
            row.querySelectorAll('td').forEach((cell, index) => {
                if (headers[index] && ! cell.classList.contains('col-actions')) {
                    cell.setAttribute('data-label', headers[index]);
                }
            });
        });

        table.classList.add('table-stacked');
    });
}

export function initPasswordToggles() {
    document.querySelectorAll('[data-password-toggle]').forEach((button) => {
        const field = button.closest('.password-field');
        const input = field?.querySelector('input');
        const showIcon = button.querySelector('[data-password-icon-show]');
        const hideIcon = button.querySelector('[data-password-icon-hide]');

        if (! input) {
            return;
        }

        button.addEventListener('click', () => {
            const revealing = input.type === 'password';
            input.type = revealing ? 'text' : 'password';
            button.setAttribute('aria-label', revealing ? 'Sembunyikan password' : 'Tampilkan password');
            showIcon?.classList.toggle('hidden', revealing);
            hideIcon?.classList.toggle('hidden', ! revealing);
        });
    });
}

document.addEventListener('DOMContentLoaded', () => {
    initMobileNav();
    initResponsiveTables();
    initPasswordToggles();
});
