@props([
    // Font aliases from vite.config.js, or null for all of them. Passing a
    // subset also narrows the inlined @font-face block, not just the preloads.
    'families' => null,

    // The print receipt draws no icons and must not pull 447 kB to a thermal
    // printer that will never render a glyph from it.
    'icons' => true,
])

@fonts($families)

@if ($icons)
    <script>
        (function () {
            var html = document.documentElement;
            var face = '24px "Material Symbols Outlined"';
            var key = 'iconsReady';
            var reveal = function () { html.classList.add('icons-ready'); };
            var hide = function () { html.classList.remove('icons-ready'); };
            var remember = function (ready) {
                try { ready ? sessionStorage.setItem(key, '1') : sessionStorage.removeItem(key); } catch (e) {}
            };

            if (!document.fonts || !document.fonts.load) { reveal(); return; }

            var seen = false;
            try { seen = sessionStorage.getItem(key) === '1'; } catch (e) {}

            // Loaded earlier this session -> reveal BEFORE the first paint, so
            // the icons do not flash in on every navigation.
            if (seen || document.fonts.check(face)) { reveal(); }

            // ...but VERIFY it, always. Clearing the browser cache drops the
            // font file while leaving sessionStorage untouched, so the flag
            // outlived the thing it was vouching for: the icons were revealed
            // against a face that had to be downloaded again, and once
            // `display: block` gave up waiting the whole UI filled with icon
            // names. That is the "everything breaks after I clear the cache,
            // then comes back" report. Re-hiding costs nothing on the happy
            // path - `check()` passes and the class is already set.
            document.fonts.load(face).then(function () {
                var ok = document.fonts.check(face);
                ok ? reveal() : hide();
                remember(ok);
            }, function () { hide(); remember(false); });
        })();
    </script>

    <noscript><style>.material-symbols-outlined{visibility:visible}</style></noscript>
@endif
