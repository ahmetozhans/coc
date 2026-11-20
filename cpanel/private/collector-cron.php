<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}
require __DIR__ . '/content.php';
require __DIR__ . '/stats.php';
require __DIR__ . '/reviews.php';
require __DIR__ . '/cms.php';
require __DIR__ . '/collector.php';
try {
    $result = collector_scan(db());
    if (empty($result['skipped'])) {
        echo json_encode($result, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE) . "\n";
    }
} catch (Throwable $error) {
    error_log('Mahanpro collector cron failed: ' . get_class($error));
    fwrite(STDERR, "Köy toplama çalışması tamamlanamadı. Panelde tarama geçmişini ve PHP hata kaydını incele.\n");
    exit(1);
}
