# Mahanpro SEO launch checklist

This repository is a static front-end. The production `mahanpro.fun` hosting may use additional PHP pages and content that are **not** present here. Merge only after reconciling paths and content with the deployed site; GitHub changes alone do not update independent hosting.

## Changes in this branch

- Uses `https://mahanpro.fun/` as the canonical host in the public HTML pages, `robots.txt` and `sitemap.xml`.
- Adds unique English page titles and search descriptions for Clash of Clans base layouts, army setups, attack guides and tools.
- Adds Open Graph / X (Twitter) sharing metadata and a site-hosted preview image.
- Adds `WebSite`, `WebPage` / `CollectionPage` and breadcrumb JSON-LD based on real navigational pages.
- Aligns the content-studio default SEO titles and descriptions with the static pages.

## Required production verification

1. Confirm that `https://mahanpro.fun/` is the desired canonical address and redirects from HTTP and the `www` host work correctly.
2. On the **live production site**, verify each URL listed in `sitemap.xml` returns HTTP 200 with substantive English content and a self-referencing canonical. Remove or replace any paths no longer deployed (especially if clan or upgrade pages were removed from hosting).
3. If hosting renders pages via PHP rather than these static HTML files, apply the equivalent metadata in the actual PHP templates before submitting the sitemap.
4. Upload the revised `robots.txt` and `sitemap.xml` to the **production domain's root** and ensure their MIME type/content is correct, not an HTML fallback or error page.
5. Keep admin/login/review/internal pages out of the sitemap and use server-side authentication where content is private. A robots.txt rule is not a security boundary.
6. Check the deployed page HTML source for missing meta, JavaScript-only content, duplicate headings, mismatched canonical URLs and missing image alt text.
7. Verify the site in [Google Search Console](https://search.google.com/search-console), submit `https://mahanpro.fun/sitemap.xml`, inspect the homepage and key pages, and check indexing/crawl reports after publishing. Use Bing Webmaster Tools as appropriate.
8. In the live content CMS, check whether runtime `seoTitle` / `seoDescription` values override these file defaults, and align those values as well.
9. Before creating additional Town Hall landing pages, publish unique, useful content and authentic base examples rather than near-duplicate doorway pages.

A sitemap submission is a discovery hint, **not** a guarantee of indexing or first-place rankings. Do not use fabricated freshness dates, review ratings, or unsupported FAQ schema.
