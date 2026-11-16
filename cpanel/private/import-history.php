<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    exit(1);
}
require __DIR__ . '/content.php';
require __DIR__ . '/stats.php';
require __DIR__ . '/cms.php';
if ($argc !== 2) {
    fwrite(STDERR, "Usage: php import-history.php private-d1-export.json\n");
    exit(1);
}

$columns = [
    'layout_events' => ['layout_id', 'visitor_hash', 'kind', 'bucket', 'created_at'],
    'layout_favorites' => ['layout_id', 'visitor_hash', 'active', 'updated_at'],
    'content_reviews' => ['proposal_id', 'fingerprint', 'decision', 'reviewer_id', 'reviewed_at'],
];
function valid_history_value(string $key, mixed $value): bool
{
    return match ($key) {
        'layout_id', 'proposal_id', 'reviewer_id' => content_id($value),
        'visitor_hash', 'fingerprint' => is_string($value) && preg_match('/^[a-f0-9]{64}$/D', $value) === 1,
        'kind' => in_array($value, ['view', 'copy'], true),
        'bucket' => is_string($value) && preg_match('/^(?:\d{1,20}|\d{4}-\d{2}-\d{2})$/D', $value) === 1,
        'created_at', 'updated_at' => is_int($value) && $value >= 0,
        'active' => in_array($value, [0, 1], true),
        'decision' => in_array($value, ['approved', 'rejected'], true),
        'reviewed_at' => is_string($value) && preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}(?:\.\d{3})?Z$/D', $value) === 1,
        default => false,
    };
}

$pdo = null;
try {
    $raw = file_get_contents($argv[1]);
    if ($raw === false) {
        throw new RuntimeException('Export unavailable');
    }
    $data = json_decode($raw, true, 32, JSON_THROW_ON_ERROR);
    $pdo = db();
    $pdo->beginTransaction();
    $current = cms_state($pdo, true);
    $adminRows = $data['admin_content'] ?? null;
    if (!is_array($adminRows) || !array_is_list($adminRows) || count($adminRows) > 1 || $current['revision'] !== 0) {
        throw new RuntimeException('Missing source CMS table or target already configured');
    }
    foreach ($adminRows as $admin) {
        $document = json_decode($admin['document'], false, 64, JSON_THROW_ON_ERROR);
        if ($admin['id'] !== 1 || !is_int($admin['revision']) || $admin['revision'] < 1 ||
            !valid_history_value('reviewed_at', $admin['updated_at'])) {
            throw new RuntimeException('Invalid CMS revision');
        }
        check_document($document);
        check_collection($pdo, $document);
        $save = $pdo->prepare('UPDATE admin_content SET revision = ?, document = ?, updated_at = ? WHERE id = 1');
        $save->execute([$admin['revision'], $admin['document'], $admin['updated_at']]);
    }
    foreach ($columns as $table => $fields) {
        $rows = $data[$table] ?? null;
        if (!is_array($rows) || !array_is_list($rows) ||
            (int) $pdo->query("SELECT COUNT(*) FROM $table")->fetchColumn() !== 0) {
            throw new RuntimeException('Missing export or destination is not empty');
        }
        $placeholders = implode(',', array_fill(0, count($fields), '?'));
        $names = implode(',', $fields);
        $insert = $pdo->prepare("INSERT INTO $table ($names) VALUES ($placeholders)");
        foreach ($rows as $row) {
            $values = [];
            foreach ($fields as $key) {
                $value = $row[$key] ?? null;
                if (!valid_history_value($key, $value)) {
                    throw new RuntimeException('Invalid exported field');
                }
                $values[] = $value;
            }
            $insert->execute($values);
        }
        if ((int) $pdo->query("SELECT COUNT(*) FROM $table")->fetchColumn() !== count($rows)) {
            throw new RuntimeException('Imported row count differs from source');
        }
    }
    $pdo->commit();
} catch (Throwable $error) {
    if ($pdo && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    fwrite(STDERR, "History import failed. No rows committed.\n");
    exit(1);
}
foreach ($columns as $table => $_) {
    echo $table . ': ' . count($data[$table]) . "\n";
}
