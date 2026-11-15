{{-- Runs before the page paints, so there is no flash of the wrong mode. Dark is the default. --}}
<script>
    (function () {
        var KEY = 'stockrage.mode';
        var mode = 'dark';
        try { mode = localStorage.getItem(KEY) === 'light' ? 'light' : 'dark'; } catch (e) {}
        document.documentElement.classList.toggle('dark', mode === 'dark');
        // Sidebar: decided here, before the page paints, so it never jumps when you change pages.
        var SIDE = 'stockrage.sidebar';
        var side = 'open';
        try { side = localStorage.getItem(SIDE) === 'collapsed' ? 'collapsed' : 'open'; } catch (e) {}
        document.documentElement.setAttribute('data-sidebar', side);
        window.toggleSidebar = function () {
            side = document.documentElement.getAttribute('data-sidebar') === 'collapsed' ? 'open' : 'collapsed';
            document.documentElement.setAttribute('data-sidebar', side);
            try { localStorage.setItem(SIDE, side); } catch (e) {}
        };
        window.toggleMode = function () {
            var dark = !document.documentElement.classList.contains('dark');
            document.documentElement.classList.toggle('dark', dark);
            try { localStorage.setItem(KEY, dark ? 'dark' : 'light'); } catch (e) {}
            // Charts are drawn once with the colours of the moment, so redraw them.
            if (window.Chart && Object.keys(window.Chart.instances || {}).length) { window.location.reload(); }
        };
    })();
</script>
