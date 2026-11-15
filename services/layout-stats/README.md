# Shared layout statistics

Live API: https://mahanpro-layout-stats.aerialmandrill32.chatgpt.site

Sites project: `appgprj_6afa25fb74448191a658f788ca14a329`. Source is deployed from its Sites source repository. This directory mirrors the Worker and initial schema for review. No tokens or credentials are stored here.

Cloudflare D1 stores aggregate activity shared by every visitor. Visitors are represented by SHA-256 hashes of random browser-local UUIDs. No browser UUIDs are returned by the public API.

- `GET /api/stats?ids=id1,id2`: read aggregate totals for up to 50 layout IDs.
- `POST /api/events`: JSON `{id, visitor, type, active?}`. Types: `view`, `copy`, `favorite`.
- Views are deduplicated per browser, layout and UTC day. Copies are deduplicated per browser, layout and hour. Favorite state is idempotent and removing a favorite reduces the active total.
- Copy totals measure game-open/link-copy actions, not completed in-game saves.
- Gallery view events are emitted only after a card remains at least half visible for one second or its image preview is opened.
- Counts show a dash until the server responds; outages never produce invented totals.

The service is public for anonymous gallery visitors. Origin checks, input limits, browser deduplication and a per-visitor event bound reduce accidental inflation; anonymous public counts are not fraud-proof financial analytics.

## Owner review

`/admin` requires the Site owner's ChatGPT sign-in. `/api/reviews` requires a verified platform identity matching the runtime `ADMIN_EMAIL` allowlist. Public visitors cannot approve content. POST decisions additionally require same-origin JSON and an exact current proposal fingerprint. `content_reviews` stores decisions by proposal/version; the scheduled task reads them through owner-authorized Sites database tools. It never writes decisions. See `CONTENT_AUTOMATION.md` at the repository root.
