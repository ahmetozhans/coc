<?php
declare(strict_types=1);

function collector_setup_merge(string $current, array $edits): string
{
    if (!$edits) {
        throw new RuntimeException('Bu dosya için güvenli birleştirme bilgisi bulunmuyor.');
    }
    foreach ($edits as $edit) {
        $old = $edit['old'];
        $new = $edit['new'];
        $oldCount = $old === '' ? 0 : substr_count($current, $old);
        $newCount = $new === '' ? 0 : substr_count($current, $new);
        if ($newCount === 1 && $oldCount === 0) {
            continue;
        }
        if ($oldCount !== 1 || $newCount > 0) {
            throw new RuntimeException('Aynı satırlarda canlı değişiklik var.');
        }
        $position = strpos($current, $old);
        $current = substr_replace($current, $new, $position, strlen($old));
    }
    return $current;
}

function collector_setup_plan(string $target, string $package): array
{
    $manifest = json_decode(file_get_contents($package . '/manifest.json'), true, 32, JSON_THROW_ON_ERROR);
    $pending = [];
    $conflicts = [];
    foreach ($manifest as $file) {
        if (!preg_match('~^(public_html|mahanpro-private)/[A-Za-z0-9_.-]+$~D', $file['path'])) {
            throw new RuntimeException('Paket dosya yolu geçersiz.');
        }
        $source = $package . '/files/' . $file['path'];
        $destination = $target . '/' . $file['path'];
        if (!is_file($source) || !hash_equals($file['after'], hash_file('sha256', $source))) {
            throw new RuntimeException('Paket dosyası eksik veya değişmiş: ' . $file['path']);
        }
        if (is_link($destination) || !is_dir(dirname($destination))) {
            $conflicts[] = $file['path'];
            continue;
        }
        $current = is_file($destination) ? file_get_contents($destination) : null;
        $hash = $current === null ? null : hash('sha256', $current);
        if ($hash === $file['after']) {
            continue;
        }
        $next = file_get_contents($source);
        $merged = false;
        if ($hash !== $file['before']) {
            if ($hash === null || $file['before'] === null) {
                $conflicts[] = $file['path'];
                continue;
            }
            try {
                $next = collector_setup_merge($current, $file['edits'] ?? []);
                $merged = true;
            } catch (RuntimeException) {
                $conflicts[] = $file['path'];
                continue;
            }
        }
        if ($current === $next) {
            continue;
        }
        if (!is_writable(dirname($destination)) || (is_file($destination) && !is_writable($destination))) {
            $conflicts[] = $file['path'] . ' (yazma izni gerekiyor)';
            continue;
        }
        $pending[] = ['path' => $file['path'], 'before' => $hash, 'after' => hash('sha256', $next),
            'bytes' => $next, 'merged' => $merged];
    }
    return ['pending' => $pending, 'conflicts' => $conflicts];
}

function collector_setup_plan_hash(array $plan): string
{
    return hash('sha256', json_encode($plan, JSON_THROW_ON_ERROR));
}

function collector_setup_apply(string $target, array $plan): ?string
{
    if ($plan['conflicts']) {
        throw new RuntimeException('Dosya çakışmaları çözülmeli.');
    }
    if (!$plan['pending']) {
        return null;
    }
    $backup = $target . '/mahanpro-private/collector-backup-' . gmdate('Ymd-His') . '-' . bin2hex(random_bytes(3));
    if (!mkdir($backup, 0700)) {
        throw new RuntimeException('Özel dosya yedeği oluşturulamadı.');
    }
    $pending = $plan['pending'];
    $staged = [];
    $applied = [];
    try {
        foreach ($pending as $file) {
            $destination = $target . '/' . $file['path'];
            if (is_file($destination)) {
                $saved = $backup . '/' . $file['path'];
                if (!is_dir(dirname($saved)) && !mkdir(dirname($saved), 0700, true)) {
                    throw new RuntimeException('Yedek klasörü hazırlanamadı.');
                }
                if (!copy($destination, $saved)) {
                    throw new RuntimeException('Dosya yedeği alınamadı: ' . $file['path']);
                }
            }
            $tmp = tempnam(dirname($destination), '.collector-install-');
            if (!$tmp) {
                throw new RuntimeException('Geçici dosya hazırlanamadı.');
            }
            $staged[$file['path']] = $tmp;
            if (file_put_contents($tmp, $file['bytes']) !== strlen($file['bytes']) || !chmod($tmp, 0644)) {
                throw new RuntimeException('Dosya hazırlanamadı: ' . $file['path']);
            }
        }
        usort($pending, static fn ($a, $b) => ($a['path'] === 'public_html/index.php') <=> ($b['path'] === 'public_html/index.php'));
        foreach ($pending as $file) {
            $destination = $target . '/' . $file['path'];
            clearstatcache(true, $destination);
            $hash = is_file($destination) ? hash_file('sha256', $destination) : null;
            if ($hash !== $file['before'] || !rename($staged[$file['path']], $destination)) {
                throw new RuntimeException('Dosya kurulum sırasında değişti: ' . $file['path']);
            }
            $applied[] = $file;
            if (function_exists('opcache_invalidate')) {
                opcache_invalidate($destination, true);
            }
        }
        return $backup;
    } catch (Throwable $error) {
        foreach (array_reverse($applied) as $file) {
            $destination = $target . '/' . $file['path'];
            $saved = $backup . '/' . $file['path'];
            clearstatcache(true, $destination);
            if (!is_file($destination) || !hash_equals($file['after'], hash_file('sha256', $destination))) {
                error_log('Collector setup rollback requires manual review: ' . $file['path']);
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
}
