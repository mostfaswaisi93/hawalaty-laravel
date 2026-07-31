import fs from 'node:fs';
import path from 'node:path';
import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import { bunny, local } from 'laravel-vite-plugin/fonts';
import tailwindcss from '@tailwindcss/vite';

/**
 * Drops the WOFF copy of any face that also ships as WOFF2.
 *
 * laravel-vite-plugin turns each source FILE into its own @font-face, so a
 * provider that answers with `src: url(a.woff2) format("woff2"), url(a.woff)
 * format("woff")` comes out as two rules with the same family, weight, style
 * and unicode-range. The later one wins the cascade - so every browser here
 * rendered from the legacy WOFF, and the WOFF2 that `@fonts` had just
 * preloaded was fetched at high priority and thrown away.
 *
 * Both copies have to go: the rule from the stylesheet and the file from the
 * build. WOFF2 has been supported since 2016 and this app already requires far
 * newer (`color-mix()`, `:has()`, service workers), so nothing is left behind.
 */
function preferWoff2() {
    const FACE = /@font-face\s*\{[^}]*\}\s*/g;
    const keyOf = (face) =>
        ['font-family', 'font-weight', 'font-style', 'unicode-range']
            .map((prop) => (face.match(new RegExp(`${prop}:\\s*([^;]+);`))?.[1] ?? '').trim())
            .join('|');

    const strip = (css) => {
        const faces = (css.match(FACE) ?? []).map((f) => f.trim());
        const woff2Keys = new Set(faces.filter((f) => f.includes('format("woff2")')).map(keyOf));
        const kept = faces.filter((f) => !(f.includes('format("woff")') && woff2Keys.has(keyOf(f))));

        // Whatever else the fragment holds - the `.font-{alias}` helper class,
        // the fontaine fallback faces - is left exactly as it was.
        return [...kept, css.replace(FACE, '').trim()].join('\n\n').trim();
    };

    return {
        name: 'hawalaty:prefer-woff2',
        // Build only. `vite dev` serves fonts from the plugin's own middleware
        // and never writes public/build, so running there would only rewrite a
        // stale manifest left behind by an earlier build.
        apply: 'build',
        enforce: 'post',
        // closeBundle, not writeBundle: the fonts manifest and its stylesheet
        // are emitted by the Laravel plugin, and this has to read what actually
        // landed on disk.
        closeBundle() {
            const buildDir = path.resolve('public/build');
            const manifestPath = path.join(buildDir, 'fonts-manifest.json');

            if (!fs.existsSync(manifestPath)) {
                return;
            }

            const manifest = JSON.parse(fs.readFileSync(manifestPath, 'utf-8'));
            const style = manifest.style ?? {};

            // `@fonts` reads the stylesheet when it is asked for every family
            // and the per-family fragments when it is given an alias list, so
            // both representations have to agree.
            if (style.file) {
                const stylePath = path.join(buildDir, style.file);
                fs.writeFileSync(stylePath, strip(fs.readFileSync(stylePath, 'utf-8')));
            }

            for (const [alias, css] of Object.entries(style.familyStyles ?? {})) {
                style.familyStyles[alias] = strip(css);
            }

            // `families` is only read for its alias keys at runtime, but it is
            // also the manifest's own record of what shipped - leaving WOFF
            // listed there would keep the files alive in the sweep below and
            // describe a build that no longer exists.
            for (const family of Object.values(manifest.families ?? {})) {
                for (const variant of Object.values(family.variants ?? {})) {
                    const woff2 = variant.files.filter((f) => f.format === 'woff2');
                    variant.files = woff2.length > 0 ? woff2 : variant.files;
                }
            }

            fs.writeFileSync(manifestPath, JSON.stringify(manifest, null, 2));

            // Anything no longer named by the manifest is dead weight in the
            // deploy - roughly a megabyte of WOFF nothing can now request.
            const referenced = fs.readFileSync(manifestPath, 'utf-8');
            for (const file of fs.readdirSync(path.join(buildDir, 'assets'))) {
                if (file.endsWith('.woff') && !referenced.includes(file)) {
                    fs.unlinkSync(path.join(buildDir, 'assets', file));
                }
            }
        },
    };
}

export default defineConfig({
    plugins: [
        laravel({
            input: ['resources/css/app.css', 'resources/js/app.js'],
            refresh: true,
            /*
             * Every font is downloaded AT BUILD TIME and served from /build.
             *
             * They used to be four <link>s to fonts.googleapis.com in each
             * layout, which is what made a cache clear look broken: nothing was
             * on this origin, so the first paint after the clear had no Arabic
             * face and no icon face. Text rendered in the system fallback and
             * reflowed when Tajawal landed, and every Material Symbols span
             * showed its own ligature name ("dashboard", "notifications") until
             * the icon font arrived.
             *
             * Self-hosting also fixes the offline story. sw.js only caches
             * same-origin /build/ URLs (see its fetch handler), so as long as
             * the fonts came from Google they were the one part of this PWA
             * that could never work offline - on a shop tablet that drops
             * connection, the whole UI degraded to ligature names.
             */
            fonts: [
                /*
                 * The UI font. `--app-font` in app.css, so preload the two
                 * weights the chrome is actually built from: 400 for body copy
                 * and 700 for every `font-bold` label, heading and nav item.
                 * 500/800 are used sparsely enough to fetch on demand.
                 */
                bunny('Tajawal', {
                    weights: [400, 500, 700, 800],
                    subsets: ['latin', 'arabic'],
                    preload: [{ weight: 400 }, { weight: 700 }],
                }),

                /*
                 * Reachable only as Tajawal's fallback inside `--app-font`, so
                 * it is declared but never preloaded - the browser fetches a
                 * weight only if it actually has to draw with it.
                 */
                bunny('Cairo', {
                    weights: [400, 500, 600, 700, 800],
                    subsets: ['latin', 'arabic'],
                    preload: false,
                }),

                /*
                 * Numerals and the small uppercase labels (`--font-numeral-lg`,
                 * `--font-label-caps`).
                 *
                 * Not preloaded even though money figures sit above the fold:
                 * `subsets` is ignored by the CSS2 endpoints, so Inter arrives
                 * as SEVEN unicode-range faces per weight and a preload would
                 * pull the Cyrillic, Greek and Vietnamese cuts of a UI that
                 * only ever renders Arabic and Latin. The latin cut is ~35 kB
                 * off this origin and the `swap` fallback is Tajawal, which
                 * already draws Western digits.
                 */
                bunny('Inter', {
                    weights: [400, 500, 600, 700],
                    preload: false,
                }),

                /*
                 * Vendored rather than fetched by family name, because the
                 * providers can only ask a CSS2 endpoint for `wght` tuples and
                 * this font is picked by its OTHER axes.
                 *
                 * The file is the css2 response for
                 * `opsz,wght,FILL,GRAD@24,400,0..1,0` - i.e. every axis pinned
                 * to the value app.css already hard-codes in
                 * `font-variation-settings`, leaving only FILL variable so
                 * `[data-weight="fill"]` still fills the active nav icon.
                 *
                 * That is 447 KB. The URL the layouts used to link
                 * (`20..48,100..700,0..1,-50..200`) ships every axis fully
                 * variable and weighs 3.9 MB - for a font that is render
                 * blocking by design (`display: block`) and whose fallback is
                 * the icon's own name. See resources/fonts/README.md before
                 * replacing it.
                 */
                local('Material Symbols Outlined', {
                    alias: 'material-symbols',
                    src: 'resources/fonts/material-symbols-outlined.woff2',
                    display: 'block',
                    // fontaine's metric-matched fallback is meaningless for an
                    // icon font - there is no system face to match against.
                    optimizedFallbacks: false,
                    /*
                     * NOT preloaded, even though icons are above the fold on
                     * every screen.
                     *
                     * At 447 kB it is by far the largest thing this app fetches,
                     * and `as="font"` is a HIGH priority hint - preloading it
                     * puts it in the same starting gate as the ~10 kB Tajawal
                     * cuts the first paint is actually waiting on, which is a
                     * direct hit to LCP on a throttled connection. Nothing is
                     * lost by demoting it: app.css hides every icon until the
                     * face is confirmed anyway, and the reserved boxes mean the
                     * late arrival shifts no layout.
                     */
                    preload: false,
                }),
            ],
        }),
        tailwindcss(),
        preferWoff2(),
    ],
    server: {
        watch: {
            ignored: ['**/storage/framework/views/**'],
        },
    },
});
