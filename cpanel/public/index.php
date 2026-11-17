<?php
declare(strict_types=1);

require dirname(__DIR__) . '/mahanpro-private/content.php';
require dirname(__DIR__) . '/mahanpro-private/stats.php';
require dirname(__DIR__) . '/mahanpro-private/reviews.php';
require dirname(__DIR__) . '/mahanpro-private/cms.php';

const PAGES = [
    '/' => ['index', 'Mahanpro | Clash of Clans Tools', 'Browse Clash of Clans village layouts, army builds and attack guides.'],
    '/bases' => ['bases', 'Clash of Clans Base Layouts | Mahanpro', 'Find Clash of Clans village layouts by Town Hall level and category.'],
    '/attacks' => ['attacks', 'Clash of Clans Attack Guides | Mahanpro', 'Explore attack guides and plan your next Clash of Clans attack.'],
    '/armies' => ['armies', 'Clash of Clans Army Layouts | Mahanpro', 'Find published army layouts and open their official Clash of Clans share links.'],
    '/clan' => ['clan', 'Clash of Clans Clan Finder | Mahanpro', 'Find your clan using its Clash of Clans clan tag.'],
    '/upgrade' => ['upgrade', 'Clash of Clans Builder Planner | Mahanpro', 'Plan Clash of Clans upgrades and organize your builders.'],
];

function cards(array $items, string $page): string
{
    $html = '';
    foreach ($items as $item) {
        if ($page === '/bases') {
            $townHall = (int) $item['th'];
            $url = game_link($item['url'] ?? null, 'OpenLayout', 'id', '/^TH' . $townHall . ':[A-Za-z0-9:_-]+$/D');
            $image = https_link($item['image'] ?? null);
            $title = h($item['title']);
            $html .= '<article class="layout-card"><div class="layout-visual">'
                . ($image ? '<img src="' . h($image) . '" alt="' . $title . '" loading="lazy">' : '')
                . '</div><div class="content-body"><h2>' . $title . '</h2><p>TH ' . $townHall
                . ' · ' . h($item['type']) . '</p>'
                . ($url ? '<a href="' . h($url) . '" class="button">Open in game</a>' : '')
                . '</div></article>';
            continue;
        }
        $army = game_link($item['armyUrl'] ?? null, 'CopyArmy', 'army', '/^[A-Za-z0-9:-]{1,2000}$/D');
        if ($page === '/armies' && !$army) {
            continue;
        }
        $html .= '<article class="guide-card" id="guide-' . h($item['id']) . '"><h2>'
            . h($item['title']['en']) . '</h2><p>' . h($item['summary']['en']) . '</p>';
        if ($page === '/attacks') {
            $html .= '<ol>';
            foreach ($item['steps']['en'] as $step) {
                $html .= '<li>' . h($step) . '</li>';
            }
            $html .= '</ol>';
        } else {
            $html .= '<a class="button" href="/attacks#guide-' . h($item['id']) . '">Read attack guide</a>';
        }
        if ($army) {
            $html .= '<a class="button" href="' . h($army) . '">Copy army to game</a>';
        }
        $html .= '</article>';
    }
    return $html ?: '<p class="empty">No published records are available yet.</p>';
}

$path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
$normalized = $path === '/index.php' || $path === '/index.html'
    ? '/' : preg_replace('~(?:\.html|/)$~', '', $path);
if (isset(PAGES[$normalized]) && $path !== $normalized) {
    header('Location: ' . $normalized, true, 301);
    exit;
}

try {
    $base = config()['base_url'];
    if ($path === '/api/clan') {
        require dirname(__DIR__) . '/mahanpro-private/clan.php';
        clan_route();
    }
    if ($path === '/admin.html') {
        header('Location: /admin', true, 301);
        exit;
    }
    if ($path === '/admin') {
        admin_route();
    }
    if ($path === '/studio') {
        studio_route();
    }
    if ($path === '/api/admin/content') {
        cms_route();
    }
    if (in_array($path, ['/api/stats', '/api/events'], true)) {
        stats_route($path);
    }
    if ($path === '/robots.txt') {
        header('Content-Type: text/plain; charset=UTF-8');
        echo "User-agent: *\nAllow: /\nSitemap: " . $base . "sitemap.xml\n";
        exit;
    }
    if ($path === '/sitemap.xml') {
        header('Content-Type: application/xml; charset=UTF-8');
        echo '<?xml version="1.0" encoding="UTF-8"?><urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">';
        foreach (array_keys(PAGES) as $route) {
            echo '<url><loc>' . h($base . ltrim($route, '/')) . '</loc></url>';
        }
        echo '</urlset>';
        exit;
    }
    if ($path === '/api/content') {
        if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
            header('Allow: GET');
            http_response_code(405);
            exit;
        }
        $pdo = db();
        $state = cms_state($pdo);
        $doc = $state['document'];
        $data = json_encode([
            'version' => 1,
            'revision' => $state['revision'],
            'site' => $doc->site,
            'layouts' => content($pdo, 'layout', $doc),
            'guides' => content($pdo, 'guide', $doc),
        ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);
        header('Content-Type: application/json; charset=UTF-8');
        echo $data;
        exit;
    }
    if (!isset(PAGES[$path])) {
        http_response_code(404);
        echo 'Page not found';
        exit;
    }
    [$file, $title, $description] = PAGES[$path];
    $pdo = db();
    $doc = cms_state($pdo)['document'];
    $settings = $doc->site->pages->{ $file === 'index' ? 'home' : $file } ?? null;
    if (!empty($doc->site->maintenance) || ($settings->enabled ?? true) === false) {
        http_response_code(!empty($doc->site->maintenance) ? 503 : 404);
        header('X-Robots-Tag: noindex');
        header('Content-Type: text/html; charset=UTF-8');
        if (!empty($doc->site->maintenance)) {
            header('Retry-After: 300');
        }
        echo '<!doctype html><html lang="tr"><meta charset="utf-8"><title>Mahanpro</title><h1>Sayfa geçici olarak kullanılamıyor.</h1><p>' . h($doc->site->maintenanceText->tr ?? '') . '</p>';
        exit;
    }
    $title = ($settings->seoTitle ?? '') ?: $title;
    $description = ($settings->seoDescription ?? '') ?: $description;
    $template = file_get_contents(dirname(__DIR__) . '/mahanpro-private/templates/' . $file . '.html');
    if ($template === false) {
        throw new RuntimeException('Page template is missing');
    }
    $template = preg_replace_callback('~<title>.*?</title>~s', fn () => '<title>' . h($title) . '</title>', $template, 1);
    $template = preg_replace_callback('~<meta name="description" content="[^"]*">~',
        fn () => '<meta name="description" content="' . h($description) . '">', $template, 1);
    $template = preg_replace('~<link rel="canonical" href="[^"]*">~',
        '<link rel="canonical" href="' . h($base . ltrim($path, '/')) . '">', $template, 1);
    if (in_array($path, ['/bases', '/attacks', '/armies'], true)) {
        $records = content($pdo, $path === '/bases' ? 'layout' : 'guide', $doc);
        $template = str_replace('<!-- MAHANPRO_CONTENT -->', cards($records, $path), $template);
    }
    header('Content-Type: text/html; charset=UTF-8');
    echo $template;
} catch (Throwable $error) {
    error_log('Mahanpro: request failed (' . get_class($error) . ')');
    if (str_starts_with($path, '/api/')) {
        json_response(['error' => 'Hizmet geçici olarak kullanılamıyor.'], 503);
    }
    http_response_code(503);
    header('Retry-After: 300');
    header('Content-Type: text/plain; charset=UTF-8');
    echo 'Mahanpro geçici olarak kullanılamıyor.';
}
