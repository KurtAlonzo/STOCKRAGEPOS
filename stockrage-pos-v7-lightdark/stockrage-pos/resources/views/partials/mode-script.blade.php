{{-- Runs before the page paints, so there is no flash of the wrong mode. Dark is the default. --}}
<script>
    (function () {
        var KEY = 'stockrage.mode';
        var mode = 'dark';
        try { mode = localStorage.getItem(KEY) === 'light' ? 'light' : 'dark'; } catch (e) {}
        document.documentElement.classList.toggle('dark', mode === 'dark');
        window.toggleMode = function () {
            var dark = !document.documentElement.classList.contains('dark');
            document.documentElement.classList.toggle('dark', dark);
            try { localStorage.setItem(KEY, dark ? 'dark' : 'light'); } catch (e) {}
            // Charts are drawn once with the colours of the moment, so redraw them.
            if (window.Chart && Object.keys(window.Chart.instances || {}).length) { window.location.reload(); }
        };
    })();
</script>
