import fs from 'node:fs';
import path from 'node:path';
import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import { bunny, local } from 'laravel-vite-plugin/fonts';
import tailwindcss from '@tailwindcss/vite';

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

        return [...kept, css.replace(FACE, '').trim()].join('\n\n').trim();
    };

    return {
        name: 'hawalaty:prefer-woff2',

        apply: 'build',
        enforce: 'post',

        closeBundle() {
            const buildDir = path.resolve('public/build');
            const manifestPath = path.join(buildDir, 'fonts-manifest.json');

            if (!fs.existsSync(manifestPath)) {
                return;
            }

            const manifest = JSON.parse(fs.readFileSync(manifestPath, 'utf-8'));
            const style = manifest.style ?? {};

            if (style.file) {
                const stylePath = path.join(buildDir, style.file);
                fs.writeFileSync(stylePath, strip(fs.readFileSync(stylePath, 'utf-8')));
            }

            for (const [alias, css] of Object.entries(style.familyStyles ?? {})) {
                style.familyStyles[alias] = strip(css);
            }

            for (const family of Object.values(manifest.families ?? {})) {
                for (const variant of Object.values(family.variants ?? {})) {
                    const woff2 = variant.files.filter((f) => f.format === 'woff2');
                    variant.files = woff2.length > 0 ? woff2 : variant.files;
                }
            }

            fs.writeFileSync(manifestPath, JSON.stringify(manifest, null, 2));

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

            fonts: [

                bunny('Tajawal', {
                    weights: [400, 500, 700, 800],
                    subsets: ['latin', 'arabic'],
                    preload: [{ weight: 400 }, { weight: 700 }],
                }),

                bunny('Cairo', {
                    weights: [400, 500, 600, 700, 800],
                    subsets: ['latin', 'arabic'],
                    preload: false,
                }),

                bunny('Inter', {
                    weights: [400, 500, 600, 700],
                    preload: false,
                }),

                local('Material Symbols Outlined', {
                    alias: 'material-symbols',
                    src: 'resources/fonts/material-symbols-outlined.woff2',
                    display: 'block',

                    optimizedFallbacks: false,

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
