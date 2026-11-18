<?php
declare(strict_types=1);

function canonical_json(mixed $value): string
{
    $flags = JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_LINE_TERMINATORS;
    if (is_array($value)) {
        return '[' . implode(',', array_map('canonical_json', $value)) . ']';
    }
    if (is_object($value)) {
        $fields = get_object_vars($value);
        ksort($fields, SORT_STRING);
        $pairs = [];
        foreach ($fields as $key => $entry) {
            $pairs[] = json_encode((string) $key, $flags) . ':' . canonical_json($entry);
        }
        return '{' . implode(',', $pairs) . '}';
    }
    return json_encode($value, $flags);
}

function proposal_fingerprint(object $proposal): string
{
    return hash('sha256', canonical_json((object) [
        'kind' => $proposal->kind,
        'payload' => $proposal->payload,
        'imageSha256' => $proposal->imageSha256 ?? '',
    ]));
}

function pending_inbox(): array
{
    $raw = file_get_contents(__DIR__ . '/content-inbox.json');
    if ($raw === false || strlen($raw) > 1000000) {
        throw new RuntimeException('Inbox unavailable');
    }
    $inbox = json_decode($raw, false, 64, JSON_THROW_ON_ERROR);
    if (($inbox->version ?? null) !== 1 || !is_array($inbox->proposals ?? null) || count($inbox->proposals) >= 100) {
        throw new RuntimeException('Inbox invalid');
    }
    $result = [];
    foreach ($inbox->proposals as $item) {
        if (!is_object($item) || !content_id($item->id ?? null) ||
            !in_array($item->kind ?? null, ['layout', 'guide'], true) ||
            !is_object($item->payload ?? null) || !is_string($item->fingerprint ?? null) ||
            !hash_equals(proposal_fingerprint($item), $item->fingerprint) || isset($result[$item->id])) {
            throw new RuntimeException('Invalid proposal fingerprint or ID');
        }
        $result[$item->id] = $item;
    }
    return $result;
}

function was_published(object $proposal): bool
{
    return ($proposal->publication->status ?? null) === 'published' &&
        ($proposal->publication->fingerprint ?? null) === $proposal->fingerprint;
}

function admin_page(string $body, int $status = 200, ?string $accessTitle = null): never
{
    http_response_code($status);
    header('Content-Type: text/html; charset=UTF-8');
    header('Cache-Control: no-store');
    header('X-Robots-Tag: noindex, nofollow');
    header('Content-Security-Policy: default-src \'none\'; style-src \'self\'; img-src \'self\' https:; form-action \'self\'; frame-ancestors \'none\'; base-uri \'none\'');
    if ($accessTitle !== null) {
        echo '<!doctype html><html lang="tr"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>'
            . h($accessTitle) . ' | Mahanpro</title><link rel="stylesheet" href="/admin-access.css?v=1"></head>'
            . '<body class="admin-access"><main class="access-shell">'
            . '<div class="access-banner" aria-hidden="true"><span class="access-wordmark">Mahan<span>pro</span></span></div>'
            . '<section class="access-panel"><a class="access-back" href="/">← Siteye dön</a>'
            . '<div class="access-content"><p class="access-label">MAHANPRO STÜDYO</p><h1>' . h($accessTitle) . '</h1>'
            . $body . '</div></section></main></body></html>';
        exit;
    }
    echo '<!doctype html><html lang="tr"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Mahanpro Yönetim</title><link rel="stylesheet" href="/admin-access.css?v=1"></head><body class="admin-reviews"><main class="review-shell">' . $body . '</main></body></html>';
    exit;
}

function admin_username(mixed $configured = 'admin'): string
{
    if (!is_string($configured) || preg_match('/^[A-Za-z0-9._-]{3,32}$/D', $configured) !== 1) {
        throw new RuntimeException('Invalid configured administrator username');
    }
    return strtolower($configured);
}

function admin_login_matches(mixed $user, mixed $password, string $expected, string $hash): bool
{
    // Verify the password for both matching and non-matching names.
    $passwordValid = is_string($password) && strlen($password) <= 128 && password_verify($password, $hash);
    return $passwordValid && is_string($user) && strlen($user) <= 32 &&
        hash_equals($expected, strtolower(trim($user)));
}

function admin_login_form(string $csrf, string $notice = '', int $status = 200): never
{
    $alert = $notice === '' ? '' : '<p class="access-alert" role="alert">' . h($notice) . '</p>';
    admin_page('<p class="access-intro">İçeriği düzenlemek ve önerileri incelemek için giriş yap.</p>' . $alert
        . '<form class="access-form" action="/admin" method="post">' . $csrf
        . '<input type="hidden" name="action" value="login">'
        . '<label for="admin-user">Kullanıcı adı</label>'
        . '<input id="admin-user" name="username" maxlength="32" autocomplete="username" autocapitalize="none" spellcheck="false" required>'
        . '<label for="admin-password">Parola</label>'
        . '<input id="admin-password" type="password" name="password" maxlength="128" autocomplete="current-password" required>'
        . '<button type="submit">Giriş yap <span aria-hidden="true">→</span></button></form>', $status, 'Yönetici girişi');
}

function admin_setup_form(string $csrf, string $username, string $notice = '', int $status = 200): never
{
    $alert = $notice === '' ? '' : '<p class="access-alert" role="alert">' . h($notice) . '</p>';
    admin_page('<p class="access-intro">Kurulum kodunu kullanarak <strong>' . h($username) . '</strong> hesabının parolasını belirle.</p>' . $alert
        . '<form class="access-form" action="/admin" method="post">' . $csrf
        . '<input type="hidden" name="action" value="setup">'
        . '<label for="admin-code">Kurulum kodu</label>'
        . '<input id="admin-code" type="password" name="setup_code" maxlength="128" autocomplete="off" required>'
        . '<label for="admin-new">Yeni parola</label>'
        . '<input id="admin-new" type="password" name="password" minlength="12" maxlength="72" autocomplete="new-password" required>'
        . '<label for="admin-repeat">Yeni parola tekrar</label>'
        . '<input id="admin-repeat" type="password" name="password_confirm" minlength="12" maxlength="72" autocomplete="new-password" required>'
        . '<button type="submit">Parolayı oluştur <span aria-hidden="true">→</span></button></form>', $status, 'İlk kurulum');
}

function review_preview(object $item, string $title): string
{
    $payload = $item->payload;
    if ($item->kind === 'layout') {
        $image = https_link($payload->image ?? null);
        $url = https_link($payload->url ?? null, 'link.clashofclans.com');
        return ($image ? '<img src="' . h($image) . '" alt="' . h($title) . '" loading="lazy" referrerpolicy="no-referrer">' : '')
            . '<p>TH ' . (int) ($payload->th ?? 0) . ' · ' . h((string) ($payload->type ?? '')) . '</p>'
            . ($url ? '<p><a href="' . h($url) . '" target="_blank" rel="noopener noreferrer">Köy bağlantısını aç ↗</a></p>' : '');
    }
    $html = '<p>' . h((string) ($payload->summary->tr ?? '')) . '</p><ol>';
    foreach ($payload->steps->tr ?? [] as $step) {
        $html .= '<li>' . h((string) $step) . '</li>';
    }
    $html .= '</ol><details><summary>İngilizce rehberi göster</summary><p><strong>'
        . h((string) ($payload->title->en ?? '')) . '</strong></p><p>'
        . h((string) ($payload->summary->en ?? '')) . '</p><ol>';
    foreach ($payload->steps->en ?? [] as $step) {
        $html .= '<li>' . h((string) $step) . '</li>';
    }
    $army = game_link($payload->armyUrl ?? null, 'CopyArmy', 'army', '/^[A-Za-z0-9:-]{1,2000}$/D');
    return $html . '</ol></details>' . ($army ? '<p><a href="' . h($army) . '" target="_blank" rel="noopener noreferrer">Ordu bağlantısını aç ↗</a></p>' : '');
}

function admin_hash(): ?string
{
    static $loaded = false;
    static $hash = null;
    if (!$loaded) {
        $stored = db()->query('SELECT password_hash FROM admin_credentials WHERE id = 1')->fetchColumn();
        if ($stored === false) {
            throw new RuntimeException('Admin credential schema not installed');
        }
        $candidate = $stored ?? (config()['admin_password_hash'] ?? '');
        $hash = is_string($candidate) && password_get_info($candidate)['algo'] ? $candidate : null;
        if ($stored !== null && $hash === null) {
            throw new RuntimeException('Invalid stored admin credential');
        }
        $loaded = true;
    }
    return $hash;
}

function valid_admin_setup_code(mixed $code): bool
{
    return is_string($code) && preg_match('/^[A-Za-z0-9_-]{24,128}$/D', $code) === 1;
}

function initialize_admin_password(PDO $database, string $password): bool
{
    $write = $database->prepare('UPDATE admin_credentials SET password_hash = ? WHERE id = 1 AND password_hash IS NULL');
    $write->execute([password_hash($password, PASSWORD_DEFAULT)]);
    return $write->rowCount() === 1;
}

function admin_session(): bool
{
    if (!in_array($_SERVER['HTTPS'] ?? '', ['on', '1'], true)) {
        return false;
    }
    ini_set('session.use_strict_mode', '1');
    ini_set('session.use_only_cookies', '1');
    session_name('mahanpro_admin');
    session_set_cookie_params(['lifetime' => 0, 'path' => '/', 'secure' => true, 'httponly' => true, 'samesite' => 'Strict']);
    if (!session_start()) {
        return false;
    }
    $_SESSION['csrf'] ??= bin2hex(random_bytes(32));
    $hash = admin_hash();
    return $hash !== null && ($_SESSION['admin_until'] ?? 0) > time() &&
        hash_equals(hash('sha256', $hash), $_SESSION['admin_proof'] ?? '');
}

function studio_route(): never
{
    if (!admin_session()) {
        header('Location: /admin', true, 303);
        exit;
    }
    $template = file_get_contents(__DIR__ . '/templates/studio.html');
    if ($template === false) {
        throw new RuntimeException('Studio template missing');
    }
    $template = preg_replace('~(<nav\b[^>]*aria-label="Yönetim"[^>]*>)~',
        '$1<a class="button small" href="/admin/collector">Otomatik Köy Toplama</a>', $template, 1);
    header('Content-Type: text/html; charset=UTF-8');
    header('Cache-Control: no-store');
    header('X-Robots-Tag: noindex, nofollow');
    header("Content-Security-Policy: default-src 'self'; style-src 'self' 'unsafe-inline'; img-src 'self' https: data:; frame-ancestors 'none'; base-uri 'none'; form-action 'self'");
    echo str_replace(
        ['<!-- STUDIO_CSRF -->', 'href="/admin">Önerileri incele', 'href="admin.html"'],
        ['<meta name="mahanpro-csrf" content="' . h($_SESSION['csrf']) . '">', 'href="/admin?view=reviews">Önerileri incele', 'href="/studio"'],
        $template
    );
    exit;
}

function admin_route(): never
{
    $authenticated = admin_session();
    if (session_status() !== PHP_SESSION_ACTIVE) {
        admin_page('<p>Yönetim paneli için HTTPS ve çalışan bir PHP oturumu gerekiyor.</p>', 503);
    }
    if ($authenticated && $_SERVER['REQUEST_METHOD'] === 'GET' && ($_GET['view'] ?? '') !== 'reviews') {
        header('Location: /studio', true, 303);
        exit;
    }
    $hash = admin_hash();
    $setupCode = config()['admin_setup_code'] ?? '';
    $setupAllowed = $hash === null && valid_admin_setup_code($setupCode);
    $username = admin_username(config()['admin_username'] ?? 'admin');
    $csrf = '<input type="hidden" name="csrf" value="' . h($_SESSION['csrf']) . '">';
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if (($_SERVER['HTTP_ORIGIN'] ?? '') !== rtrim(config()['base_url'], '/') ||
            !is_string($_POST['csrf'] ?? null) || !hash_equals($_SESSION['csrf'], $_POST['csrf'])) {
            admin_page('<p>Oturum doğrulanamadı.</p>', 403);
        }
        $action = $_POST['action'] ?? '';
        if ($action === 'setup' && $setupAllowed) {
            if (($_SESSION['retry_at'] ?? 0) > time()) {
                admin_setup_form($csrf, $username, 'Birkaç saniye sonra tekrar dene.', 429);
            }
            $_SESSION['retry_at'] = time() + 3;
            $code = $_POST['setup_code'] ?? null;
            if (!valid_admin_setup_code($code) || !hash_equals($setupCode, $code)) {
                admin_setup_form($csrf, $username, 'Kurulum kodu geçersiz.', 403);
            }
            $password = $_POST['password'] ?? null;
            if (!is_string($password) || strlen($password) < 12 || strlen($password) > 72 ||
                str_contains($password, "\0") || $password !== ($_POST['password_confirm'] ?? null) ||
                $password === $code) {
                admin_setup_form($csrf, $username, '12–72 bayt uzunluğunda ayrı bir parola seç ve iki alana aynı parolayı yaz.', 400);
            }
            if (!initialize_admin_password(db(), $password)) {
                admin_page('<p>Yönetici parolası zaten oluşturuldu. Giriş sayfasını yenile.</p>', 409);
            }
            session_regenerate_id(true);
            $_SESSION = ['csrf' => bin2hex(random_bytes(32))];
            header('Location: /admin', true, 303);
            exit;
        } elseif ($action === 'login' && !$authenticated && $hash !== null) {
            if (($_SESSION['retry_at'] ?? 0) > time()) {
                admin_login_form($csrf, 'Birkaç saniye sonra tekrar dene.', 429);
            }
            $_SESSION['retry_at'] = time() + 3;
            if (admin_login_matches($_POST['username'] ?? null, $_POST['password'] ?? null, $username, $hash)) {
                session_regenerate_id(true);
                $_SESSION = ['admin_until' => time() + 3600, 'csrf' => bin2hex(random_bytes(32)), 'admin_proof' => hash('sha256', $hash)];
                header('Location: /studio', true, 303);
                exit;
            }
        } elseif ($authenticated && $action === 'logout') {
            $_SESSION = [];
            session_destroy();
            header('Location: /admin', true, 303);
            exit;
        } elseif ($authenticated && in_array($action, ['approved', 'rejected'], true)) {
            $id = $_POST['id'] ?? null;
            $fingerprint = $_POST['fingerprint'] ?? null;
            $item = content_id($id) ? (pending_inbox()[$id] ?? null) : null;
            if (!$item || was_published($item) || !is_string($fingerprint) ||
                !hash_equals($item->fingerprint, $fingerprint)) {
                admin_page('<p>Öneri değişti veya yayımlandı. Paneli yenile.</p>', 409);
            }
            $now = gmdate('Y-m-d\TH:i:s\Z');
            $db = db();
            $publication = $db->prepare('SELECT payload FROM public_content WHERE kind = ? AND id = ?');
            $publication->execute([$item->kind, $item->payload->id]);
            $stored = $publication->fetchColumn();
            if ($stored !== false && (json_decode($stored, true, 64, JSON_THROW_ON_ERROR)['approvalFingerprint'] ?? null) === $fingerprint) {
                admin_page('<p>Bu sürüm yayımlandı. Paneli yenile.</p>', 409);
            }
            $write = $db->prepare('INSERT INTO content_reviews (proposal_id, fingerprint, decision, reviewer_id, reviewed_at) VALUES (?, ?, ?, ?, ?) ON DUPLICATE KEY UPDATE fingerprint = ?, decision = ?, reviewer_id = ?, reviewed_at = ?');
            $write->execute([$id, $fingerprint, $action, 'cpanel-admin', $now, $fingerprint, $action, 'cpanel-admin', $now]);
            header('Location: /admin?view=reviews', true, 303);
            exit;
        } else {
            admin_page('<p>İşlem geçersiz.</p>', 400);
        }
    } elseif ($_SERVER['REQUEST_METHOD'] !== 'GET') {
        header('Allow: GET, POST');
        admin_page('<p>İşlem geçersiz.</p>', 405);
    }
    if (!$authenticated) {
        if ($setupAllowed) {
            admin_setup_form($csrf, $username);
        }
        if ($hash === null) {
            admin_page('<p>Yönetici hesabı henüz kurulmadı. Özel config.php dosyasına kurulum kodu ekle.</p>', 503);
        }
        $failed = $_SERVER['REQUEST_METHOD'] === 'POST';
        admin_login_form($csrf, $failed ? 'Kullanıcı adı veya parola hatalı.' : '', $failed ? 401 : 200);
    }
    $body = '<header class="review-header"><div><p class="access-label">MAHANPRO STÜDYO</p><h1>İçerik önerileri</h1></div>'
        . '<div class="review-actions"><a class="review-button" href="/studio">İçerik Stüdyosu</a>'
        . '<form method="post">' . $csrf . '<button class="review-button review-button--quiet" name="action" value="logout">Çıkış yap</button></form></div></header>'
        . '<p>Bir öneriyi onaylamadan önce içeriğini ve varsa oyun bağlantısını incele.</p>';
    $decisions = [];
    $db = db();
    foreach ($db->query('SELECT proposal_id, fingerprint, decision FROM content_reviews') as $row) {
        $decisions[$row['proposal_id']] = $row;
    }
    $receipts = [];
    foreach ($db->query('SELECT proposal_id, fingerprint FROM content_publications') as $row) {
        $receipts[$row['proposal_id']][$row['fingerprint']] = true;
    }
    $items = pending_inbox();
    if (!$items) {
        $body .= '<p>İncelenecek öneri bulunmuyor.</p>';
    }
    $body .= '<div class="review-grid">';
    foreach ($items as $id => $item) {
        $row = $decisions[$id] ?? [];
        $published = was_published($item) || isset($receipts[$id][$item->fingerprint]);
        $state = $published ? 'Yayımlandı' : (($row['fingerprint'] ?? '') === $item->fingerprint
            ? ($row['decision'] === 'approved' ? 'Onaylandı' : 'Reddedildi') : 'Bekliyor');
        $payload = $item->payload;
        $title = $item->kind === 'layout' ? $payload->title : $payload->title->tr;
        $body .= '<article class="review-card"><span class="review-state">' . h($state) . '</span><h3>' . h($title) . '</h3>'
            . review_preview($item, $title)
            . '<details><summary>Kayıt ayrıntıları</summary><pre>'
            . h(json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR)) . '</pre></details>';
        if (!$published) {
            $body .= '<form class="review-actions" method="post">' . $csrf . '<input type="hidden" name="id" value="' . h((string) $id)
                . '"><input type="hidden" name="fingerprint" value="' . h($item->fingerprint)
                . '"><button class="review-button" name="action" value="approved">Onayla</button> <button class="review-button review-button--quiet" name="action" value="rejected">Reddet</button></form>';
        }
        $body .= '</article>';
    }
    admin_page($body . '</div>');
}
