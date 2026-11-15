# Mahanpro — Clash of Clans tools

Independent, unofficial Clash of Clans fan toolkit. Responsive HTML/CSS/JavaScript with no build step or frontend dependencies. All six pages use the Night Village design: a midnight palette, purple and gold accents, original fantasy village artwork, custom SVG icons and a completely replaced stylesheet. The mobile layout includes safe-area-aware bottom navigation, at least 44 px primary touch controls, 16 px form fields, responsive cards and a horizontally scrollable Town Hall filter row. System fonts avoid external font requests; the hero illustration is locally hosted WebP (~211 KiB).

## Features

- Turkish and English interface, including Content Studio. Language choice persists when browser storage is available. User-entered titles and descriptions are preserved as written.
- Public layout gallery with Town Hall and category filters, search and browser-local favorites.
- Visitors can browse, copy official game links and favorite published layouts. Layout creation, editing, approval and catalog import/export are available only in Content Studio; there is no visitor upload or personal-layout import form.
- Builder planner with persistent builder count, parallel scheduling, approximate finish date and resource totals. Costs and durations are entered by the user; boosts and resource waiting time are excluded.
- Official in-game clan profile links and basic attack guides.
- Content Studio with local drafts, duplicate link checks, optional in-game check date, catalog import and export.

Saving, deleting and approving records update the interface only after browser storage succeeds. Invalid locally stored records are ignored. Existing storage keys are retained; no migration or clearing is required. Legacy personal layouts are left in browser storage but are never read or displayed by the public gallery.

## Try locally

From the project directory, run `python3 -m http.server 8000`, then open `http://localhost:8000`. A local HTTP server does not execute the optional Cloudflare Pages Function.

Run the regression checks with Node.js 18 or newer:

```sh
node tests/regression.cjs
```

These regression tests use a lightweight DOM harness for tool behavior and translation logic.

For real browser checks, use an environment with Python Playwright and Chromium installed. Start the site on port 8765, then run:

```sh
python3 -m http.server 8765 --bind 127.0.0.1
# In another terminal:
python3 tests/browser.py
```

`BROWSER_BIN`, `VILLAGEFORGE_QA_URL` and `VILLAGEFORGE_QA_SCREENSHOTS` can override the browser executable, server URL and screenshot directory. The browser suite checks all six pages in Turkish and English at 360, 390, 430, 768, 1024 and 1440 px, including all Studio sections, horizontal overflow, JavaScript errors and core tool interactions. It writes screenshots to `/tmp/villageforge-qa` by default. Browser-local fixtures are confined to disposable test contexts.

## Publish genuine layouts

`catalog.json` contains an editor-selected collection from public source pages. See [CATALOG_SOURCES.md](CATALOG_SOURCES.md) for origins and dates. These are sourced game links, not claims of in-game testing. Every published entry has a matching local preview image. The public gallery omits external source labels and links; provenance remains in the internal catalog audit and is preserved by Studio import/export and edits. Image previews open in a full-size dialog. Shared views, copy actions and active favorites are stored in the live D1 service described in [services/layout-stats/README.md](services/layout-stats/README.md).

1. Open `admin.html`. If a published catalog already exists, import it in the publishing section before making changes. Importing merges by official layout URL; imported records replace matching local drafts.
2. Add an official `https://link.clashofclans.com/...` link with `action=OpenLayout` and an `id`, a title, Town Hall level and category. Use an HTTPS screenshot URL only when you have permission to publish that image.
3. Optionally enter a description and the date you actually checked the layout in game.
4. Approve the records to publish and download `catalog.json`.
5. Replace the repository's root `catalog.json`, commit and let your hosting provider deploy it.
6. Export a separate draft backup regularly. Catalog exports include only approved layouts. Local drafts, favorites and planner entries stay in the current browser; there is no account sync.

Public pages do not link to Content Studio; the owner can open `admin.html` directly. Content Studio is a local editor, not an authenticated server admin panel. Its URL is not an access-control mechanism. Only someone with GitHub repository write permission can publish a changed catalog; no public endpoint accepts layout writes. Visiting it does not grant repository write access. Never enter GitHub tokens or API secrets into the page.

## Hosting and optional API

The frontend works on GitHub Pages or another static HTTP host. Keep all HTML, JS, CSS, `favicon.svg` and `catalog.json` at the same relative paths. Canonical URLs and sitemap currently target `https://ahmetozhans.github.io/coc/`; update them if the hosting address changes.

The optional `functions/api/clan.js` requires Cloudflare Pages Functions, a server-side `CLASH_API_TOKEN` secret and an approved outbound IP configuration; see [API_SETUP.md](API_SETUP.md). It is not connected to the current frontend. GitHub Pages cannot run this function.

Not included: accounts, automatic publishing, live clan statistics in the UI, official upgrade datasets, or guaranteed three-star attack strategies.

## Disclaimer

This content is not affiliated with, endorsed, sponsored, or specifically approved by Supercell and Supercell is not responsible for it. Clash of Clans and related trademarks belong to Supercell.

## Artwork

`assets/night-village.webp` is original AI-generated decorative artwork, not a game screenshot or a playable layout. It is not included as an entry in the layout catalog. Custom inline SVG icons and `favicon.svg` are part of the Night Village theme.

## Gallery counters

`metrics.js` reads persistent shared totals from the Mahanpro statistics service. No local or invented totals are substituted when the service is unavailable. View events are emitted after one second with at least half of a card visible (or opening its image preview). The backend deduplicates views per browser per UTC day and copy actions per browser per hour. Favorites count current active browser favorites and decrease when removed.

“Downloads” means game-open or successful link-copy actions, not verified saves inside Clash of Clans. Counts start when tracking is enabled; historical activity is not fabricated. Anonymous browser IDs are random and stored as hashes server-side.

With the local server running, `python3 tests/shared-statistics.py` verifies real cross-browser persistence and deduplication using isolated random `qa-` IDs that never appear in the public catalog.

## Approval-first content suggestions

The automated content task researches base layouts and original bilingual attack guides. New material stays in `content-inbox.json` until the owner approves its exact version in the secure **İçerik önerileri** admin page. Rejections and approvals persist in D1. Approved items publish on the next successful hourly task run. See [CONTENT_AUTOMATION.md](CONTENT_AUTOMATION.md) for the complete publication gate and recovery rules.

`guides.json` supplies the guide page and supports Town Hall/category filtering. Its initial three entries preserve the previously published general-principle guides. The initial inbox contains two new layout suggestions and one new guide, all awaiting owner decisions.
