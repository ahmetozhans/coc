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

function admin_page(string $body, int $status = 200): never
{
    http_response_code($status);
    header('Content-Type: text/html; charset=UTF-8');
    header('Cache-Control: no-store');
    header('X-Robots-Tag: noindex, nofollow');
    header('Content-Security-Policy: default-src \'none\'; style-src \'self\'; img-src https:; form-action \'self\'; frame-ancestors \'none\'; base-uri \'none\'');
    echo '<!doctype html><html lang="tr"><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Mahanpro Yönetim</title><link rel="stylesheet" href="/mahanpro-night.css"><main class="container page-content"><h1>Mahanpro Yönetim</h1>' . $body . '</main></html>';
    exit;
}

function admin_session(): bool
{
    $hash = config()['admin_password_hash'] ?? '';
    if (!is_string($hash) || !password_get_info($hash)['algo'] ||
        !in_array($_SERVER['HTTPS'] ?? '', ['on', '1'], true)) {
        return false;
    }
    ini_set('session.use_strict_mode', '1');
    ini_set('session.use_only_cookies', '1');
    session_name('mahanpro_admin');
    session_set_cookie_params(['lifetime' => 0, 'path' => '/', 'secure' => true, 'httponly' => true, 'samesite' => 'Strict']);
    session_start();
    $_SESSION['csrf'] ??= bin2hex(random_bytes(32));
    return ($_SESSION['admin_until'] ?? 0) > time();
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
    header('Content-Type: text/html; charset=UTF-8');
    header('Cache-Control: no-store');
    header('X-Robots-Tag: noindex, nofollow');
    header("Content-Security-Policy: default-src 'self'; style-src 'self' 'unsafe-inline'; img-src 'self' https: data:; frame-ancestors 'none'; base-uri 'none'; form-action 'self'");
    echo str_replace('<!-- STUDIO_CSRF -->', '<meta name="mahanpro-csrf" content="' . h($_SESSION['csrf']) . '">', $template);
    exit;
}

function admin_route(): never
{
    $authenticated = admin_session();
    if (session_status() !== PHP_SESSION_ACTIVE) {
        admin_page('<p>HTTPS ve yönetici hesabı ayarı gerekiyor.</p>', 503);
    }
    $hash = config()['admin_password_hash'];
    $csrf = '<input type="hidden" name="csrf" value="' . h($_SESSION['csrf']) . '">';
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if (($_SERVER['HTTP_ORIGIN'] ?? '') !== rtrim(config()['base_url'], '/') ||
            !is_string($_POST['csrf'] ?? null) || !hash_equals($_SESSION['csrf'], $_POST['csrf'])) {
            admin_page('<p>Oturum doğrulanamadı.</p>', 403);
        }
        $action = $_POST['action'] ?? '';
        if ($action === 'login' && !$authenticated) {
            if (($_SESSION['retry_at'] ?? 0) > time()) {
                admin_page('<p>Birkaç saniye sonra tekrar dene.</p>', 429);
            }
            $_SESSION['retry_at'] = time() + 3;
            $password = $_POST['password'] ?? '';
            if (is_string($password) && strlen($password) <= 128 && password_verify($password, $hash)) {
                session_regenerate_id(true);
                $_SESSION = ['admin_until' => time() + 3600, 'csrf' => bin2hex(random_bytes(32))];
                header('Location: /admin', true, 303);
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
            header('Location: /admin', true, 303);
            exit;
        } else {
            admin_page('<p>İşlem geçersiz.</p>', 400);
        }
    } elseif ($_SERVER['REQUEST_METHOD'] !== 'GET') {
        header('Allow: GET, POST');
        admin_page('<p>İşlem geçersiz.</p>', 405);
    }
    if (!$authenticated) {
        $failed = $_SERVER['REQUEST_METHOD'] === 'POST';
        admin_page(($failed ? '<p>Giriş bilgisi hatalı.</p>' : '') . '<form method="post">' . $csrf . '<input type="hidden" name="action" value="login"><label>Yönetici parolası <input type="password" name="password" maxlength="128" autocomplete="current-password" required></label> <button class="button primary">Giriş</button></form>', $failed ? 401 : 200);
    }
    $body = '<p><a class="button primary" href="/studio">İçerik Stüdyosu</a></p><form method="post">' . $csrf . '<button class="button" name="action" value="logout">Çıkış yap</button></form><h2>İçerik önerileri</h2>';
    $decisions = [];
    $db = db();
    foreach ($db->query('SELECT proposal_id, fingerprint, decision FROM content_reviews') as $row) {
        $decisions[$row['proposal_id']] = $row;
    }
    $receipts = [];
    foreach ($db->query('SELECT proposal_id, fingerprint FROM content_publications') as $row) {
        $receipts[$row['proposal_id']][$row['fingerprint']] = true;
    }
    foreach (pending_inbox() as $id => $item) {
        $row = $decisions[$id] ?? [];
        $published = was_published($item) || isset($receipts[$id][$item->fingerprint]);
        $state = $published ? 'Yayımlandı' : (($row['fingerprint'] ?? '') === $item->fingerprint
            ? ($row['decision'] === 'approved' ? 'Onaylandı' : 'Reddedildi') : 'Bekliyor');
        $payload = $item->payload;
        $title = $item->kind === 'layout' ? $payload->title : $payload->title->tr;
        $body .= '<article class="panel"><h3>' . h($title) . '</h3><p>' . $state . '</p>';
        if ($item->kind === 'layout' && ($image = https_link($payload->image ?? null))) {
            $body .= '<img src="' . h($image) . '" alt="' . h($title) . '" loading="lazy" width="320" referrerpolicy="no-referrer">';
        }
        $body .= '<pre>' . h(json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR)) . '</pre>';
        if (!$published) {
            $body .= '<form method="post">' . $csrf . '<input type="hidden" name="id" value="' . h((string) $id)
                . '"><input type="hidden" name="fingerprint" value="' . h($item->fingerprint)
                . '"><button class="button primary" name="action" value="approved">Onayla</button> <button class="button" name="action" value="rejected">Reddet</button></form>';
        }
        $body .= '</article>';
    }
    admin_page($body);
}
