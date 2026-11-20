<?php
declare(strict_types=1);

function require_cms(bool $condition): void
{
    if (!$condition) {
        throw new InvalidArgumentException('İçerik veya ayar alanları geçersiz.');
    }
}

function bounded_text(mixed $value, int $limit, bool $required = false): bool
{
    return is_string($value) && strlen($value) <= $limit * 4 &&
        preg_match('/^.{0,' . $limit . '}$/usD', $value) === 1 &&
        (!$required || trim($value) !== '');
}

function check_record(mixed $item, string $kind): void
{
    require_cms(is_object($item) && content_id($item->id ?? null) && is_bool($item->hidden ?? null));
    if ($kind === 'layout') {
        $th = $item->th ?? null;
        require_cms(is_int($th) && $th >= 3 && $th <= 19 &&
            in_array($item->type ?? null, ['War', 'Farming', 'Trophy', 'Hybrid'], true) &&
            bounded_text($item->title ?? null, 70, true) &&
            bounded_text($item->description ?? '', 1200) &&
            (($item->image ?? '') === '' || https_link($item->image) !== '') &&
            game_link($item->url ?? null, 'OpenLayout', 'id', '/^TH' . $th . ':[A-Za-z0-9:_-]+$/D') !== '');
        return;
    }
    $levels = $item->townHalls ?? null;
    require_cms(in_array($item->category ?? null, ['Air', 'Ground', 'War'], true) &&
        is_array($levels) && count($levels) >= 1 && count($levels) <= 16 &&
        !array_filter($levels, fn ($level) => !is_int($level) || $level < 3 || $level > 18) &&
        (($item->armyUrl ?? '') === '' || game_link($item->armyUrl, 'CopyArmy', 'army', '/^[A-Za-z0-9:-]{1,2000}$/D') !== ''));
    foreach (['tr', 'en'] as $lang) {
        $steps = $item->steps->$lang ?? null;
        require_cms(bounded_text($item->title->$lang ?? null, 100, true) &&
            bounded_text($item->summary->$lang ?? null, 500, true) &&
            is_array($steps) && count($steps) >= 2 && count($steps) <= 8 &&
            !array_filter($steps, fn ($step) => !bounded_text($step, 350, true)));
    }
    require_cms(count($item->steps->tr) === count($item->steps->en));
}

function check_site(mixed $site): void
{
    $pages = ['home', 'bases', 'clan', 'attacks', 'armies', 'upgrade'];
    require_cms(is_object($site) && is_object($site->pages ?? null) && is_object($site->texts ?? null));
    foreach ($site as $key => $value) {
        $valid = match ($key) {
            'name', 'tagline' => bounded_text($value, 60, true),
            'logo', 'heroImage' => $value === '' || https_link($value) !== '',
            'accent' => is_string($value) && preg_match('/^#[a-f0-9]{6}$/iD', $value) === 1,
            'maintenance' => is_bool($value),
            'announcement', 'maintenanceText', 'footer' => bounded_text($value->tr ?? null, 500) && bounded_text($value->en ?? null, 500),
            'pages', 'texts' => true,
            default => false,
        };
        require_cms($valid);
    }
    require_cms(count(get_object_vars($site->texts)) <= 250);
    foreach ($site->texts as $key => $value) {
        require_cms(preg_match('/^(home|bases|clan|attacks|armies|upgrade)-\d+$/D', $key) === 1 &&
            bounded_text($value->tr ?? null, 1200) && bounded_text($value->en ?? null, 1200));
    }
    foreach ($site->pages as $key => $value) {
        require_cms(in_array($key, $pages, true) && is_bool($value->enabled ?? null) &&
            bounded_text($value->seoTitle ?? null, 120) && bounded_text($value->seoDescription ?? null, 300));
    }
}

function check_document(mixed $document): void
{
    require_cms(is_object($document) && is_object($document->layoutOverrides ?? null) &&
        is_object($document->guideOverrides ?? null));
    check_site($document->site ?? null);
    foreach (['layout' => 5000, 'guide' => 100] as $kind => $limit) {
        $key = $kind . 'Overrides';
        require_cms(count(get_object_vars($document->$key)) <= $limit);
        foreach ($document->$key as $id => $item) {
            check_record($item, $kind);
            require_cms((string) $id === $item->id);
        }
    }
}

function check_collection(PDO $db, object $document): void
{
    $layouts = content($db, 'layout', $document, true);
    require_cms(count(content($db, 'guide', $document, true)) <= 100);
    require_once __DIR__ . '/collector.php';
    collector_check_capacity(content($db, 'layout', cms_state($db)['document'], true), $layouts);
    $ids = [];
    foreach ($layouts as $item) {
        if ($item['hidden']) {
            continue;
        }
        parse_str(parse_url($item['url'], PHP_URL_QUERY) ?? '', $query);
        $id = $query['id'] ?? null;
        require_cms(is_string($id) && !isset($ids[$id]));
        $ids[$id] = true;
    }
}

function cms_route(): never
{
    if (!admin_session()) {
        json_response(['error' => 'Önce yönetim panelinde giriş yap.'], 401);
    }
    $db = db();
    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        $state = cms_state($db);
        $doc = $state['document'];
        $layouts = content($db, 'layout', $doc, true);
        $counts = [];
        foreach (array_chunk(array_column($layouts, 'id'), 50) as $ids) {
            $counts += stats($db, $ids);
        }
        $defaults = json_decode(file_get_contents(__DIR__ . '/site-defaults.json'), false, 64, JSON_THROW_ON_ERROR);
        unset($defaults->pages->clan, $defaults->pages->upgrade);
        json_response($state + ['version' => 1, 'layouts' => $layouts, 'guides' => content($db, 'guide', $doc, true),
            'defaults' => $defaults, 'stats' => (object) $counts]);
    }
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        header('Allow: GET, POST');
        json_response(['error' => 'Yöntem geçersiz.'], 405);
    }
    if (($_SERVER['HTTP_ORIGIN'] ?? '') !== rtrim(config()['base_url'], '/') ||
        !hash_equals($_SESSION['csrf'], $_SERVER['HTTP_X_MAHANPRO_CSRF'] ?? '') ||
        !str_starts_with($_SERVER['CONTENT_TYPE'] ?? '', 'application/json')) {
        json_response(['error' => 'Oturum veya istek doğrulanamadı.'], 403);
    }
    $raw = file_get_contents('php://input', false, null, 0, 1500001);
    if ($raw === false || strlen($raw) > 1500000) {
        json_response(['error' => 'İstek çok büyük.'], 413);
    }
    $request = json_decode($raw);
    $db->beginTransaction();
    try {
        $state = cms_state($db, true);
        if (($request->revision ?? null) !== $state['revision']) {
            $db->rollBack();
            json_response(['error' => 'Panel değişti. Yenileyip tekrar dene.'], 409);
        }
        $doc = $state['document'];
        $now = gmdate('Y-m-d\TH:i:s\Z');
        $action = $request->action ?? null;
        if (in_array($action, ['layout', 'guide'], true)) {
            $item = $request->item ?? null;
            check_record($item, $action);
            $key = $action . 'Overrides';
            if ($action === 'layout' && !empty($item->archived) && !$item->hidden) {
                // Existing Studio publish controls also restore an archived record.
                $item->archived = false;
                unset($item->archivedAt);
            }
            $item->updated = $now;
            $doc->$key->{$item->id} = $item;
            $label = $action === 'layout' ? 'Köy düzeni güncellendi' : 'Saldırı rehberi güncellendi';
        } elseif ($action === 'site') {
            $doc->site = $request->site ?? null;
            $label = 'Site ayarları güncellendi';
        } elseif ($action === 'restore') {
            $backup = $request->backup ?? null;
            require_cms(($backup->type ?? null) === 'mahanpro-cms-backup' && ($backup->version ?? null) === 1);
            $restored = $backup->document ?? null;
            check_document($restored);
            $doc->site = $restored->site;
            $doc->layoutOverrides = $restored->layoutOverrides;
            $doc->guideOverrides = $restored->guideOverrides;
            $label = 'Panel yedeği geri yüklendi';
        } else {
            throw new InvalidArgumentException('Bilinmeyen işlem.');
        }
        check_document($doc);
        check_collection($db, $doc);
        $doc->history = array_slice(array_merge([['at' => $now, 'text' => $label]], $doc->history ?? []), 0, 30);
        $revision = $state['revision'] + 1;
        $write = $db->prepare('UPDATE admin_content SET revision = ?, document = ?, updated_at = ? WHERE id = 1');
        $write->execute([$revision, json_encode($doc, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE), $now]);
        $db->commit();
        json_response(['ok' => true, 'revision' => $revision, 'updatedAt' => $now]);
    } catch (InvalidArgumentException $error) {
        $db->rollBack();
        json_response(['error' => $error->getMessage()], 400);
    }
}
