<?php
declare(strict_types=1);
require_once __DIR__ . '/collector.php';

function collector_form_token(): string
{
    return '<input type="hidden" name="csrf" value="' . h($_SESSION['csrf']) . '">';
}

function collector_panel(string $body, int $status = 200): never
{
    http_response_code($status);
    header('Content-Type: text/html; charset=UTF-8');
    header('Cache-Control: no-store');
    header('X-Robots-Tag: noindex, nofollow');
    header("Content-Security-Policy: default-src 'none'; style-src 'self'; img-src 'self'; form-action 'self'; frame-ancestors 'none'; base-uri 'none'");
    echo '<!doctype html><html lang="tr"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">'
        . '<title>Otomatik Köy Toplama | Mahanpro</title><link rel="stylesheet" href="/mahanpro-night.css">'
        . '<link rel="stylesheet" href="/collector-admin.css?v=1"></head><body class="collector-page">'
        . '<header class="site-header"><div class="container header-row"><a class="brand" href="/studio">Mahan<span class="brand-gold">pro</span></a>'
        . '<a class="button small" href="/studio">← İçerik Stüdyosu</a></div></header><main class="container page-content">'
        . '<div class="section-heading"><div><span class="eyebrow">YÖNETİCİ İNCELEMESİ</span><h1>Otomatik Köy Toplama</h1></div></div>'
        . '<nav class="collector-tabs" aria-label="Toplama yönetimi"><a class="button small" href="/admin/collector">Bekleyenler</a>'
        . '<a class="button small" href="?view=duplicate">Yinelenenler</a><a class="button small" href="?view=errors">Hatalar</a>'
        . '<a class="button small" href="?view=published">Yayımlananlar</a><a class="button small" href="?view=rejected">Reddedilenler</a>'
        . '<a class="button small" href="?view=archive">Arşivleme</a><a class="button small" href="?view=history">Geçmiş</a>'
        . '<a class="button small" href="?view=settings">Kaynaklar ve ayarlar</a></nav>' . $body . '</main></body></html>';
    exit;
}

function collector_verify_in_game(PDO $pdo, string $id, string $expected, bool $confirmed): void
{
    if (!$confirmed) {
        throw new InvalidArgumentException('Oyun içinde aynı bağlantıyı içe aktardığını ve görselle eşleştiğini onayla.');
    }
    $pdo->beginTransaction();
    try {
        $state = cms_state($pdo, true);
        $doc = $state['document'];
        $rows = array_column(content($pdo, 'layout', $doc, true), null, 'id');
        $item = $rows[$id] ?? null;
        if (!$item || !hash_equals(collector_hash($item), $expected)) {
            throw new RuntimeException('Düzen değişti. Paneli yenile.');
        }
        $item['inGameVerifiedAt'] = collector_now();
        // Bind verification to the actual game ID and exact screenshot.
        $item['verificationHash'] = collector_hash(['url' => $item['url'], 'image' => $item['image']]);
        $doc->layoutOverrides->$id = json_decode(json_encode($item));
        $pdo->prepare('UPDATE admin_content SET document = ?, revision = revision + 1, updated_at = ? WHERE id = 1')->execute([
            json_encode($doc, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE), collector_now()]);
        collector_audit($pdo, 'game-verified', $id, $expected);
        $pdo->commit();
    } catch (Throwable $error) {
        $pdo->rollBack();
        throw $error;
    }
}

function collector_edit_proposal(PDO $pdo, int $id, string $expected, string $title, string $description): void
{
    if (!bounded_text($title, 70, true) || !bounded_text($description, 1200, true)) {
        throw new InvalidArgumentException('Başlık 70, açıklama 1.200 karakteri aşamaz.');
    }
    $pdo->beginTransaction();
    try {
        $q = $pdo->prepare('SELECT * FROM collector_proposals WHERE id = ? FOR UPDATE');
        $q->execute([$id]);
        $row = $q->fetch(PDO::FETCH_ASSOC);
        if (!$row || $row['status'] !== 'pending' || !hash_equals($row['fingerprint'], $expected)) {
            throw new RuntimeException('Öneri değişti veya işlendi.');
        }
        $payload = json_decode($row['payload'], true, 64, JSON_THROW_ON_ERROR);
        $payload['title'] = trim($title);
        $payload['description'] = trim($description);
        $fingerprint = collector_fingerprint($payload, $row['image_sha256']);
        $pdo->prepare('UPDATE collector_proposals SET payload = ?, fingerprint = ? WHERE id = ?')->execute([
            json_encode($payload, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE), $fingerprint, $id]);
        collector_audit($pdo, 'edit-proposal', $row['content_id'], $fingerprint);
        $pdo->commit();
    } catch (Throwable $error) {
        $pdo->rollBack();
        throw $error;
    }
}

function collector_admin_route(): never
{
    if (!admin_session()) {
        header('Location: /admin', true, 303);
        exit;
    }
    $pdo = db();
    if (isset($_GET['image'])) {
        $q = $pdo->prepare('SELECT image_sha256 FROM collector_proposals WHERE id = ?');
        $q->execute([(int) $_GET['image']]);
        $sha = $q->fetchColumn();
        if (!$sha || !is_file(collector_image_path($sha)) || !hash_equals($sha, hash_file('sha256', collector_image_path($sha)))) {
            http_response_code(404);
            exit;
        }
        header('Content-Type: image/webp');
        header('Cache-Control: private, no-store');
        header('X-Content-Type-Options: nosniff');
        readfile(collector_image_path($sha));
        exit;
    }
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if (($_SERVER['HTTP_ORIGIN'] ?? '') !== rtrim(config()['base_url'], '/')
            || !is_string($_POST['csrf'] ?? null) || !hash_equals($_SESSION['csrf'], $_POST['csrf'])) {
            collector_panel('<p role="alert">Oturum doğrulanamadı.</p>', 403);
        }
        try {
            $action = $_POST['action'] ?? '';
            if (in_array($action, ['publish', 'reject'], true)) {
                collector_review($pdo, is_array($_POST['selected'] ?? null) ? $_POST['selected'] : [], $action, isset($_POST['screenshots']));
            } elseif ($action === 'scan') {
                // Release session lock while the bounded scan is running.
                session_write_close();
                $result = collector_scan($pdo, 'manual');
                collector_panel('<section class="panel"><h2>Tarama sonucu</h2><p>' . h(json_encode($result, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR))
                    . '</p><a class="button" href="/admin/collector">Kuyruğu aç</a></section>');
            } elseif ($action === 'archive') {
                collector_archive($pdo, (string) ($_POST['id'] ?? ''), (string) ($_POST['fingerprint'] ?? ''));
            } elseif ($action === 'verify') {
                collector_verify_in_game($pdo, (string) ($_POST['id'] ?? ''), (string) ($_POST['fingerprint'] ?? ''), isset($_POST['confirmed']));
            } elseif ($action === 'edit') {
                collector_edit_proposal($pdo, (int) ($_POST['id'] ?? 0), (string) ($_POST['fingerprint'] ?? ''), (string) ($_POST['title'] ?? ''), (string) ($_POST['description'] ?? ''));
            } elseif ($action === 'settings') {
                $hour = filter_var($_POST['hour'] ?? null, FILTER_VALIDATE_INT);
                $max = filter_var($_POST['max'] ?? null, FILTER_VALIDATE_INT);
                if ($hour === false || $hour < 0 || $hour > 23 || $max === false || $max < 1 || $max > 20) {
                    throw new InvalidArgumentException('Saat 0–23, kaynak başına kayıt bütçesi 1–20 olmalı.');
                }
                $settings = ['automatic' => isset($_POST['automatic']), 'hour' => $hour, 'maxPerSource' => $max, 'sources' => []];
                foreach (collector_sources() as $source => $spec) {
                    $settings['sources'][$source] = isset($_POST['sources'][$source]);
                }
                $q = $pdo->prepare('UPDATE collector_settings SET document = ?, revision = revision + 1 WHERE id = 1 AND revision = ?');
                $q->execute([json_encode($settings, JSON_THROW_ON_ERROR), (int) ($_POST['revision'] ?? -1)]);
                if ($q->rowCount() !== 1) {
                    throw new RuntimeException('Ayarlar değişti. Sayfayı yenile.');
                }
            } else {
                throw new InvalidArgumentException('İşlem geçersiz.');
            }
            header('Location: /admin/collector?done=1', true, 303);
            exit;
        } catch (Throwable $error) {
            error_log('Collector admin operation failed: ' . get_class($error));
            $message = $error instanceof PDOException ? 'Veritabanı işlemi tamamlanamadı. Migration ve hata kaydını kontrol et.' : $error->getMessage();
            collector_panel('<section class="panel"><p role="alert">' . h($message) . '</p><a class="button" href="/admin/collector">Geri dön</a></section>', 409);
        }
    }
    if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
        header('Allow: GET, POST');
        collector_panel('<p>Yöntem geçersiz.</p>', 405);
    }
    try {
        $state = collector_settings($pdo);
    } catch (Throwable) {
        collector_panel('<section class="panel"><p>Otomatik köy toplama tabloları hazır değil. Kurulum rehberindeki ek migration dosyasını mevcut veritabanında çalıştır.</p></section>', 503);
    }
    $layouts = content($pdo, 'layout', null, true);
    $counts = collector_counts($layouts);
    ob_start();
    ?>
    <?php if (isset($_GET['done'])): ?><p class="collector-notice" role="status">İşlem kaydedildi.</p><?php endif; ?>
    <section class="panel"><h2>Aktif düzen sayıları</h2><div class="collector-counts">
      <?php foreach (array_reverse($counts, true) as $th => $count): ?><span class="chip">TH<?= $th ?> · <?= $count ?>/20</span><?php endforeach; ?>
    </div><p>Son günlük keşif <?= h($state['lastDiscovery'] ?? 'Henüz çalışmadı') ?> · Günler UTC üzerinden hesaplanır.</p></section>
    <?php
    $view = $_GET['view'] ?? 'pending';
    if ($view === 'settings'):
        $settings = $state['settings'];
    ?>
    <section class="panel"><h2>Kaynaklar ve günlük tarama</h2>
      <form method="post" class="collector-form"><?= collector_form_token() ?>
        <input type="hidden" name="action" value="settings"><input type="hidden" name="revision" value="<?= $state['revision'] ?>">
        <?php foreach (collector_sources() as $id => $spec): ?>
          <label><input type="checkbox" name="sources[<?= h($id) ?>]" <?= $settings['sources'][$id] ? 'checked' : '' ?>> <?= h($spec['name']) ?> · <?= h($spec['origin']) ?></label>
        <?php endforeach; ?>
        <label><input type="checkbox" name="automatic" <?= $settings['automatic'] ? 'checked' : '' ?>> Günlük otomatik tarama açık</label>
        <label>Tarama saati (UTC)<input type="number" name="hour" value="<?= (int) $settings['hour'] ?>" min="0" max="23" required></label>
        <label>Kaynak başına günlük aday bütçesi<input type="number" name="max" value="<?= (int) $settings['maxPerSource'] ?>" min="1" max="20" required></label>
        <button class="button primary">Ayarları kaydet</button>
      </form>
      <form method="post"><?= collector_form_token() ?><input type="hidden" name="action" value="scan"><button class="button">Manuel tarama başlat</button></form>
      <p>PHP cron kurulumu gerekir. Manuel tarama da günlük keşif sınırını kullanır. Tarama en çok dört dakika sürer.</p>
    </section>
    <?php elseif ($view === 'history'): ?>
      <section class="panel"><h2>Tarama geçmişi</h2>
        <?php foreach ($pdo->query('SELECT * FROM collector_runs ORDER BY id DESC LIMIT 50') as $run): ?>
          <p><strong>#<?= (int) $run['id'] ?> · <?= h($run['status']) ?></strong> <?= h($run['started_at']) ?> — <?= h($run['finished_at'] ?? 'Çalışma kesilmiş olabilir') ?><br><?= h($run['summary']) ?></p>
        <?php endforeach; ?>
      </section>
    <?php elseif ($view === 'errors'): ?>
      <section class="panel"><h2>Kaynak, görsel ve bağlantı hataları</h2>
        <?php foreach ($pdo->query('SELECT * FROM collector_issues ORDER BY id DESC LIMIT 100') as $issue): ?>
          <p><?= h($issue['checked_at']) ?> · <?= h($issue['source_id']) ?> · <?= h($issue['source_key']) ?><br><?= h($issue['message']) ?></p>
        <?php endforeach; ?>
      </section>
    <?php elseif ($view === 'archive'): ?>
      <section class="panel"><h2>Arşivleme önerileri</h2><p>TH sınırına ulaşan veya son kontrolü 90 günden eski düzenler önerilir. Hangi düzenin arşivleneceğini sen seçersin.</p>
      <?php
      usort($layouts, static fn ($a, $b) => strcmp($a['lastCheckedAt'] ?? '', $b['lastCheckedAt'] ?? ''));
      foreach ($layouts as $item):
          if (empty($item['hidden']) && (($counts[$item['th']] ?? 0) >= 20 || strtotime($item['lastCheckedAt'] ?? '1970-01-01') < time() - 90 * 86400)):
      ?>
        <form method="post" class="collector-archive"><?= collector_form_token() ?>
          <a href="<?= h(layout_detail_url($item)) ?>"><?= h($item['title']) ?></a>
          <input type="hidden" name="action" value="archive"><input type="hidden" name="id" value="<?= h($item['id']) ?>">
          <input type="hidden" name="fingerprint" value="<?= h(collector_hash($item)) ?>"><button class="button small">Arşivle</button>
        </form>
      <?php endif; endforeach; ?>
      <details><summary>Arşivlenen kayıtlar</summary>
      <?php foreach ($layouts as $item): if (!empty($item['archived'])): ?>
        <p><?= h($item['title']) ?> · <?= h($item['archivedAt'] ?? '') ?> · <a href="/studio">Studio'da yönet</a></p>
      <?php endif; endforeach; ?></details></section>
    <?php else:
        $status = in_array($view, ['pending', 'duplicate', 'published', 'rejected'], true) ? $view : 'pending';
        $page = max(1, min(100000, (int) ($_GET['page'] ?? 1)));
        $read = $pdo->prepare('SELECT * FROM collector_proposals WHERE status = ? ORDER BY id DESC LIMIT 20 OFFSET ' . (($page - 1) * 20));
        $read->execute([$status]);
        $rows = $read->fetchAll(PDO::FETCH_ASSOC);
    ?>
      <?php if ($status === 'pending'): ?>
        <form id="collector-review" method="post"><?= collector_form_token() ?>
          <label class="collector-confirm"><input type="checkbox" name="screenshots"> Seçtiğim önizlemeleri inceledim, bunlar bağlantılarıyla eşleşen gerçek köy ekran görüntüleri.</label>
          <button class="button primary" name="action" value="publish">Seçilenleri onayla ve yayımla</button>
          <button class="button" name="action" value="reject">Seçilenleri reddet</button>
        </form>
      <?php endif; ?>
      <div class="collector-grid">
      <?php foreach ($rows as $row): $item = json_decode($row['payload'], true, 64, JSON_THROW_ON_ERROR); ?>
        <article class="panel collector-card">
          <?php if ($status === 'pending'): ?><label><input form="collector-review" type="checkbox" name="selected[<?= (int) $row['id'] ?>]" value="<?= h($row['fingerprint']) ?>"> Onay için seç</label><?php endif; ?>
          <a href="?image=<?= (int) $row['id'] ?>" target="_blank" rel="noopener"><img src="?image=<?= (int) $row['id'] ?>" alt="<?= h($item['title']) ?>" loading="lazy" width="800" height="600"></a>
          <h2><?= h($item['title']) ?></h2><p><?= h($item['description']) ?></p>
          <p>TH<?= (int) $item['th'] ?> · <?= h(layout_category_label($item['type'])) ?> · <?= $status === 'published' ? 'Yayımlandı' : 'Doğrulanmadı' ?></p>
          <p><a href="<?= h($item['url']) ?>" target="_blank" rel="noopener noreferrer">Oyunda aç ↗</a> ·
          <a href="<?= h($item['sourceUrl']) ?>" target="_blank" rel="noopener noreferrer">Kaynağı aç ↗</a></p>
          <p>Kaynak başlık <?= h($item['sourceTitle']) ?><br>Kaynak yayın tarihi <?= h($item['sourcePublishedAt'] ?? 'Belirtilmemiş') ?><br>Son kontrol <?= h($row['last_checked']) ?></p>
          <?php if ($row['issue']): ?><p><?= h($row['issue']) ?></p><?php endif; ?>
          <?php if (($counts[$item['th']] ?? 0) >= 20 && $status === 'pending'): ?><p class="collector-notice">TH<?= (int) $item['th'] ?> dolu. <a href="?view=archive">Arşivleme önerilerini aç</a>.</p><?php endif; ?>
          <?php if ($status === 'pending'): ?>
          <details><summary>İngilizce başlık ve açıklamayı düzenle</summary>
            <form method="post" class="collector-form"><?= collector_form_token() ?><input type="hidden" name="action" value="edit">
              <input type="hidden" name="id" value="<?= (int) $row['id'] ?>"><input type="hidden" name="fingerprint" value="<?= h($row['fingerprint']) ?>">
              <label>Başlık<input name="title" maxlength="70" value="<?= h($item['title']) ?>" required></label>
              <label>Açıklama<textarea name="description" maxlength="1200" rows="7" required><?= h($item['description']) ?></textarea></label>
              <button class="button">Yeni sürümü kaydet</button>
            </form>
          </details>
          <?php elseif ($status === 'published'):
              $active = array_column($layouts, null, 'id')[$item['id']] ?? null;
              if ($active):
          ?>
            <a class="button small" href="<?= h(layout_detail_url($active)) ?>">Detay sayfasını aç</a>
            <form method="post" class="collector-form"><?= collector_form_token() ?>
              <input type="hidden" name="action" value="verify"><input type="hidden" name="id" value="<?= h($active['id']) ?>">
              <input type="hidden" name="fingerprint" value="<?= h(collector_hash($active)) ?>">
              <label><input type="checkbox" name="confirmed" required> Bu bağlantıyı oyunda içe aktardım ve düzen görselle eşleşiyor.</label>
              <button class="button small">Oyun içi kontrol tarihini kaydet</button>
            </form>
          <?php endif; endif; ?>
        </article>
      <?php endforeach; ?>
      </div>
      <?php if (!$rows): ?><section class="panel"><p>Bu görünümde kayıt yok.</p></section><?php endif; ?>
      <nav class="collector-tabs" aria-label="Kuyruk sayfaları">
        <?php if ($page > 1): ?><a class="button" href="?view=<?= h($status) ?>&amp;page=<?= $page - 1 ?>">Önceki</a><?php endif; ?>
        <?php if (count($rows) === 20): ?><a class="button" href="?view=<?= h($status) ?>&amp;page=<?= $page + 1 ?>">Sonraki</a><?php endif; ?>
      </nav>
    <?php endif;
    collector_panel(ob_get_clean());
}
