<?php
declare(strict_types=1);

function layout_detail_url(array $layout): string
{
    return '/bases/' . rawurlencode($layout['id']);
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

function render_layout_detail(string $id, array $catalog, string $base): never
{
    $selected = layout_detail_selection($id, $catalog);
    if ($selected === null) {
        not_found();
    }
    [$layout, $previous, $next, $related] = $selected;
    $title = $layout['title'];
    $townHall = (int) $layout['th'];
    $category = layout_category_label($layout['type']);
    $image = https_link($layout['image'] ?? null);
    $game = game_link($layout['url'] ?? null, 'OpenLayout', 'id', '/^TH' . $townHall . ':[A-Za-z0-9:_-]+$/D');
    $description = trim((string) ($layout['description'] ?? ''));
    $canonical = rtrim($base, '/') . layout_detail_url($layout);
    $summary = $description !== '' ? $description : "TH$townHall · $category köy düzeni. Düzenin önizlemesini ve oyun bağlantısını Mahanpro’da incele.";
    header('Content-Type: text/html; charset=UTF-8');
    ?>
<!doctype html>
<html lang="tr">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="theme-color" content="#0a1020">
  <meta name="description" content="<?= h($summary) ?>">
  <meta property="og:title" content="<?= h($title) ?> | Mahanpro">
  <meta property="og:description" content="<?= h($summary) ?>">
  <meta property="og:url" content="<?= h($canonical) ?>">
  <?php if ($image): ?><meta property="og:image" content="<?= h($image) ?>"><?php endif; ?>
  <link rel="canonical" href="<?= h($canonical) ?>">
  <link rel="icon" href="/favicon.svg" type="image/svg+xml">
  <link rel="stylesheet" href="/layout-detail.css?v=1">
  <script src="/metrics.js?v=detail-1" defer></script>
  <script src="/layout-detail.js?v=1" defer></script>
  <title><?= h($title) ?> | Mahanpro</title>
</head>
<body>
  <header class="site-top"><div class="shell top-inner"><a class="wordmark" href="/">Mahan<span>pro</span></a><a href="/bases">Köy düzenleri</a></div></header>
  <main class="shell" id="layoutDetail" data-id="<?= h($id) ?>" data-game-url="<?= h($game) ?>">
    <nav class="crumb" aria-label="Gezinme yolu"><a href="/">Ana sayfa</a><span aria-hidden="true">/</span><a href="/bases">Köy düzenleri</a><span aria-hidden="true">/</span><span aria-current="page">TH<?= $townHall ?></span></nav>
    <h1><?= h($title) ?></h1>
    <div class="chips"><span>TH<?= $townHall ?></span><span><?= h($category) ?></span></div>
    <div class="detail-grid">
      <div class="detail-main">
        <figure class="hero layout-card" data-layout-id="<?= h($id) ?>">
          <?php if ($image): ?>
            <a href="<?= h($image) ?>" target="_blank" rel="noopener noreferrer" aria-label="Düzen görselini tam boy aç">
              <img src="<?= h($image) ?>" alt="<?= h($title) ?>" fetchpriority="high">
            </a>
          <?php else: ?>
            <p class="hero-empty">Bu düzen için önizleme bulunmuyor.</p>
          <?php endif; ?>
        </figure>
        <div class="action-row">
          <?php if ($game): ?>
            <a class="button primary" data-game-open href="<?= h($game) ?>" target="_blank" rel="noopener noreferrer">Oyunda aç ↗</a>
            <button class="button" type="button" data-copy="game" hidden>Oyun bağlantısını kopyala</button>
            <button class="button" type="button" data-favorite data-favorite-url="<?= h($game) ?>" aria-pressed="false" hidden>♡ Favoriye ekle</button>
          <?php endif; ?>
        </div>
        <?php if ($description !== ''): ?>
          <section class="panel"><h2>Düzen açıklaması</h2><p class="description"><?= h($description) ?></p></section>
        <?php endif; ?>
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
