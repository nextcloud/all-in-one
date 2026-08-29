# BharatSuite brand assets

Generated, not hand-drawn. The mockups render the wordmark as live HTML text, so
there was no asset to extract — these are built from that text's exact spec.

## Wordmark spec, taken from the mockup markup

```
font: 600 17px/1 var(--font-heading); letter-spacing: -.04em
Bharat  →  var(--color-accent-3-700)  #1c4478
Suite   →  var(--color-text)          #16202b
/       →  var(--color-accent)        #cf6d14
```

## Files

| File | Use |
|---|---|
| `logo.svg` | Light grounds — login screen, header on the paper theme |
| `logo-dark.svg` | Dark grounds — `Suite` becomes paper, `Bharat` lifts to `#6f9ad6` |
| `favicon.svg` | Square tile, saffron slash on `#1c4478` |

The dark variant is not optional. `Suite` is ink `#16202b`; on a dark ground the word
disappears entirely, leaving "Bharat /".

## How they were made

Text outlined to paths with fontTools, so the files carry no font dependency and
render identically everywhere. Source face is Inter (SIL OFL) at weight 600,
substituting for Geist, which the Notifiled stylesheet specifies but which is not
open source. The favicon uses weight 900 — at 16px the 600-weight slash was too thin
to read.

To regenerate after a font or colour change, re-run the outlining step with the
palette above; the glyphs are laid out with the font's own advance widths plus
`-0.04em` tracking to match the mockup.

## Wiring

These are **not** uploaded through Nextcloud's theming admin UI. `occ theming:config`
rejects the image keys, and an upload would live only inside a Docker volume. Instead
`nextcloud-custom-apps/nc_aio_tools/img/` carries copies, and
`css/bharatsuite.css` assigns them to Nextcloud's own `--image-logo` and
`--image-logoheader`, which core already reads with its stock logo as the fallback.
The favicon has no such variable, so it goes in as a `<link>` from
`BrandingAssetsListener`.
