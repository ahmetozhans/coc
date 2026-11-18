<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}
require __DIR__ . '/collector-installer-core.php';
$target = realpath($argv[1] ?? '');
$mode = $argv[2] ?? '--check';
if (!$target || !in_array($mode, ['--check', '--apply'], true)
    || !is_dir($target . '/public_html') || !is_dir($target . '/mahanpro-private')) {
    fwrite(STDERR, "Usage: php collector-install.php /home/CPANEL_ACCOUNT [--check|--apply]\n");
    exit(1);
}
try {
    $plan = collector_setup_plan($target, __DIR__);
    if ($plan['conflicts']) {
        fwrite(STDERR, "Bu dosyalarda canlı değişiklikler çakışıyor:\n" . implode("\n", $plan['conflicts']) . "\n");
        exit(2);
    }
    if ($mode === '--check') {
        echo "Kontrol geçti. " . count($plan['pending']) . " dosya güncellenebilir.\n";
        exit;
    }
    $backup = collector_setup_apply($target, $plan);
    echo $backup ? "Kuruldu. Özel yedek: $backup\n" : "Bu sürüm zaten kurulu.\n";
} catch (Throwable $error) {
    fwrite(STDERR, $error->getMessage() . "\n");
    exit(1);
}
