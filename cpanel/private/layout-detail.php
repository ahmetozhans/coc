<?php
declare(strict_types=1);

function layout_detail_url(array $layout): string
{
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
    return ['War' => 'Savaş', 'Farming' => 'Ganimet', 'Trophy' => 'Kupa', 'Hybrid' => 'Karma'][$category] ?? $category;
}

function layout_detail_selection(string $id, array $catalog): ?array
{
    $catalog = array_values(array_filter($catalog, static fn (array $item): bool => empty($item['hidden'])));
    foreach ($catalog as $index => $layout) {
        if ($layout['id'] !== $id) {
            continue;
        }
        $related = array_values(array_filter($catalog, static fn (array $item): bool => $item['id'] !== $id));
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
        empty($layout['hidden']) && str_ends_with(layout_detail_url($layout), '--' . $match[1])));
    return count($matches) === 1 ? layout_detail_selection($matches[0]['id'], $catalog) : null;
}

function layout_notes(string $type, int $townHall): array
{
    return match ($type) {
        'War' => [
            "Bu düzen TH$townHall için savaş kategorisinde listeleniyor. Oyunda kurduktan sonra bir dostluk mücadelesiyle farklı giriş yönlerini dene.",
            ['Belediye binasına en kısa giriş yollarını karşılaştır.', 'Rakibin tek bir girişten birden çok önemli savunmaya ulaşıp ulaşmadığını kontrol et.'],
        ],
        'Farming' => [
            "Bu düzen TH$townHall için ganimet kategorisinde listeleniyor. Kullanırken korumak istediğin depoların konumuna öncelik ver.",
            ['Depolardan kaçına dış çeperden ulaşılabildiğine bak.', 'Birden fazla deponun aynı saldırıyla kolayca erişilebilir olup olmadığını kontrol et.'],
        ],
        'Trophy' => [
            "Bu düzen TH$townHall için kupa kategorisinde listeleniyor. Savunma tekrarlarında rakibin ilk yıldızını hangi yoldan aldığını gözlemle.",
            ['Rakibin dış binalardan ne kadar hızlı yıkım yüzdesi topladığını izle.', 'Belediye binasına giden yaklaşımları hava ve kara ordularıyla ayrı ayrı dene.'],
        ],
        default => [
            "Bu düzen TH$townHall için karma kategoride listeleniyor. Önceliğinin ganimet korumak mı, kupa savunmak mı olduğuna göre test et.",
            ['Belediye binası ve depolara giden yolları ayrı ayrı izle.', 'Önce bir dostluk mücadelesiyle dene, sonra tuzak ve dış bina yerleşimine karar ver.'],
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

function render_layout_detail(string $segment, array $catalog, string $base, ?string $requestPath = null): never
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
    $title = $layout['title'];
    $townHall = (int) $layout['th'];
    $category = layout_category_label($layout['type']);
    $image = https_link($layout['image'] ?? null);
    $game = game_link($layout['url'] ?? null, 'OpenLayout', 'id', '/^TH' . $townHall . ':[A-Za-z0-9:_-]+$/D');
    $description = trim((string) ($layout['description'] ?? ''));
    $canonical = rtrim($base, '/') . $path;
    $summary = layout_meta_summary($description, "$title. Clash of Clans TH$townHall $category düzeninin önizlemesini, oyun bağlantısını ve savunma notlarını incele.");
    [$defaultDescription, $tips] = layout_notes($layout['type'], $townHall);
    $trail = [
        ['name' => 'Ana sayfa', 'url' => $base],
        ['name' => 'Köy düzenleri', 'url' => $base . 'bases'],
        ['name' => $title, 'url' => $canonical],
    ];
    $breadcrumbs = ['@context' => 'https://schema.org', '@type' => 'BreadcrumbList',
        'itemListElement' => array_map(static fn (array $item, int $index): array =>
            ['@type' => 'ListItem', 'position' => $index + 1, 'name' => $item['name'], 'item' => $item['url']],
            $trail, array_keys($trail))];
    header('Content-Type: text/html; charset=UTF-8');
    ?>
<!doctype html>
<html lang="tr">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="theme-color" content="#0a1020">
  <meta name="description" content="<?= h($summary) ?>">
  <meta property="og:type" content="website">
  <meta property="og:site_name" content="Mahanpro">
  <meta property="og:locale" content="tr_TR">
  <meta property="og:title" content="<?= h($title) ?> | Mahanpro">
  <meta property="og:description" content="<?= h($summary) ?>">
  <meta property="og:url" content="<?= h($canonical) ?>">
  <?php if ($image): ?><meta property="og:image" content="<?= h($image) ?>"><?php endif; ?>
  <link rel="canonical" href="<?= h($canonical) ?>">
  <link rel="icon" href="/favicon.svg" type="image/svg+xml">
  <link rel="stylesheet" href="/layout-detail.css?v=2">
  <script src="/metrics.js?v=detail-1" defer></script>
  <script src="/layout-detail.js?v=2" defer></script>
  <script type="application/ld+json"><?= json_encode($breadcrumbs, JSON_THROW_ON_ERROR | JSON_HEX_TAG | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?></script>
  <title><?= h($title) ?> | Clash of Clans Köy Düzenleri – Mahanpro</title>
</head>
<body>
  <header class="site-top"><div class="shell top-inner"><a class="wordmark" href="/">Mahan<span>pro</span></a><a class="return-link" href="/bases">← Tüm köy düzenleri</a></div></header>
  <main class="shell" id="layoutDetail" data-id="<?= h($id) ?>" data-game-url="<?= h($game) ?>">
    <nav class="crumb" aria-label="Gezinme yolu"><a href="/">Ana sayfa</a><span aria-hidden="true">/</span><a href="/bases">Köy düzenleri</a><span aria-hidden="true">/</span><span aria-current="page">TH<?= $townHall ?></span></nav>
    <div class="detail-heading">
      <div class="chips"><span>TH<?= $townHall ?></span><span><?= h($category) ?></span></div>
      <h1><?= h($title) ?></h1>
    </div>
    <div class="detail-grid">
      <div class="detail-main">
        <figure class="hero layout-card" data-layout-id="<?= h($id) ?>">
          <?php if ($image): ?>
            <a href="<?= h($image) ?>" target="_blank" rel="noopener noreferrer" aria-label="Düzen görselini tam boy aç">
              <img src="<?= h($image) ?>" alt="<?= h($title) ?>" fetchpriority="high">
              <span class="zoom-label" aria-hidden="true">↗ Tam boy</span>
            </a>
          <?php else: ?>
            <p class="hero-empty">Bu düzen için önizleme bulunmuyor.</p>
          <?php endif; ?>
        </figure>
        <div class="action-row layout-controls">
          <?php if ($game): ?>
            <a class="button primary" data-game-open href="<?= h($game) ?>" target="_blank" rel="noopener noreferrer">Oyunda aç ↗</a>
            <button class="button" type="button" data-copy="game" hidden>Bağlantıyı kopyala</button>
            <button class="button" type="button" data-favorite data-favorite-url="<?= h($game) ?>" aria-pressed="false" hidden>♡ Favoriye ekle</button>
          <?php endif; ?>
        </div>
        <section class="panel layout-notes">
          <h2>Düzen açıklaması</h2>
          <p class="description"><?= h($description === '' ? $defaultDescription : $description) ?></p>
          <h3>Savunma notları</h3>
          <ul><?php foreach ($tips as $tip): ?><li><?= h($tip) ?></li><?php endforeach; ?></ul>
        </section>
        <section class="panel share">
          <h2>Bu düzeni paylaş</h2>
          <div class="action-row">
            <a class="button" href="<?= h('https://wa.me/?text=' . rawurlencode($title . ' ' . $canonical)) ?>" target="_blank" rel="noopener noreferrer">WhatsApp</a>
            <button class="button" type="button" data-copy="page" hidden>Sayfa bağlantısını kopyala</button>
          </div>
          <input id="shareLink" class="share-link" aria-label="Bu düzenin sayfa bağlantısı" type="url" value="<?= h($canonical) ?>" readonly>
        </section>
        <p class="message" id="detailMessage" aria-live="polite"></p>
        <?php if ($previous || $next): ?>
          <nav class="directions" aria-label="Komşu düzenler">
            <?php if ($previous): ?><a href="<?= h(layout_detail_url($previous)) ?>">← Önceki düzen</a><?php endif; ?>
            <?php if ($next): ?><a href="<?= h(layout_detail_url($next)) ?>">Sonraki düzen →</a><?php endif; ?>
          </nav>
        <?php endif; ?>
      </div>
      <aside class="detail-side">
        <section class="panel">
          <h2>Düzen bilgileri</h2>
          <dl class="specs" data-stat-layout="<?= h($id) ?>">
            <div><dt>Belediye binası</dt><dd>TH<?= $townHall ?></dd></div>
            <div><dt>Tür</dt><dd><?= h($category) ?></dd></div>
            <div><dt>Görüntülenme</dt><dd data-stat="views">—</dd></div>
            <div><dt>Oyuna açma / kopyalama</dt><dd data-stat="downloads">—</dd></div>
            <div><dt>Aktif favori</dt><dd data-stat="favorites">—</dd></div>
          </dl>
          <p class="message" id="statsStatus" aria-live="polite"></p>
        </section>
        <?php if ($related): ?>
          <section class="panel"><h2>Benzer köy düzenleri</h2><div class="related">
            <?php foreach ($related as $item): $thumb = https_link($item['image'] ?? null); ?>
              <a class="related-card" href="<?= h(layout_detail_url($item)) ?>">
                <?php if ($thumb): ?><img src="<?= h($thumb) ?>" alt="" loading="lazy" width="180" height="120"><?php endif; ?>
                <span>TH<?= (int) $item['th'] ?></span><strong><?= h($item['title']) ?></strong>
              </a>
            <?php endforeach; ?>
          </div></section>
        <?php endif; ?>
      </aside>
    </div>
  </main>
</body>
</html>
    <?php
    exit;
}
