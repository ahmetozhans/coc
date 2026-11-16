<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    exit(1);
}
require __DIR__ . '/content.php';
if ($argc !== 3) {
    fwrite(STDERR, "Usage: php seed.php catalog.json guides.json\n");
    exit(1);
}

$pdo = db();
$pdo->beginTransaction();
try {
    $insert = $pdo->prepare('INSERT INTO public_content (kind, id, sort_order, payload) VALUES (?, ?, ?, ?)');
    foreach (['layout' => [$argv[1], 'layouts'], 'guide' => [$argv[2], 'guides']] as $kind => [$file, $key]) {
        $raw = file_get_contents($file);
        if ($raw === false) {
            throw new RuntimeException('Could not read the content snapshot');
        }
        $document = json_decode($raw, true, 64, JSON_THROW_ON_ERROR);
        if (($document['version'] ?? null) !== 1 || !is_array($document[$key] ?? null) ||
            !array_is_list($document[$key])) {
            throw new RuntimeException('Invalid content snapshot');
        }
        foreach ($document[$key] as $position => $row) {
            if (!is_array($row) || !is_string($row['id'] ?? null) ||
                !preg_match('/^[A-Za-z0-9_-]{1,100}$/D', $row['id'])) {
                throw new RuntimeException('Invalid content ID');
            }
            $insert->execute([$kind, $row['id'], $position, json_encode($row, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE)]);
        }
    }
    $pdo->commit();
} catch (Throwable $error) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    fwrite(STDERR, "Import failed. Check database configuration, IDs and whether content is already seeded.\n");
    exit(1);
}
echo "Published catalog and guides imported.\n";
