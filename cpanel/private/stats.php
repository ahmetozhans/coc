<?php
declare(strict_types=1);

function content_id(mixed $value): bool
{
    return is_string($value) && preg_match('/^[A-Za-z0-9_-]{1,100}$/D', $value) === 1;
}

function stats(PDO $db, array $ids): array
{
    $marks = implode(',', array_fill(0, count($ids), '?'));
    $counts = array_fill_keys($ids, ['views' => 0, 'downloads' => 0, 'favorites' => 0]);
    $events = $db->prepare("SELECT layout_id, kind, COUNT(*) AS total FROM layout_events WHERE layout_id IN ($marks) GROUP BY layout_id, kind");
    $events->execute($ids);
    foreach ($events as $row) {
        $counts[$row['layout_id']][$row['kind'] === 'view' ? 'views' : 'downloads'] = (int) $row['total'];
    }
    $favorites = $db->prepare("SELECT layout_id, SUM(active) AS total FROM layout_favorites WHERE layout_id IN ($marks) GROUP BY layout_id");
    $favorites->execute($ids);
    foreach ($favorites as $row) {
        $counts[$row['layout_id']]['favorites'] = (int) $row['total'];
    }
    return $counts;
}

function json_response(array $data, int $status = 200): never
{
    http_response_code($status);
    header('Content-Type: application/json; charset=UTF-8');
    header('Cache-Control: no-store');
    echo json_encode($data, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);
    exit;
}

function stats_route(string $path): never
{
    if ($path === '/api/stats' && $_SERVER['REQUEST_METHOD'] === 'GET') {
        $raw = $_GET['ids'] ?? null;
        $ids = is_string($raw) && strlen($raw) <= 5050 ? array_values(array_unique(explode(',', $raw))) : [];
        if (!$ids || count($ids) > 50 || array_filter($ids, fn ($id) => !content_id($id))) {
            json_response(['error' => 'Invalid layout IDs'], 400);
        }
        json_response(['stats' => (object) stats(db(), $ids)]);
    }
    if ($path !== '/api/events' || $_SERVER['REQUEST_METHOD'] !== 'POST') {
        json_response(['error' => 'Method not allowed'], 405);
    }
    if (($_SERVER['HTTP_ORIGIN'] ?? '') !== rtrim(config()['base_url'], '/') ||
        !str_starts_with($_SERVER['CONTENT_TYPE'] ?? '', 'application/json')) {
        json_response(['error' => 'Invalid request origin or format'], 403);
    }
    $raw = file_get_contents('php://input', false, null, 0, 4097);
    $body = is_string($raw) && strlen($raw) <= 4096 ? json_decode($raw, true) : null;
    $id = $body['id'] ?? null;
    $visitor = $body['visitor'] ?? null;
    $type = $body['type'] ?? null;
    $active = $body['active'] ?? null;
    if (!content_id($id) || !is_string($visitor) ||
        !preg_match('/^[0-9a-f]{8}(?:-[0-9a-f]{4}){3}-[0-9a-f]{12}$/iD', $visitor) ||
        !in_array($type, ['view', 'copy', 'favorite'], true) ||
        ($type === 'favorite' && !is_bool($active))) {
        json_response(['error' => 'Invalid event'], 400);
    }
    $db = db();
    $layout = $db->prepare("SELECT payload FROM public_content WHERE kind = 'layout' AND id = ?");
    $layout->execute([$id]);
    $payload = $layout->fetchColumn();
    if ($payload === false || !empty(json_decode($payload, true)['hidden'])) {
        json_response(['error' => 'Layout not found'], 404);
    }
    $hash = hash('sha256', 'mahanpro-v1:' . $visitor);
    $time = (int) floor(microtime(true) * 1000);
    $limit = $db->prepare('SELECT COUNT(*) FROM layout_events WHERE visitor_hash = ? AND created_at > ?');
    $limit->execute([$hash, $time - 86400000]);
    if ((int) $limit->fetchColumn() >= 500 && $type !== 'favorite') {
        json_response(['error' => 'Too many events'], 429);
    }
    if ($type === 'favorite') {
        $write = $db->prepare('INSERT INTO layout_favorites (layout_id, visitor_hash, active, updated_at) VALUES (?, ?, ?, ?) ON DUPLICATE KEY UPDATE active = ?, updated_at = ?');
        $write->execute([$id, $hash, (int) $active, $time, (int) $active, $time]);
    } else {
        $bucket = $type === 'view' ? gmdate('Y-m-d', intdiv($time, 1000)) : (string) intdiv($time, 3600000);
        $write = $db->prepare('INSERT INTO layout_events (layout_id, visitor_hash, kind, bucket, created_at) VALUES (?, ?, ?, ?, ?) ON DUPLICATE KEY UPDATE created_at = created_at');
        $write->execute([$id, $hash, $type, $bucket, $time]);
    }
    json_response(['id' => $id, 'stats' => stats($db, [$id])[$id]]);
}
