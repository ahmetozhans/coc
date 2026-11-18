# Verification record — 18 November 2026

The feature branch is based on cpanel-php-migration at a9734253859c3f7143c59555c3007a96d5ba39b3. Production was inspected read-only.

## Existing production

- /bases, /api/content and an existing /bases/...--stable-suffix detail URL returned content.
- The public endpoint contained 22 layouts and CMS revision 2 at inspection time.
- The production CSS differs from the repository root CSS. The production app.js includes cPanel build transformations. The update archive excludes both.
- Private production PHP files and database credentials were not available for byte comparison. The installer checks common PHP files against the exact branch baseline and stops on differences.

## Source checks

Public HTML from CoC Base Finder, Clash Loot and ClashLy was inspected. The adapters decoded their public server-rendered records and normalized supported TH/category combinations. Unsupported progress/funny/BH categories were excluded.

Two actual Clash Loot screenshots were fetched alongside their official OpenLayout share URLs. The fixture provenance file retains the source detail URLs, image URLs and source publication times. Their image bytes were decoded and optimized into WebP. These are test fixtures, not published entries.

The image hosts for CoC Base Finder and ClashLy returned HTTP 403 in this execution environment. Access was not retried with alternate identities or bypasses. Those images could not be validated end to end here. Production may have different access, and the feature records such failures without queuing an incomplete layout.

PHP direct DNS/network requests are restricted in this execution environment. Daily scan integration therefore uses a transport fixture carrying the real captured public records and screenshots, including simulated 403 responses. The deployed cURL transport still requires a staging smoke test on the cPanel host.

## Executed checks

- PHP 8.4 lint on the new modules, installer and all modified PHP entry points.
- MariaDB 11.8 with fresh InnoDB tables. The additive migration was applied twice.
- Actual source record normalization and actual screenshot conversion.
- First discovery remains private. Repeating the same version does not create another proposal.
- Changed text, a stale fingerprint and altered image bytes cannot publish.
- Exact administrator approval publishes the reviewed snapshot.
- A source update keeps the existing content ID and requires a new review.
- Superseded versions cannot publish. Rejected unchanged versions remain rejected.
- Duplicate decoded share IDs, exact image hashes and visual similarity are checked.
- An administrator CMS override blocks automated replacement.
- Archiving preserves content IDs, detail URLs, view counts and favorite counts.
- The 21st active layout in a TH is rejected and the database transaction rolls back.
- The normal Studio collection validation enforces the same cap.
- A competing scan lock blocks another run. The UTC-day gate persists between connections.
- Partial source errors are logged and remaining sources are processed.
- An installer dry run refuses a deliberately changed existing PHP file.
- Existing JavaScript regression, CMS and admin DOM suites passed.
- Existing content validation and six approval-policy tests passed.
- The real PHP sitemap handler produced valid XML with 26 unique URLs, including 20 test detail URLs.
- Chromium tested 1440px desktop and 390px mobile widths with the inspected production CSS. Public details and the authenticated collector panel had no horizontal overflow. The game button, preview, queue selection, editable content and JSON-LD were checked.
- New public detail UI and generated proposal copy use English. Admin/Studio remains Turkish.

Browser requests were served from captured/generated test HTML and local assets. No production administrator account or production database was modified. No gameplay import was performed. New layouts remain unverified until an administrator explicitly records an in-game check.

## Remaining host checks before enabling automation

Run the package preflight against the real private PHP files, apply the additive migration to a staging clone, test HTTPS sessions and image previews, run one authorized scan from cPanel, approve a reviewed staging example, then inspect its public endpoint, image, canonical URL and sitemap. Enable the hourly cron only after these checks.
