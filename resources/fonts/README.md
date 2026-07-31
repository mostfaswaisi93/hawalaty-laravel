# resources/fonts

## material-symbols-outlined.woff2

The icon font, vendored instead of linked. Registered in `vite.config.js` with
the `local()` provider and served from `/build` like any other asset, so `sw.js`
caches it and the icons survive an offline load.

It is **not** the full Material Symbols release. Google's CSS2 endpoint returns
an instanced font, so pinning the axes the app never varies cuts it from 3.9 MB
to 447 KB:

| request                                | size    |
| -------------------------------------- | ------- |
| `opsz,wght,FILL,GRAD@20..48,100..700,0..1,-50..200` | 3.9 MB |
| `opsz,wght,FILL,GRAD@24,400,0..1,0`                 | 447 KB |

The pinned values are exactly what `.material-symbols-outlined` in
`resources/css/app.css` hard-codes in `font-variation-settings`
(`'wght' 400, 'GRAD' 0, 'opsz' 24`). `FILL` stays variable because
`[data-weight="fill"]` toggles it to 1 on the active nav item and the order
confirmation mark.

Nothing is subset by icon NAME on purpose: catalogue icons come from a tenant's
saved value (`App\Services\Tenant\CatalogIcons`) and several views build the
ligature in PHP, so a name-subset font would silently render an unlisted icon as
its own text.

### Regenerating

Ask the CSS2 endpoint with a browser User-Agent (it serves TTF to anything it
does not recognise) and download the single URL it returns:

```bash
UA='Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36'
URL=$(curl -s -A "$UA" 'https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:opsz,wght,FILL,GRAD@24,400,0..1,0&display=block' | grep -o 'https://[^)]*\.woff2')
curl -sL -o resources/fonts/material-symbols-outlined.woff2 "$URL"
```

Then `npm run build`. Only do this to pick up new icons - the axis tuple should
stay as it is unless `font-variation-settings` in `app.css` changes too.
