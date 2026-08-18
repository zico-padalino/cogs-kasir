<script>
    (function () {
        var frame = 0;
        var state = window.__cogsVvh || { lastGood: 0, pickerUntil: 0 };
        window.__cogsVvh = state;

        function markPicker() {
            state.pickerUntil = Date.now() + 8000;
        }

        function endPicker() {
            state.pickerUntil = 0;
        }

        function syncVvh() {
            if (document.visibilityState === 'hidden') {
                return;
            }

            var vv = window.visualViewport;
            var inner = window.innerHeight || 0;
            var h = vv ? Math.round(vv.height) : inner;

            // Native gallery/camera overlays often collapse visualViewport.
            // Keep the last stable layout height so the page does not squash.
            if (Date.now() < state.pickerUntil && state.lastGood > 0 && h < state.lastGood * 0.75) {
                return;
            }

            state.lastGood = Math.max(240, h);
            document.documentElement.style.setProperty('--vvh', state.lastGood + 'px');
        }

        function scheduleSync() {
            if (frame) {
                return;
            }
            frame = window.requestAnimationFrame(function () {
                frame = 0;
                syncVvh();
            });
        }

        function restoreSoon() {
            endPicker();
            window.setTimeout(syncVvh, 50);
            window.setTimeout(syncVvh, 400);
        }

        function isFileControl(el) {
            if (! el) {
                return false;
            }
            if (el.matches && el.matches('input[type="file"]')) {
                return true;
            }
            var label = el.closest && el.closest('label');
            return !!(label && label.querySelector('input[type="file"]'));
        }

        document.addEventListener('pointerdown', function (event) {
            if (isFileControl(event.target)) {
                markPicker();
            }
        }, true);

        document.addEventListener('change', function (event) {
            if (event.target && event.target.matches && event.target.matches('input[type="file"]')) {
                restoreSoon();
            }
        }, true);

        document.addEventListener('cancel', function (event) {
            if (event.target && event.target.matches && event.target.matches('input[type="file"]')) {
                restoreSoon();
            }
        }, true);

        document.addEventListener('focusin', function (event) {
            var el = event.target;
            if (el && el.matches && el.matches('input:not([type="file"]), textarea, select')) {
                restoreSoon();
            }
        }, true);

        window.addEventListener('focus', restoreSoon);
        document.addEventListener('visibilitychange', function () {
            if (document.visibilityState === 'visible') {
                restoreSoon();
            }
        });

        syncVvh();
        window.addEventListener('resize', scheduleSync);
        window.addEventListener('orientationchange', function () {
            endPicker();
            window.setTimeout(syncVvh, 100);
            window.setTimeout(syncVvh, 350);
        });
        if (window.visualViewport) {
            window.visualViewport.addEventListener('resize', scheduleSync);
            window.visualViewport.addEventListener('scroll', scheduleSync);
        }
    })();
</script>
