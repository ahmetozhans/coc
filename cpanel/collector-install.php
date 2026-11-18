<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}
$target = realpath($argv[1] ?? '');
$mode = $argv[2] ?? '--check';
if (!$target || !in_array($mode, ['--check', '--apply'], true) || !is_dir($target . '/public_html') || !is_dir($target . '/mahanpro-private')) {
    fwrite(STDERR, "Usage: php collector-install.php /home/CPANEL_ACCOUNT [--check|--apply]\n");
    exit(1);
}
try {
    $manifest = json_decode(file_get_contents(__DIR__ . '/manifest.json'), true, 32, JSON_THROW_ON_ERROR);
    $conflicts = [];
    $pending = [];
    foreach ($manifest as $file) {
        $source = __DIR__ . '/files/' . $file['path'];
        $destination = $target . '/' . $file['path'];
        if (!hash_equals($file['after'], hash_file('sha256', $source))) {
            throw new RuntimeException('Package checksum mismatch: ' . $file['path']);
        }
        $hash = is_file($destination) ? hash_file('sha256', $destination) : null;
        if ($hash === $file['after']) {
            continue;
        }
        if ($hash !== $file['before']) {
            $conflicts[] = $file['path'];
        } else {
            $pending[] = $file;
        }
    }
    if ($conflicts) {
        fwrite(STDERR, "Kurulum durdu. Bu dosyalar temel GitHub sürümünden farklı:\n" . implode("\n", $conflicts) . "\nCanlı dosyaları reference/ ve files/ sürümleriyle karşılaştırıp değişiklikleri birleştir.\n");
        exit(2);
    }
    if ($mode === '--check') {
        echo "Kontrol geçti. " . count($pending) . " dosya güncellenebilir. --apply ile kurulabilir.\n";
        exit;
    }
    if (!$pending) {
        echo "Bu sürüm zaten kurulu.\n";
        exit;
    }
    $backup = $target . '/collector-backup-' . gmdate('Ymd-His') . '-' . bin2hex(random_bytes(3));
    mkdir($backup, 0700);
    $staged = [];
    $applied = [];
    try {
        foreach ($pending as $file) {
            $destination = $target . '/' . $file['path'];
            if (is_file($destination)) {
                $saved = $backup . '/' . $file['path'];
                if (!is_dir(dirname($saved))) {
                    mkdir(dirname($saved), 0700, true);
                }
                if (!copy($destination, $saved)) {
                    throw new RuntimeException('Backup failed: ' . $file['path']);
                }
            }
            $tmp = tempnam(dirname($destination), '.collector-install-');
            if (!$tmp || !copy(__DIR__ . '/files/' . $file['path'], $tmp) || !chmod($tmp, 0644)) {
                throw new RuntimeException('Staging failed: ' . $file['path']);
            }
            $staged[$file['path']] = $tmp;
        }
        usort($pending, static fn ($a, $b) => ($a['path'] === 'public_html/index.php') <=> ($b['path'] === 'public_html/index.php'));
        foreach ($pending as $file) {
            $destination = $target . '/' . $file['path'];
            clearstatcache(true, $destination);
            $hash = is_file($destination) ? hash_file('sha256', $destination) : null;
            if ($hash !== $file['before'] || !rename($staged[$file['path']], $destination)) {
                throw new RuntimeException('File changed during install: ' . $file['path']);
            }
            $applied[] = $file;
        }
        echo "Dosyalar kuruldu. Özel yedek: $backup\n";
    } catch (Throwable $error) {
        foreach (array_reverse($applied) as $file) {
            $destination = $target . '/' . $file['path'];
            $saved = $backup . '/' . $file['path'];
            clearstatcache(true, $destination);
            if (!is_file($destination) || !hash_equals($file['after'], hash_file('sha256', $destination))) {
                fwrite(STDERR, 'Dosya kurulum sırasında ayrıca değiştirilmiş, otomatik geri alınmadı: ' . $file['path'] . "\n");
                continue;
            }
            if (is_file($saved)) {
                copy($saved, $destination);
            } else {
                unlink($destination);
            }
        }
        throw $error;
    } finally {
        foreach ($staged as $tmp) {
            if (is_file($tmp)) {
                unlink($tmp);
            }
        }
    }
} catch (Throwable $error) {
    fwrite(STDERR, $error->getMessage() . "\n");
    exit(1);
}
