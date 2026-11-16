<?php
declare(strict_types=1);

function config(): array
{
    static $values;
    if ($values === null) {
        $values = require __DIR__ . '/config.php';
        if (!preg_match('~^https://[a-z0-9.-]+/$~iD', $values['base_url'] ?? '')) {
            throw new RuntimeException('Invalid site address');
        }
    }
    return $values;
}

function db(): PDO
{
    $settings = config();
    return new PDO(
        'mysql:host=' . $settings['db_host'] . ';dbname=' . $settings['db_name'] . ';charset=utf8mb4',
        $settings['db_user'],
        $settings['db_password'],
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_EMULATE_PREPARES => false]
    );
}

function cms_state(PDO $db, bool $lock = false): array
{
    $row = $db->query('SELECT revision, document, updated_at FROM admin_content WHERE id = 1' . ($lock ? ' FOR UPDATE' : ''))->fetch(PDO::FETCH_ASSOC);
    if (!$row) {
        throw new RuntimeException('CMS schema not installed');
    }
    return [
        'revision' => (int) $row['revision'],
        'document' => json_decode($row['document'], false, 64, JSON_THROW_ON_ERROR),
        'updatedAt' => $row['updated_at'],
    ];
}

function content(PDO $db, string $kind, ?object $document = null, bool $includeHidden = false): array
{
    $query = $db->prepare('SELECT payload FROM public_content WHERE kind = ? ORDER BY sort_order, id');
    $query->execute([$kind]);
    $records = [];
    foreach ($query->fetchAll(PDO::FETCH_COLUMN) as $payload) {
        $row = json_decode($payload, true, 64, JSON_THROW_ON_ERROR);
        $records[$row['id']] = ['hidden' => false] + $row;
    }
    $document ??= cms_state($db)['document'];
    $key = $kind === 'layout' ? 'layoutOverrides' : 'guideOverrides';
    foreach ($document->$key as $id => $record) {
        $records[$id] = json_decode(json_encode($record, JSON_THROW_ON_ERROR), true, 64, JSON_THROW_ON_ERROR);
    }
    $rows = [];
    foreach ($records as $row) {
        if (!$includeHidden && !empty($row['hidden'])) {
            continue;
        }
        if ($kind === 'layout' && isset($row['image'])) {
            $legacy = 'https://ahmetozhans.github.io/coc/assets/layouts/';
            if (str_starts_with($row['image'], $legacy)) {
                $file = substr($row['image'], strlen($legacy));
                if (preg_match('~^[A-Za-z0-9_-]+\.(?:webp|png|jpg|jpeg)$~iD', $file)) {
                    $row['image'] = config()['base_url'] . 'assets/layouts/' . $file;
                }
            }
        }
        $rows[] = $row;
    }
    return $rows;
}

function h(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function https_link(mixed $url, ?string $host = null): string
{
    if (!is_string($url) || strlen($url) > 3000 || !filter_var($url, FILTER_VALIDATE_URL)) {
        return '';
    }
    $parts = parse_url($url);
    if (strtolower($parts['scheme'] ?? '') !== 'https' || isset($parts['user']) ||
        isset($parts['pass']) || ($host !== null && strtolower($parts['host'] ?? '') !== $host)) {
        return '';
    }
    return $url;
}

function game_link(mixed $url, string $action, string $field, string $pattern): string
{
    $url = https_link($url, 'link.clashofclans.com');
    if (!$url || strlen($url) > 3000) {
        return '';
    }
    $parts = parse_url($url);
    if (isset($parts['fragment']) || isset($parts['port'])) {
        return '';
    }
    $parameters = [];
    foreach (explode('&', $parts['query'] ?? '') as $pair) {
        [$key, $value] = array_pad(explode('=', $pair, 2), 2, '');
        $parameters[urldecode($key)][] = urldecode($value);
    }
    $values = $parameters[$field] ?? [];
    return ($parameters['action'] ?? []) === [$action] &&
        count($values) === 1 && preg_match($pattern, $values[0]) ? $url : '';
}
