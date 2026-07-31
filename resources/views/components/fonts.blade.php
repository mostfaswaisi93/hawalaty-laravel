{{--
    Every font the page needs, plus the guard that keeps the icon font from
    leaking its own ligature names.

    One component because this used to be copy-pasted into five layouts, and the
    copies had already drifted - the print receipt asked for a different family
    set, and a change to the icon script had to be made in four places or in
    none.

    @fonts writes <link rel="preload"> tags for the hot faces and INLINES the
    @font-face block. Inlining matters: the old markup was a <link> to
    fonts.googleapis.com, so the browser had to resolve a second origin, fetch a
    stylesheet, and only then discover the font URLs - three serial round trips
    before a single glyph could be drawn. Now the faces are in the HTML and the
    files are on this origin, which is also the only way sw.js can cache them
    (its fetch handler drops anything cross-origin).
--}}

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
    {{-- Material Symbols draws each icon from a LIGATURE, so an element's text
         content IS the icon's name. With no font, the browser paints those
         identifiers as words - "dashboard", "notifications", "restaurant_menu"
         - all over the UI. app.css therefore hides them by default and this
         reveals them once the face is CONFIRMED available. --}}
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

    {{-- JS off: better a visible ligature than no icons at all. --}}
    <noscript><style>.material-symbols-outlined{visibility:visible}</style></noscript>
@endif
