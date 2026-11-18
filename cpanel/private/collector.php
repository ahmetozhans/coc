<?php
declare(strict_types=1);
require_once __DIR__ . '/collector-sources.php';

function collector_now(): string
{
    return gmdate('Y-m-d\TH:i:s\Z');
}

function collector_hash(mixed $value): string
{
    return hash('sha256', canonical_json(json_decode(json_encode($value, JSON_THROW_ON_ERROR), false, 64, JSON_THROW_ON_ERROR)));
}

function collector_settings(PDO $pdo, bool $lock = false): array
{
    $row = $pdo->query('SELECT * FROM collector_settings WHERE id = 1' . ($lock ? ' FOR UPDATE' : ''))->fetch(PDO::FETCH_ASSOC);
    if (!$row) {
        throw new RuntimeException('Köy toplama migration dosyası kurulmamış.');
    }
    return ['revision' => (int) $row['revision'], 'lastDiscovery' => $row['last_discovery'],
        'settings' => json_decode($row['document'], true, 32, JSON_THROW_ON_ERROR)];
}

function collector_counts(array $layouts): array
{
    $counts = array_fill(3, 17, 0);
    foreach ($layouts as $row) {
        if (empty($row['hidden']) && empty($row['archived']) && isset($counts[$row['th']])) {
            $counts[$row['th']]++;
        }
    }
    return $counts;
}

function collector_check_capacity(array $before, array $after): void
{
    $prior = collector_counts($before);
    foreach (collector_counts($after) as $th => $count) {
        if ($count > 20 && $count > $prior[$th]) {
            throw new InvalidArgumentException("TH$th için 20 aktif düzen sınırı dolu. Önce arşivlenecek düzeni seç.");
        }
    }
}

function collector_image_path(string $sha): string
{
    if (!preg_match('/^[a-f0-9]{64}$/D', $sha)) {
        throw new RuntimeException('Görsel parmak izi geçersiz.');
    }
    return __DIR__ . '/collector-images/' . $sha . '.webp';
}

function collector_store_image(array $image): void
{
    $target = collector_image_path($image['sha256']);
    if (!is_dir(dirname($target)) && !mkdir(dirname($target), 0700, true) && !is_dir(dirname($target))) {
        throw new RuntimeException('Özel görsel klasörü oluşturulamadı.');
    }
    if (is_file($target)) {
        if (!hash_equals($image['sha256'], hash_file('sha256', $target))) {
            throw new RuntimeException('Kayıtlı görselin parmak izi değişmiş.');
        }
        return;
    }
    $temp = tempnam(dirname($target), '.scan-');
    if ($temp === false) {
        throw new RuntimeException('Görsel hazırlanamadı.');
    }
    try {
        if (file_put_contents($temp, $image['bytes']) !== strlen($image['bytes'])
            || !chmod($temp, 0600) || !rename($temp, $target)) {
            throw new RuntimeException('Görsel kaydedilemedi.');
        }
    } finally {
        if (is_file($temp)) {
            unlink($temp);
        }
    }
}

function collector_fingerprint(array $payload, string $sha): string
{
    return collector_hash(['kind' => 'layout', 'payload' => $payload, 'imageSha256' => $sha]);
}

function collector_editorial_hash(array $layout): string
{
    unset($layout['lastCheckedAt']);
    return collector_hash($layout);
}

function collector_editorial(array $record): array
{
    $label = strtolower($record['type']);
    $short = substr(hash('sha256', $record['gameId']), 0, 6);
    $feature = $record['sourceFeatures'][0] ?? $short;
    $title = "TH{$record['th']} " . $record['type'] . " Base Layout · $feature";
    $featureNote = empty($record['sourceFeatures']) ? ''
        : 'Source-listed features include ' . strtolower(implode(', ', $record['sourceFeatures'])) . '. Compare these labels with the preview and your own defensive replays. ';
    $goals = [
        'War' => 'During war preparation, compare entry directions, routes to the Town Hall and the distance between key defenses.',
        'Trophy' => 'For trophy defense, check the destruction available from outside buildings and compare air and ground approaches to the Town Hall.',
        'Farming' => 'When protecting resources, inspect storage accessibility and routes that could expose several storages to the same attack.',
        'Hybrid' => 'Balance your trophy and resource priorities. Examine routes to the Town Hall and resource storages separately.',
    ];
    return [$title, "Explore this TH{$record['th']} $label layout in the full-size preview and use the official game link to import it into your village. "
        . $featureNote . $goals[$record['type']] . " Check building levels and trap positions against your own village before using it. Test different approaches in friendly challenges and review the defensive replays."];
}

function collector_issue(PDO $pdo, int $run, string $source, string $key, string $kind, string $message): void
{
    $write = $pdo->prepare('INSERT INTO collector_issues (run_id, source_id, source_key, kind, message, checked_at) VALUES (?, ?, ?, ?, ?, ?)');
    $write->execute([$run, $source, substr($key, 0, 160), $kind, substr($message, 0, 500), collector_now()]);
}

function collector_queue(PDO $pdo, array $record, string $source, array $image): string
{
    $hash = collector_hash($record + ['sourceImageSha256' => $image['sourceSha256']]);
    $existing = $pdo->prepare('SELECT id FROM collector_proposals WHERE source_id = ? AND source_key = ? AND source_hash = ?');
    $existing->execute([$source, $record['key'], $hash]);
    $id = $existing->fetchColumn();
    if ($id) {
        $pdo->prepare('UPDATE collector_proposals SET last_checked = ? WHERE id = ?')->execute([collector_now(), $id]);
        return 'unchanged';
    }
    $pdo->beginTransaction();
    try {
        $doc = cms_state($pdo, true)['document'];
        $layouts = content($pdo, 'layout', $doc, true);
        $contentId = 'auto-' . substr(hash('sha256', $source . ':' . $record['key']), 0, 24);
        $target = null;
        $duplicate = null;
        foreach ($layouts as $layout) {
            if ($layout['id'] === $contentId) {
                $target = $layout;
                continue;
            }
            $layout += collector_local_signature($layout);
            if (collector_safe_game_id($layout) === $record['gameId']
                || ($layout['imageSha256'] ?? '') === $image['sha256']
                || (isset($layout['imageDhash']) && collector_similar($layout['imageDhash'], $image['dhash']))) {
                $duplicate = $layout['id'];
            }
        }
        $get = $pdo->query("SELECT content_id, game_id, image_sha256, image_dhash FROM collector_proposals WHERE status IN ('pending', 'published', 'rejected')");
        foreach ($get as $row) {
            if ($row['content_id'] !== $contentId && ($row['game_id'] === $record['gameId']
                || $row['image_sha256'] === $image['sha256'] || collector_similar($row['image_dhash'], $image['dhash']))) {
                $duplicate = $row['content_id'];
            }
        }
        [$title, $description] = collector_editorial($record);
        $payload = [
            'id' => $contentId, 'th' => $record['th'], 'type' => $record['type'],
            'title' => $title, 'description' => $description, 'hidden' => false,
            'image' => config()['base_url'] . 'assets/layouts/col-' . $image['sha256'] . '.webp',
            'url' => $record['url'], 'sourceUrl' => $record['sourceUrl'], 'sourceSite' => $source,
            'sourceTitle' => $record['sourceTitle'], 'sourcePublishedAt' => $record['sourcePublishedAt'],
            'sourceFeatures' => $record['sourceFeatures'] ?? [],
            'sourceUpdatedAt' => $record['sourceUpdatedAt'], 'imageSha256' => $image['sha256'],
            'imageDhash' => $image['dhash'], 'sourceImageSha256' => $image['sourceSha256'],
            'inGameVerifiedAt' => null,
        ];
        $fingerprint = collector_fingerprint($payload, $image['sha256']);
        $now = collector_now();
        $insert = $pdo->prepare('INSERT INTO collector_proposals (source_id, source_key, content_id, fingerprint, game_id, image_sha256, image_dhash, source_hash, payload, baseline_hash, status, issue, first_seen, last_checked) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
        $insert->execute([$source, $record['key'], $contentId, $fingerprint, $record['gameId'], $image['sha256'],
            $image['dhash'], $hash, json_encode($payload, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE),
            $target ? collector_editorial_hash($target) : null, $duplicate ? 'duplicate' : 'pending',
            $duplicate ? 'Benzer veya aynı kayıt: ' . $duplicate : null, $now, $now]);
        $newId = (int) $pdo->lastInsertId();
        $pdo->prepare("UPDATE collector_proposals SET status = 'superseded', issue = ? WHERE content_id = ? AND id <> ? AND status = 'pending'")
            ->execute(['Kaynağın daha yeni bir sürümü bulundu.', $contentId, $newId]);
        // Every changed version is a new proposal and requires a fresh exact-version review.
        $pdo->commit();
        return $duplicate ? 'duplicate' : 'pending';
    } catch (Throwable $error) {
        $pdo->rollBack();
        throw $error;
    }
}

function collector_safe_game_id(array $layout): string
{
    try {
        return collector_game_id($layout['url'] ?? '', (int) ($layout['th'] ?? 0));
    } catch (RuntimeException) {
        return '';
    }
}

function collector_check_published(PDO $pdo, int $run): int
{
    $errors = 0;
    foreach (content($pdo, 'layout') as $layout) {
        try {
            collector_game_id($layout['url'] ?? '', (int) $layout['th']);
            $signature = collector_local_signature($layout);
            if (!$signature || (isset($layout['imageSha256']) && !hash_equals($layout['imageSha256'], $signature['fileSha256']))) {
                throw new RuntimeException('Yayındaki yerel görsel eksik, bozuk veya değişmiş.');
            }
            // Keep editorial fields and approvals intact while recording a format/image check.
            $pdo->beginTransaction();
            $doc = cms_state($pdo, true)['document'];
            if (!isset($doc->layoutOverrides->{$layout['id']})) {
                $get = $pdo->prepare("SELECT payload FROM public_content WHERE kind = 'layout' AND id = ? FOR UPDATE");
                $get->execute([$layout['id']]);
                $raw = $get->fetchColumn();
                if ($raw !== false) {
                    $payload = json_decode($raw, true, 64, JSON_THROW_ON_ERROR);
                    $payload['lastCheckedAt'] = collector_now();
                    $pdo->prepare("UPDATE public_content SET payload = ? WHERE kind = 'layout' AND id = ?")
                        ->execute([json_encode($payload, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE), $layout['id']]);
                }
            }
            $pdo->commit();
        } catch (Throwable $error) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $errors++;
            collector_issue($pdo, $run, 'local', $layout['id'], 'published-image-link', $error->getMessage());
        }
    }
    return $errors;
}

function collector_local_signature(array $layout): array
{
    static $cache = [];
    $url = $layout['image'] ?? '';
    if (isset($cache[$url])) {
        return $cache[$url];
    }
    $path = parse_url($url, PHP_URL_PATH) ?: '';
    if (!str_starts_with($url, config()['base_url'] . 'assets/layouts/')
        || !preg_match('~^/assets/layouts/[A-Za-z0-9_.-]+$~D', $path)) {
        return [];
    }
    $file = rtrim(config()['document_root'], '/') . $path;
    if (!is_file($file) || filesize($file) > 10000000) {
        return [];
    }
    try {
        $image = collector_image(file_get_contents($file));
        return $cache[$url] = ['imageSha256' => $image['sha256'], 'imageDhash' => $image['dhash'],
            'fileSha256' => hash_file('sha256', $file)];
    } catch (RuntimeException) {
        return [];
    }
}

function collector_scan(PDO $pdo, string $trigger = 'cron', ?CollectorHttp $http = null): array
{
    $lock = $pdo->query("SELECT GET_LOCK('mahanpro-layout-collector', 0)")->fetchColumn();
    if ((int) $lock !== 1) {
        throw new RuntimeException('Başka bir tarama devam ediyor.');
    }
    $run = null;
    $summary = ['pending' => 0, 'duplicate' => 0, 'unchanged' => 0, 'errors' => 0];
    try {
        $pdo->beginTransaction();
        $state = collector_settings($pdo, true);
        $settings = $state['settings'];
        if ($state['lastDiscovery'] === gmdate('Y-m-d') || ($trigger === 'cron'
            && (!$settings['automatic'] || (int) gmdate('G') < $settings['hour']))) {
            $pdo->rollBack();
            return ['skipped' => true, 'message' => 'Bugün kontrol edildi veya otomatik tarama saati gelmedi.'];
        }
        // Claim the UTC day before network I/O, including a crashed process.
        $pdo->prepare('UPDATE collector_settings SET last_discovery = ? WHERE id = 1')->execute([gmdate('Y-m-d')]);
        $pdo->prepare('INSERT INTO collector_runs (started_at, status, trigger_kind, summary) VALUES (?, ?, ?, ?)')->execute([collector_now(), 'running', $trigger, '{}']);
        $run = (int) $pdo->lastInsertId();
        $pdo->commit();
        $http ??= new CollectorHttp();
        $started = microtime(true);
        $summary['errors'] += collector_check_published($pdo, $run);
        $coverage = collector_counts(content($pdo, 'layout'));
        foreach ($pdo->query("SELECT payload FROM collector_proposals WHERE status = 'pending'") as $pending) {
            $value = json_decode($pending['payload'], true, 64, JSON_THROW_ON_ERROR);
            $coverage[$value['th']]++;
        }
        $remaining = array_fill_keys(array_keys(collector_sources()), $settings['maxPerSource']);
        // Rotate sources within each TH priority, so TH19 is checked before TH18.
        foreach (range(19, 3) as $th) {
            foreach (collector_sources() as $source => $spec) {
                if (($coverage[$th] ?? 0) >= 20 && (int) gmdate('w') !== 0) {
                    continue;
                }
                if (!$settings['sources'][$source] || $remaining[$source] <= 0) {
                    continue;
                }
                if (microtime(true) - $started > 220) {
                    collector_issue($pdo, $run, $source, '', 'timeout', 'Tarama süre sınırında durdu. Kalan kaynaklar sonraki günlük çalışmada denenecek.');
                    $summary['errors']++;
                    break 2;
                }
                try {
                    $html = $http->get($spec['origin'] . sprintf($spec['path'], $th), [parse_url($spec['origin'], PHP_URL_HOST)]);
                    $rows = collector_rows($html, $source);
                    if (!$rows) {
                        continue;
                    }
                    $processed = 0;
                    foreach ($rows as $row) {
                        if ($remaining[$source] <= 0 || $processed >= 5) {
                            break;
                        }
                        try {
                            $record = collector_normalize($row, $source, $html);
                            if (!$record || $record['th'] !== $th) {
                                continue;
                            }
                            $remaining[$source]--;
                            $processed++;
                            $bytes = $http->get($record['imageUrl'], $spec['images'], 10000000);
                            $image = collector_image($bytes);
                            collector_store_image($image);
                            $result = collector_queue($pdo, $record, $source, $image);
                            $summary[$result]++;
                        } catch (Throwable $error) {
                            $summary['errors']++;
                            collector_issue($pdo, $run, $source, (string) ($row['id'] ?? ''), 'record', $error->getMessage());
                        }
                    }
                } catch (Throwable $error) {
                    $remaining[$source] = 0;
                    $summary['errors']++;
                    collector_issue($pdo, $run, $source, '', 'source', $error->getMessage());
                }
            }
        }
        $pdo->prepare('UPDATE collector_runs SET finished_at = ?, status = ?, summary = ? WHERE id = ?')->execute([
            collector_now(), $summary['errors'] ? 'partial' : 'completed', json_encode($summary, JSON_THROW_ON_ERROR), $run]);
        return ['run' => $run] + $summary;
    } catch (Throwable $error) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        if ($run !== null) {
            $pdo->prepare('UPDATE collector_runs SET finished_at = ?, status = ?, summary = ? WHERE id = ?')->execute([
                collector_now(), 'failed', json_encode(['error' => 'Çalışma tamamlanamadı.']), $run]);
        }
        throw $error;
    } finally {
        $pdo->query("SELECT RELEASE_LOCK('mahanpro-layout-collector')");
    }
}

function collector_audit(PDO $pdo, string $action, string $id, string $fingerprint, array $details = []): void
{
    $write = $pdo->prepare('INSERT INTO collector_audit (action, content_id, fingerprint, actor, created_at, details) VALUES (?, ?, ?, ?, ?, ?)');
    $write->execute([$action, $id, $fingerprint, 'cpanel-admin', collector_now(), json_encode($details, JSON_THROW_ON_ERROR)]);
}

function collector_publish_asset(array $payload): void
{
    $sha = $payload['imageSha256'];
    $source = collector_image_path($sha);
    if (!is_file($source) || !hash_equals($sha, hash_file('sha256', $source))) {
        throw new RuntimeException('Onaylanan görselin parmak izi değişmiş veya dosya eksik.');
    }
    $folder = rtrim(config()['document_root'], '/') . '/assets/layouts';
    if (!is_dir($folder) && !mkdir($folder, 0755, true) && !is_dir($folder)) {
        throw new RuntimeException('Yayın görsel klasörü açılamadı.');
    }
    $target = $folder . '/col-' . $sha . '.webp';
    if (!is_file($target)) {
        $temp = tempnam($folder, '.collector-');
        try {
            if (!$temp || !copy($source, $temp) || !chmod($temp, 0644) || !rename($temp, $target)) {
                throw new RuntimeException('Yayın görseli yazılamadı.');
            }
        } finally {
            if ($temp && is_file($temp)) {
                unlink($temp);
            }
        }
    }
    if (!hash_equals($sha, hash_file('sha256', $target))) {
        throw new RuntimeException('Yayın görseli parmak izi uyuşmuyor.');
    }
}

function collector_review(PDO $pdo, array $selected, string $action, bool $screenshotsConfirmed): void
{
    if (!$selected || count($selected) > 20 || !in_array($action, ['publish', 'reject'], true)) {
        throw new InvalidArgumentException('1–20 öneri seç.');
    }
    if ($action === 'publish' && !$screenshotsConfirmed) {
        throw new InvalidArgumentException('Önizlemelerin gerçek köy ekran görüntüleri olduğunu inceleyerek onayla.');
    }
    $pdo->beginTransaction();
    try {
        $state = cms_state($pdo, true);
        $doc = $state['document'];
        $before = content($pdo, 'layout', $doc, true);
        $layouts = array_column($before, null, 'id');
        foreach ($selected as $id => $fingerprint) {
            $read = $pdo->prepare('SELECT * FROM collector_proposals WHERE id = ? FOR UPDATE');
            $read->execute([(int) $id]);
            $row = $read->fetch(PDO::FETCH_ASSOC);
            if (!$row || $row['status'] !== 'pending' || !is_string($fingerprint)
                || !hash_equals($row['fingerprint'], $fingerprint)) {
                throw new RuntimeException('Öneri değişti veya işlendi. Paneli yenile.');
            }
            $payload = json_decode($row['payload'], true, 64, JSON_THROW_ON_ERROR);
            if (!hash_equals($fingerprint, collector_fingerprint($payload, $row['image_sha256']))) {
                throw new RuntimeException('İçerik parmak izi eşleşmiyor.');
            }
            if ($action === 'publish') {
                $target = $layouts[$payload['id']] ?? null;
                if (isset($doc->layoutOverrides->{$payload['id']})
                    || ($target ? collector_editorial_hash($target) : null) !== $row['baseline_hash']) {
                    throw new RuntimeException('Bu kimlikte yönetici düzenlemesi var. Otomatik güncelleme bekletildi.');
                }
                foreach ($layouts as $layout) {
                    $layout += collector_local_signature($layout);
                    if ($layout['id'] !== $payload['id'] && (collector_safe_game_id($layout) === $row['game_id']
                        || ($layout['imageSha256'] ?? '') === $row['image_sha256']
                        || (isset($layout['imageDhash']) && collector_similar($layout['imageDhash'], $row['image_dhash'])))) {
                        throw new RuntimeException('Aynı veya benzer düzen zaten kayıtlı.');
                    }
                }
                collector_game_id($payload['url'], $payload['th']);
                check_record(json_decode(json_encode($payload)), 'layout');
                collector_publish_asset($payload);
                $payload['approvalFingerprint'] = $fingerprint;
                $payload['lastCheckedAt'] = $row['last_checked'];
                $layouts[$payload['id']] = $payload;
                collector_check_capacity($before, array_values($layouts));
                $position = $pdo->query("SELECT COALESCE(MAX(sort_order), 0) + 1 FROM public_content WHERE kind = 'layout'")->fetchColumn();
                $write = $pdo->prepare("INSERT INTO public_content (kind, id, sort_order, payload) VALUES ('layout', ?, ?, ?) ON DUPLICATE KEY UPDATE payload = VALUES(payload)");
                $write->execute([$payload['id'], (int) $position, json_encode($payload, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE)]);
            }
            $pdo->prepare('UPDATE collector_proposals SET status = ?, reviewed_at = ?, reviewer = ? WHERE id = ?')->execute([
                $action === 'publish' ? 'published' : 'rejected', collector_now(), 'cpanel-admin', $id]);
            collector_audit($pdo, $action, $payload['id'], $fingerprint);
        }
        // A version change lets already-open Studio forms detect the publication.
        $pdo->prepare('UPDATE admin_content SET revision = revision + 1, updated_at = ? WHERE id = 1')->execute([collector_now()]);
        $pdo->commit();
    } catch (Throwable $error) {
        $pdo->rollBack();
        throw $error;
    }
}

function collector_archive(PDO $pdo, string $id, string $expected): void
{
    $pdo->beginTransaction();
    try {
        $state = cms_state($pdo, true);
        $doc = $state['document'];
        $rows = array_column(content($pdo, 'layout', $doc, true), null, 'id');
        $item = $rows[$id] ?? null;
        if (!$item || !hash_equals(collector_hash($item), $expected)) {
            throw new RuntimeException('Düzen değişti. Paneli yenile.');
        }
        $item['hidden'] = true;
        $item['archived'] = true;
        $item['archivedAt'] = collector_now();
        $doc->layoutOverrides->$id = json_decode(json_encode($item));
        $pdo->prepare('UPDATE admin_content SET revision = revision + 1, document = ?, updated_at = ? WHERE id = 1')->execute([
            json_encode($doc, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE), collector_now()]);
        collector_audit($pdo, 'archive', $id, $expected);
        $pdo->commit();
    } catch (Throwable $error) {
        $pdo->rollBack();
        throw $error;
    }
}
