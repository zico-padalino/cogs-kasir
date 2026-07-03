const DISMISS_KEY = 'pwa_install_dismissed_until';
const DISMISS_DAYS = 7;

let deferredInstallPrompt = null;

function isStandaloneDisplay() {
    return window.matchMedia('(display-mode: standalone)').matches
        || window.navigator.standalone === true;
}

function isMobileViewport() {
    return window.matchMedia('(max-width: 1023px)').matches;
}

function isDismissed() {
    const until = Number(localStorage.getItem(DISMISS_KEY) || '0');

    return until > Date.now();
}

function dismissBanner() {
    const until = Date.now() + DISMISS_DAYS * 24 * 60 * 60 * 1000;
    localStorage.setItem(DISMISS_KEY, String(until));
    hideInstallBanners();
}

function hideInstallBanners() {
    document.querySelectorAll('[data-pwa-install], [data-pwa-install-ios]').forEach((element) => {
        element.classList.add('hidden');
        element.hidden = true;
    });
}

function showInstallBanner(element) {
    if (! element || isStandaloneDisplay() || isDismissed() || ! isMobileViewport()) {
        return;
    }

    element.classList.remove('hidden');
    element.hidden = false;
}

function registerServiceWorker() {
    if (! ('serviceWorker' in navigator)) {
        return;
    }

    window.addEventListener('load', () => {
        navigator.serviceWorker.register('/sw.js').catch(() => {
            // Service worker optional for install on some browsers.
        });
    });
}

function initPwaInstall() {
    if (isStandaloneDisplay()) {
        document.documentElement.classList.add('is-pwa-standalone');

        return;
    }

    registerServiceWorker();

    const androidBanner = document.querySelector('[data-pwa-install]');
    const iosBanner = document.querySelector('[data-pwa-install-ios]');

    window.addEventListener('beforeinstallprompt', (event) => {
        event.preventDefault();
        deferredInstallPrompt = event;
        showInstallBanner(androidBanner);
    });

    document.querySelector('[data-pwa-install-accept]')?.addEventListener('click', async () => {
        if (! deferredInstallPrompt) {
            return;
        }

        deferredInstallPrompt.prompt();
        await deferredInstallPrompt.userChoice;
        deferredInstallPrompt = null;
        dismissBanner();
    });

    document.querySelector('[data-pwa-install-dismiss]')?.addEventListener('click', dismissBanner);
    document.querySelector('[data-pwa-install-ios-dismiss]')?.addEventListener('click', dismissBanner);

    const isIos = /iphone|ipad|ipod/i.test(window.navigator.userAgent);
    const isAndroid = /android/i.test(window.navigator.userAgent);

    if (isIos && ! isAndroid && iosBanner && ! isDismissed() && isMobileViewport()) {
        window.setTimeout(() => showInstallBanner(iosBanner), 1200);
    }
}

document.addEventListener('DOMContentLoaded', initPwaInstall);

export { isStandaloneDisplay };
