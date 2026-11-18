<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    exit(1);
}
require __DIR__ . '/content.php';
require __DIR__ . '/stats.php';
require __DIR__ . '/reviews.php';
require __DIR__ . '/cms.php';
require __DIR__ . '/layout-detail.php';
require __DIR__ . '/public-english.php';

function get_https(string $url, int $limit): string
{
    $body = '';
    $curl = curl_init($url);
    curl_setopt_array($curl, [
        CURLOPT_PROTOCOLS => CURLPROTO_HTTPS, CURLOPT_FOLLOWLOCATION => false,
        CURLOPT_CONNECTTIMEOUT => 8, CURLOPT_TIMEOUT => 20,
        CURLOPT_WRITEFUNCTION => function ($handle, string $chunk) use (&$body, $limit): int {
            if (strlen($body) + strlen($chunk) > $limit) {
                return 0;
            }
            $body .= $chunk;
            return strlen($chunk);
        },
    ]);
    $result = curl_exec($curl);
    $status = curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
    if ($result === false || $status !== 200) {
        throw new RuntimeException('HTTPS verification failed (' . $status . ')');
    }
    return $body;
}

function publish_image(object $item, string $file): string
{
    $bytes = is_file($file) ? file_get_contents($file, false, null, 0, 10000001) : false;
    if (!$bytes || strlen($bytes) > 10000000 || !hash_equals($item->imageSha256 ?? '', hash('sha256', $bytes))) {
        throw new RuntimeException('Preview bytes do not match the reviewed fingerprint');
    }
    $ext = match ((new finfo(FILEINFO_MIME_TYPE))->buffer($bytes)) {
        'image/webp' => 'webp', 'image/jpeg' => 'jpg', 'image/png' => 'png',
        default => throw new RuntimeException('Preview format not supported'),
    };
    $relative = 'assets/layouts/' . $item->payload->id . '-' . $item->imageSha256 . '.' . $ext;
    $target = rtrim(config()['document_root'], '/') . '/' . $relative;
    if (!is_file($target)) {
        $temp = tempnam(dirname($target), '.approved-');
        if ($temp === false) {
            throw new RuntimeException('Cannot stage preview file');
        }
        try {
            if (file_put_contents($temp, $bytes) !== strlen($bytes) || !chmod($temp, 0644) || !rename($temp, $target)) {
                throw new RuntimeException('Cannot publish preview file');
            }
        } finally {
            if (is_file($temp)) {
                unlink($temp);
            }
        }
    }
    if (!hash_equals($item->imageSha256, hash_file('sha256', $target))) {
        throw new RuntimeException('Existing preview differs from reviewed bytes');
    }
    return config()['base_url'] . $relative;
}

$id = $argv[1] ?? null;
if (!content_id($id) || $argc > 3) {
    fwrite(STDERR, "Usage: php publish-approved.php PROPOSAL_ID [VERIFIED_LOCAL_IMAGE]\n");
    exit(1);
}
$pdo = null;
try {
    $proposal = pending_inbox()[$id] ?? null;
    if (!$proposal || was_published($proposal)) {
        throw new RuntimeException('Proposal missing or already published before migration');
    }
    $kind = $proposal->kind;
    $item = clone $proposal->payload;
    $item->hidden = false;
    check_record($item, $kind);
    $pdo = db();
    $pdo->beginTransaction();
    $doc = cms_state($pdo, true)['document'];
    $decision = $pdo->prepare('SELECT fingerprint, decision FROM content_reviews WHERE proposal_id = ? FOR UPDATE');
    $decision->execute([$id]);
    $row = $decision->fetch(PDO::FETCH_ASSOC);
    $current = pending_inbox()[$id] ?? null;
    if (!$row || $row['decision'] !== 'approved' || !hash_equals($proposal->fingerprint, $row['fingerprint']) ||
        !$current || was_published($current) || !hash_equals($current->fingerprint, $proposal->fingerprint)) {
        throw new RuntimeException('Exact version has no active approval');
    }
    $key = $kind . 'Overrides';
    if (isset($doc->$key->{$item->id})) {
        throw new RuntimeException('The ID has a manual editorial override');
    }
    $get = $pdo->prepare('SELECT sort_order, payload FROM public_content WHERE kind = ? AND id = ? FOR UPDATE');
    $get->execute([$kind, $item->id]);
    $existing = $get->fetch(PDO::FETCH_ASSOC);
    $alreadyWritten = $existing && (json_decode($existing['payload'], true, 64, JSON_THROW_ON_ERROR)['approvalFingerprint'] ?? null) === $proposal->fingerprint;
    $receipt = $pdo->prepare('SELECT 1 FROM content_publications WHERE proposal_id = ? AND fingerprint = ?');
    $receipt->execute([$id, $proposal->fingerprint]);
    if ($receipt->fetchColumn()) {
        $pdo->rollBack();
        echo "Already verified and published.\n";
        exit;
    }
    $item->approvalFingerprint = $proposal->fingerprint;
    if ($kind === 'layout') {
        if ($existing && !$alreadyWritten) {
            throw new RuntimeException('Existing layout ID cannot be replaced by unattended publication');
        }
        get_https($item->url, 1000000);
        $item->image = publish_image($proposal, $argv[2] ?? '');
    } elseif (!empty($item->armyUrl)) {
        get_https($item->armyUrl, 1000000);
    }
    if (!$alreadyWritten) {
        if (!$existing && $kind === 'guide' && count(content($pdo, $kind, $doc, true)) >= 99) {
            throw new RuntimeException('Catalog publication limit reached');
        }
        if ($existing) {
            $write = $pdo->prepare('UPDATE public_content SET payload = ? WHERE kind = ? AND id = ?');
            $write->execute([json_encode($item, JSON_THROW_ON_ERROR), $kind, $item->id]);
        } else {
            $position = $pdo->prepare('SELECT COALESCE(MAX(sort_order), 0) + 1 FROM public_content WHERE kind = ?');
            $position->execute([$kind]);
            $write = $pdo->prepare('INSERT INTO public_content (kind, id, sort_order, payload) VALUES (?, ?, ?, ?)');
            $write->execute([$kind, $item->id, (int) $position->fetchColumn(), json_encode($item, JSON_THROW_ON_ERROR)]);
        }
        check_collection($pdo, $doc);
        if ($kind === 'layout') {
            // The legacy approval publisher must obey the same TH cap.
            $counts = collector_counts(content($pdo, 'layout', $doc));
            if (($counts[$item->th] ?? 0) > 20) {
                throw new RuntimeException('Town Hall active-layout limit reached');
            }
        }
    }
    $pdo->commit();

    $live = json_decode(get_https(config()['base_url'] . 'api/content', 4000000), true, 64, JSON_THROW_ON_ERROR);
    $matches = array_filter($live[$kind === 'layout' ? 'layouts' : 'guides'] ?? [], fn ($value) =>
        $value['id'] === $item->id && ($value['approvalFingerprint'] ?? '') === $proposal->fingerprint);
    $gallery = $kind === 'layout' ? 'bases' : 'attacks';
    $page = get_https(config()['base_url'] . $gallery, 4000000);
    $title = $kind === 'layout' ? public_english_layout((array) $item)['title'] : $item->title->en;
    if (count($matches) !== 1 || !str_contains($page, h($title)) ||
        ($kind === 'layout' && (!str_contains($page, h($item->image)) ||
            !hash_equals($proposal->imageSha256, hash('sha256', get_https($item->image, 10000000)))))) {
        throw new RuntimeException('Live verification failed; publication receipt not written');
    }
    $record = $pdo->prepare('INSERT INTO content_publications (proposal_id, fingerprint, published_at) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE published_at = published_at');
    $record->execute([$id, $proposal->fingerprint, gmdate('Y-m-d\TH:i:s\Z')]);
    echo "Published and verified: $id\n";
} catch (Throwable $error) {
    if ($pdo && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    $message = $error instanceof PDOException ? 'Database operation failed' : $error->getMessage();
    fwrite(STDERR, "Publication incomplete: $message\n");
    exit(1);
}
