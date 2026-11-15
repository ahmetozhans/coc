# VillageForge — Clash of Clans tools

Independent, unofficial Clash of Clans fan toolkit. Responsive HTML/CSS/JavaScript with no build step or frontend dependencies.

## Features

- Turkish and English interface, including Content Studio. Language choice persists when browser storage is available. User-entered titles and descriptions are preserved as written.
- Public layout gallery with Town Hall and category filters, search and browser-local favorites.
- Personal layout collection with compressed screenshots, official game links and JSON import/export.
- Builder planner with persistent builder count, parallel scheduling, approximate finish date and resource totals. Costs and durations are entered by the user; boosts and resource waiting time are excluded.
- Official in-game clan profile links and basic attack guides.
- Content Studio with local drafts, duplicate link checks, optional in-game check date, catalog import and export.

Saving, deleting and approving records update the interface only after browser storage succeeds. Invalid locally stored records are ignored. Existing storage keys are retained; no migration or clearing is required.

## Try locally

From the project directory, run `python3 -m http.server 8000`, then open `http://localhost:8000`. A local HTTP server does not execute the optional Cloudflare Pages Function.

Run the regression checks with Node.js 18 or newer:

```sh
node tests/regression.cjs
```

These tests use a lightweight DOM harness for tool behavior and translation logic. They do not replace real browser checks on desktop and mobile.

## Publish genuine layouts

The initial `catalog.json` is intentionally empty. No example layout is represented as a verified, usable game layout.

1. Open `admin.html`. If a published catalog already exists, import it in the publishing section before making changes. Importing merges by official layout URL; imported records replace matching local drafts.
2. Add an official `https://link.clashofclans.com/...` link with `action=OpenLayout` and an `id`, a title, Town Hall level and category. Use an HTTPS screenshot URL only when you have permission to publish that image.
3. Optionally enter a description and the date you actually checked the layout in game.
4. Approve the records to publish and download `catalog.json`.
5. Replace the repository's root `catalog.json`, commit and let your hosting provider deploy it.
6. Export a separate draft backup regularly. Catalog exports include only approved layouts. Local drafts, personal layouts, favorites and planner entries stay in the current browser; there is no account sync.

Content Studio is a local editor, not an authenticated server admin panel. Visiting it does not grant repository write access. Never enter GitHub tokens or API secrets into the page.

## Hosting and optional API

The frontend works on GitHub Pages or another static HTTP host. Keep all HTML, JS, CSS, `favicon.svg` and `catalog.json` at the same relative paths. Canonical URLs and sitemap currently target `https://ahmetozhans.github.io/coc/`; update them if the hosting address changes.

The optional `functions/api/clan.js` requires Cloudflare Pages Functions, a server-side `CLASH_API_TOKEN` secret and an approved outbound IP configuration; see [API_SETUP.md](API_SETUP.md). It is not connected to the current frontend. GitHub Pages cannot run this function.

Not included: accounts, automatic publishing, live clan statistics in the UI, official upgrade datasets, or guaranteed three-star attack strategies.

## Disclaimer

This content is not affiliated with, endorsed, sponsored, or specifically approved by Supercell and Supercell is not responsible for it. Clash of Clans and related trademarks belong to Supercell.
