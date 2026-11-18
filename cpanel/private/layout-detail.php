<?php
declare(strict_types=1);

function layout_detail_url(array $layout): string
{
    $canonical = $layout['canonicalPath'] ?? '';
    if (is_string($canonical) && preg_match('~^/bases/[a-z0-9-]+--[a-f0-9]{12}$~D', $canonical)
        && str_ends_with($canonical, '--' . substr(hash('sha256', $layout['id']), 0, 12))) {
        return $canonical;
    }
    $title = strtr($layout['title'], [
        'ş' => 's', 'Ş' => 's', 'ç' => 'c', 'Ç' => 'c', 'ğ' => 'g', 'Ğ' => 'g',
        'ü' => 'u', 'Ü' => 'u', 'ö' => 'o', 'Ö' => 'o', 'ı' => 'i', 'İ' => 'i',
    ]);
    $slug = trim(preg_replace('/[^a-z0-9]+/', '-', strtolower($title)), '-');
    $slug = rtrim(substr($slug, 0, 70), '-') ?: 'th' . (int) $layout['th'] . '-koy-duzeni';
    return '/bases/' . $slug . '--' . substr(hash('sha256', $layout['id']), 0, 12);
}

function layout_category_label(string $category): string
{
    return $category;
}

function layout_detail_selection(string $id, array $catalog): ?array
{
    $catalog = array_values(array_filter($catalog, static fn (array $item): bool => empty($item['hidden']) || !empty($item['archived'])));
    foreach ($catalog as $index => $layout) {
        if ($layout['id'] !== $id) {
            continue;
        }
        $related = array_values(array_filter($catalog, static fn (array $item): bool => $item['id'] !== $id && empty($item['hidden']) && empty($item['archived'])));
        $score = static fn (array $item): int => ((int) $item['th'] === (int) $layout['th'] ? 2 : 0)
            + ($item['type'] === $layout['type'] ? 1 : 0);
        usort($related, static fn (array $a, array $b): int => $score($b) <=> $score($a));
        return [$layout, $catalog[$index - 1] ?? null, $catalog[$index + 1] ?? null, array_slice($related, 0, 6)];
    }
    return null;
}

function layout_detail_request(string $segment, array $catalog): ?array
{
    $selected = layout_detail_selection($segment, $catalog);
    if ($selected !== null || preg_match('/--([a-f0-9]{12})$/D', $segment, $match) !== 1) {
        return $selected;
    }
    $matches = array_values(array_filter($catalog, static fn (array $layout): bool =>
        (empty($layout['hidden']) || !empty($layout['archived'])) && str_ends_with(layout_detail_url($layout), '--' . $match[1])));
    return count($matches) === 1 ? layout_detail_selection($matches[0]['id'], $catalog) : null;
}

function layout_notes(string $type, int $townHall): array
{
    return match ($type) {
        'War' => [
            "This layout is listed as a TH$townHall war base. Import it into the game and test different entry points in a friendly challenge.",
            ['Compare the shortest routes to the Town Hall.', 'Check whether a single entry exposes several important defenses.'],
        ],
        'Farming' => [
            "This layout is listed as a TH$townHall farming base. Focus on the placement of the resource storages you want to protect.",
            ['Check how many storages can be reached from the outer edge.', 'Look for routes that expose several storages to the same attack.'],
        ],
        'Trophy' => [
            "This layout is listed as a TH$townHall trophy base. Use defense replays to see how attackers reach their first star.",
            ['Watch how quickly attackers gain destruction from outside buildings.', 'Test air and ground approaches to the Town Hall separately.'],
        ],
        default => [
            "This layout is listed as a TH$townHall hybrid base. Test it against your trophy and resource protection priorities.",
            ['Review routes to the Town Hall and storages separately.', 'Try a friendly challenge before adjusting traps and outside buildings.'],
        ],
    };
}

function layout_meta_summary(string $description, string $fallback): string
{
    $text = $description === '' ? $fallback : preg_replace('/\s+/u', ' ', $description);
    if (preg_match('/^(.{1,156})\s.+$/u', $text, $match) && preg_match('/^.{161}/u', $text)) {
        return rtrim($match[1], ' ,.;:') . '…';
    }
    return preg_match('/^(.{157}).{4}/u', $text, $match) ? $match[1] . '…' : $text;
}

function layout_detail_shell(string $galleryTemplate): array
{
    $fragments = [];
    foreach ([
        'theme' => '~<link rel="stylesheet" href="/?mahanpro-night\.css(?:\?[^"<>]*)?">~',
        'header' => '~<header class="site-header">.*?</header>~s',
        'footer' => '~<footer class="site-footer">.*?</footer>~s',
        'mobile' => '~<nav class="mobile-nav"[^>]*>.*?</nav>~s',
    ] as $name => $pattern) {
        if (preg_match($pattern, $galleryTemplate, $match) !== 1) {
            throw new RuntimeException('Gallery template fragment is missing: ' . $name);
        }
        $fragments[$name] = $match[0];
    }
    $fragments['theme'] = str_replace('href="mahanpro-night.css', 'href="/mahanpro-night.css', $fragments['theme']);
    // Detail pages use English and share the gallery navigation.
    $fragments['header'] = preg_replace('~<div class="header-actions">.*?</div>~s', '', $fragments['header']);
    foreach (['header', 'footer', 'mobile'] as $name) {
        $fragments[$name] = str_replace('aria-current="page"', 'aria-current="location"', $fragments[$name]);
    }
    return $fragments;
}

function render_layout_detail(string $segment, array $catalog, string $base, ?string $requestPath = null, ?string $galleryTemplate = null): never
{
    $selected = layout_detail_request($segment, $catalog);
    if ($selected === null) {
        not_found();
    }
    [$layout, $previous, $next, $related] = $selected;
    $id = $layout['id'];
    $path = layout_detail_url($layout);
    if ($requestPath !== null && $requestPath !== $path) {
        header('Location: ' . $path, true, 301);
        exit;
    }
    $shell = layout_detail_shell($galleryTemplate ?? (string) file_get_contents(__DIR__ . '/templates/bases.html'));
    $title = $layout['title'];
    $townHall = (int) $layout['th'];
    $category = layout_category_label($layout['type']);
    $image = https_link($layout['image'] ?? null);
    $game = game_link($layout['url'] ?? null, 'OpenLayout', 'id', '/^TH' . $townHall . ':[A-Za-z0-9:_-]+$/D');
    $description = trim((string) ($layout['description'] ?? ''));
    $verification = !empty($layout['inGameVerifiedAt']) && hash_equals(
        $layout['verificationHash'] ?? '', hash('sha256', canonical_json((object) ['url' => $layout['url'], 'image' => $layout['image']])))
        ? $layout['inGameVerifiedAt'] : null;
    $canonical = rtrim($base, '/') . $path;
    $summary = layout_meta_summary($description, "$title. Clash of Clans TH$townHall $category base layout preview, official game link and defense notes.");
    [$defaultDescription, $tips] = layout_notes($layout['type'], $townHall);
    $trail = [
        ['name' => 'Home', 'url' => $base],
        ['name' => 'Base layouts', 'url' => $base . 'bases'],
        ['name' => $title, 'url' => $canonical],
    ];
    $breadcrumbs = ['@context' => 'https://schema.org', '@type' => 'BreadcrumbList',
        'itemListElement' => array_map(static fn (array $item, int $index): array =>
            ['@type' => 'ListItem', 'position' => $index + 1, 'name' => $item['name'], 'item' => $item['url']],
            $trail, array_keys($trail))];
    $pageSchema = ['@context' => 'https://schema.org', '@type' => 'WebPage', '@id' => $canonical,
        'url' => $canonical, 'name' => $title, 'description' => $summary, 'inLanguage' => 'en',
        'isPartOf' => ['@type' => 'WebSite', 'name' => 'Mahanpro', 'url' => $base]];
    if ($image) {
        $pageSchema['primaryImageOfPage'] = ['@type' => 'ImageObject', 'contentUrl' => $image];
    }
    header('Content-Type: text/html; charset=UTF-8');
    ?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="theme-color" content="#080f22">
  <meta name="description" content="<?= h($summary) ?>">
  <meta property="og:type" content="website">
  <meta property="og:site_name" content="Mahanpro">
  <meta property="og:locale" content="en_US">
  <meta property="og:title" content="<?= h($title) ?> | Mahanpro">
  <meta property="og:description" content="<?= h($summary) ?>">
  <meta property="og:url" content="<?= h($canonical) ?>">
  <?php if ($image): ?><meta property="og:image" content="<?= h($image) ?>"><?php endif; ?>
  <link rel="canonical" href="<?= h($canonical) ?>">
  <link rel="icon" href="/favicon.svg" type="image/svg+xml">
  <?= $shell['theme'] ?>
  <link rel="stylesheet" href="/layout-detail.css?v=3">
  <script src="/metrics.js?v=detail-1" defer></script>
  <script src="/layout-detail.js?v=collector-en-1" defer></script>
  <script type="application/ld+json"><?= json_encode($breadcrumbs, JSON_THROW_ON_ERROR | JSON_HEX_TAG | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?></script>
  <script type="application/ld+json"><?= json_encode($pageSchema, JSON_THROW_ON_ERROR | JSON_HEX_TAG | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?></script>
  <title><?= h($title) ?> | Clash of Clans Base Layouts – Mahanpro</title>
</head>
<body class="layout-detail-page">
  <a class="skip-link" href="#layoutDetail">Skip to content</a>
  <?= $shell['header'] ?>
  <main id="layoutDetail" data-id="<?= h($id) ?>" data-game-url="<?= h($game) ?>">
    <div class="page-banner">
      <div class="container banner-content detail-heading">
        <nav class="detail-crumb" aria-label="Breadcrumb"><a href="/">Home</a><span aria-hidden="true">/</span><a href="/bases">Base layouts</a></nav>
        <h1><?= h($title) ?></h1>
        <div class="detail-chips"><span class="chip">TH<?= $townHall ?></span><span class="chip"><?= h($category) ?></span></div>
        <p><?= !empty($layout['archived']) ? 'Archived layout · ' : '' ?><?= $verification ? 'Verified in game · ' . h($verification) : 'Unverified' ?></p>
      </div>
    </div>
    <div class="container page-content">
      <div class="detail-grid">
        <div class="detail-main">
          <figure class="detail-preview layout-card" data-layout-id="<?= h($id) ?>">
            <?php if ($image): ?>
              <a href="<?= h($image) ?>" target="_blank" rel="noopener noreferrer" aria-label="Open the full-size layout image">
                <img src="<?= h($image) ?>" alt="<?= h($title) ?>" fetchpriority="high">
                <span class="zoom-label chip" aria-hidden="true">↗ Full size</span>
              </a>
            <?php else: ?>
              <p class="hero-empty">No preview is available for this layout.</p>
            <?php endif; ?>
          </figure>
          <?php if ($game): ?>
            <div class="action-row layout-controls">
              <a class="button primary" data-game-open href="<?= h($game) ?>" target="_blank" rel="noopener noreferrer">Open in game ↗</a>
              <button class="button" type="button" data-copy="game" hidden>Copy game link</button>
              <button class="button" type="button" data-favorite data-favorite-url="<?= h($game) ?>" aria-pressed="false" hidden>♡ Add to favorites</button>
            </div>
          <?php endif; ?>
          <section class="panel layout-notes">
            <h2>About this layout</h2>
            <p class="description"><?= h($description === '' ? $defaultDescription : $description) ?></p>
            <h3>Defense notes</h3>
            <ul><?php foreach ($tips as $tip): ?><li><?= h($tip) ?></li><?php endforeach; ?></ul>
          </section>
          <section class="panel share">
            <h2>Share this layout</h2>
            <div class="action-row">
              <a class="button" href="<?= h('https://wa.me/?text=' . rawurlencode($title . ' ' . $canonical)) ?>" target="_blank" rel="noopener noreferrer">WhatsApp</a>
              <button class="button" type="button" data-copy="page" hidden>Copy page link</button>
            </div>
            <input id="shareLink" class="share-link" aria-label="Layout page URL" type="url" value="<?= h($canonical) ?>" readonly>
          </section>
          <p class="message" id="detailMessage" aria-live="polite"></p>
          <?php if ($previous || $next): ?>
            <nav class="directions" aria-label="More layouts">
              <?php if ($previous): ?><a href="<?= h(layout_detail_url($previous)) ?>">← Previous layout</a><?php endif; ?>
              <?php if ($next): ?><a class="next" href="<?= h(layout_detail_url($next)) ?>">Next layout →</a><?php endif; ?>
            </nav>
          <?php endif; ?>
        </div>
        <aside class="detail-side">
          <section class="panel">
            <h2>Layout details</h2>
            <dl class="specs" data-stat-layout="<?= h($id) ?>">
              <div><dt>Town Hall</dt><dd>TH<?= $townHall ?></dd></div>
              <div><dt>Category</dt><dd><?= h($category) ?></dd></div>
              <div><dt>Last checked</dt><dd><?= h($layout['lastCheckedAt'] ?? 'Not recorded') ?></dd></div>
              <div><dt>In-game verification</dt><dd><?= $verification ? h($verification) : 'Unverified' ?></dd></div>
              <div><dt>Views</dt><dd data-stat="views">—</dd></div>
              <div><dt>Game opens / copies</dt><dd data-stat="downloads">—</dd></div>
              <div><dt>Active favorites</dt><dd data-stat="favorites">—</dd></div>
            </dl>
            <p class="message" id="statsStatus" aria-live="polite"></p>
          </section>
          <?php if ($related): ?>
            <section class="panel"><h2>Similar base layouts</h2><div class="related">
              <?php foreach ($related as $item): $thumb = https_link($item['image'] ?? null); ?>
                <a class="related-card layout-card" href="<?= h(layout_detail_url($item)) ?>">
                  <?php if ($thumb): ?><img src="<?= h($thumb) ?>" alt="" loading="lazy" width="180" height="120"><?php endif; ?>
                  <span class="chip">TH<?= (int) $item['th'] ?></span><strong><?= h($item['title']) ?></strong>
                </a>
              <?php endforeach; ?>
            </div></section>
          <?php endif; ?>
        </aside>
      </div>
    </div>
  </main>
  <?= $shell['footer'] ?>
  <?= $shell['mobile'] ?>
</body>
</html>
    <?php
    exit;
}
