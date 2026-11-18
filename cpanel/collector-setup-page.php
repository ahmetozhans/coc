<?php
declare(strict_types=1);
require __DIR__ . '/core.php';

function collector_setup_page(string $body, int $status = 200): never
{
    http_response_code($status);
    header('Content-Type: text/html; charset=UTF-8');
    header('Cache-Control: no-store');
    header('X-Robots-Tag: noindex, nofollow');
    header("Content-Security-Policy: default-src 'none'; style-src 'self'; form-action 'self'; frame-ancestors 'none'; base-uri 'none'");
    echo '<!doctype html><html lang="tr"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">'
        . '<title>Mahanpro kolay kurulum</title><link rel="stylesheet" href="/mahanpro-night.css">'
        . '<link rel="stylesheet" href="/mahanpro-setup.css"></head><body><main class="container page-content setup-shell">'
        . '<a class="brand" href="/studio">Mahan<span class="brand-gold">pro</span></a><section class="panel">'
        . '<span class="eyebrow">OTOMATİK KÖY TOPLAMA</span><h1>Kolay kurulum</h1>' . $body . '</section></main></body></html>';
    exit;
}

$private = dirname(__DIR__);
$target = dirname($private);
$package = __DIR__ . '/payload';
if (!is_file($private . '/config.php') || !is_file($private . '/reviews.php')) {
    collector_setup_page('<p>Özel klasörün konumu bulunamadı. ZIP’i public_html ve mahanpro-private klasörlerinin bulunduğu ana dizine çıkar.</p>', 503);
}
require_once $private . '/content.php';
require_once $private . '/stats.php';
require_once $private . '/reviews.php';

if (!admin_session()) {
    collector_setup_page('<p>Kurulumu başlatmak için mevcut yönetici hesabınla giriş yap, sonra bu sayfaya dön.</p>'
        . '<a class="button primary" href="/admin">Yönetici girişi</a>');
}
$pdo = null;
$locked = false;
try {
    foreach (['pdo_mysql', 'curl', 'gd', 'dom', 'fileinfo'] as $extension) {
        if (!extension_loaded($extension)) {
            throw new RuntimeException('Hosting PHP ayarlarında şu uzantıyı aç: ' . $extension);
        }
    }
    if (!function_exists('imagewebp')) {
        throw new RuntimeException('Hosting PHP ayarlarında GD WebP desteğini aç.');
    }
    if (is_file(__DIR__ . '/installed.json')) {
        collector_setup_page('<p>Kurulum tamamlandı.</p><a class="button primary" href="/admin/collector">Otomatik Köy Toplama’yı aç</a>');
    }
    $plan = collector_setup_plan($target, $package);
    $sql = file_get_contents($package . '/20261118-layout-collector.sql');
    $expectedSql = trim(file_get_contents($package . '/migration.sha256'));
    if (!hash_equals($expectedSql, hash('sha256', $sql))) {
        throw new RuntimeException('Kurulum SQL dosyası eksik veya değişmiş.');
    }
    $planHash = hash('sha256', collector_setup_plan_hash($plan) . $expectedSql);
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if (($_SERVER['HTTP_ORIGIN'] ?? '') !== rtrim(config()['base_url'], '/')
            || !is_string($_POST['csrf'] ?? null) || !hash_equals($_SESSION['csrf'], $_POST['csrf'])) {
            collector_setup_page('<p>Oturum doğrulanamadı. Sayfayı yenileyip tekrar dene.</p>', 403);
        }
        if (!is_string($_POST['plan'] ?? null) || !hash_equals($planHash, $_POST['plan'])) {
            throw new RuntimeException('Kontrolden sonra dosyalar değişti. Sayfayı yenile.');
        }
        if ($plan['conflicts']) {
            throw new RuntimeException('Çakışan dosyalar incelenmeli.');
        }
        $pdo = db();
        $locked = (int) $pdo->query("SELECT GET_LOCK('mahanpro-collector-setup', 0)")->fetchColumn() === 1;
        if (!$locked) {
            throw new RuntimeException('Başka bir kurulum devam ediyor. Biraz sonra tekrar dene.');
        }
        // Recheck under the installer lock, before the first mutation.
        $fresh = collector_setup_plan($target, $package);
        if (collector_setup_plan_hash($fresh) !== collector_setup_plan_hash($plan)) {
            throw new RuntimeException('Dosyalar değişti. Sayfayı yenile.');
        }
        $pdo->exec($sql);
        $backup = collector_setup_apply($target, $fresh);
        $receipt = ['installedAt' => gmdate('c'), 'plan' => $planHash, 'backup' => $backup];
        if (file_put_contents(__DIR__ . '/installed.json', json_encode($receipt, JSON_THROW_ON_ERROR), LOCK_EX) === false) {
            throw new RuntimeException('Dosyalar kuruldu. Kurulum kaydı yazılamadı, klasör yazma iznini kontrol et.');
        }
        chmod(__DIR__ . '/installed.json', 0600);
        $pdo->query("SELECT RELEASE_LOCK('mahanpro-collector-setup')");
        $locked = false;
        collector_setup_page('<p>Kurulum tamamlandı. Dosya yedeği özel klasöre kaydedildi.</p>'
            . '<a class="button primary" href="/admin/collector">Otomatik Köy Toplama’yı aç</a>'
            . '<p>Kaynaklar ve ayarlar bölümünden ilk taramayı başlatabilirsin. Günlük zamanlama adımı paket içindeki kısa rehberde yer alıyor.</p>');
    }
    if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
        collector_setup_page('<p>Yöntem geçersiz.</p>', 405);
    }
    if ($plan['conflicts']) {
        $items = implode('', array_map(static fn ($path) => '<li>' . h($path) . '</li>', $plan['conflicts']));
        collector_setup_page('<p>Şu dosyalarda aynı satırlara gelen canlı değişiklikler var. Bu dosyalar incelenmeli.</p><ul>'
            . $items . '</ul><p>Bu ekranda listelenen dosyaları paylaşarak birleştirme isteyebilirsin. config.php dosyasını paylaşma.</p>', 409);
    }
    $merged = count(array_filter($plan['pending'], static fn ($file) => $file['merged']));
    $body = '<p>Dosya kontrolü tamamlandı. ' . count($plan['pending']) . ' dosya kurulmaya hazır.</p>';
    if ($merged) {
        $body .= '<p>' . $merged . ' dosyada mevcut değişikliklerle uyumlu birleştirme hazırlandı.</p>';
    }
    $body .= '<p>Kurulum dosya yedeğini alır ve gerekli ek veritabanı tablolarını oluşturur.</p>'
        . '<form method="post"><input type="hidden" name="csrf" value="' . h($_SESSION['csrf']) . '">'
        . '<input type="hidden" name="plan" value="' . h($planHash) . '">'
        . '<button class="button primary" type="submit">Kurulumu tamamla</button></form>';
    collector_setup_page($body);
} catch (Throwable $error) {
    if ($locked && $pdo) {
        $pdo->query("SELECT RELEASE_LOCK('mahanpro-collector-setup')");
    }
    error_log('Mahanpro setup failed: ' . get_class($error));
    $message = $error instanceof PDOException ? 'Veritabanı ekleri kurulamadı. Hosting kullanıcısının tablo oluşturma yetkisini kontrol et.' : $error->getMessage();
    collector_setup_page('<p role="alert">' . h($message) . '</p><a class="button" href="/mahanpro-setup.php">Tekrar kontrol et</a>', 409);
}
