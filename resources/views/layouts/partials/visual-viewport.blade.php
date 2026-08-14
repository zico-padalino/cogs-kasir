<script>
    (function () {
        var frame = 0;
        function syncVvh() {
            var vv = window.visualViewport;
            var h = vv ? Math.round(vv.height) : window.innerHeight;
            document.documentElement.style.setProperty('--vvh', Math.max(240, h) + 'px');
        }
        function scheduleSync() {
            if (frame) return;
            frame = window.requestAnimationFrame(function () {
                frame = 0;
                syncVvh();
            });
        }
        syncVvh();
        window.addEventListener('resize', scheduleSync);
        window.addEventListener('orientationchange', function () {
            window.setTimeout(syncVvh, 100);
            window.setTimeout(syncVvh, 350);
        });
        if (window.visualViewport) {
            window.visualViewport.addEventListener('resize', scheduleSync);
            window.visualViewport.addEventListener('scroll', scheduleSync);
        }
    })();
</script>
