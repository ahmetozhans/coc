<?php
declare(strict_types=1);

const COLLECTOR_AGENT = 'MahanproCollector/1.0 (+https://mahanpro.fun/)';

function collector_sources(): array
{
    return [
        'finder' => ['name' => 'CoC Base Finder', 'origin' => 'https://cocbasefinder.com',
            'images' => ['img.cocbasefinder.com'], 'path' => '/th%d', 'field' => 'initialBases'],
        'loot' => ['name' => 'Clash Loot', 'origin' => 'https://clashloot.gg',
            'images' => ['assets.clashloot.gg'], 'path' => '/coc/bases/th-%d', 'field' => 'initialBases'],
        'clashly' => ['name' => 'ClashLy', 'origin' => 'https://clashly.app',
            'images' => ['storage.clashly.app'], 'path' => '/bases/th%d?sort=new', 'field' => 'initial'],
    ];
}

function collector_absolute_url(string $value, string $base): string
{
    if (str_starts_with($value, '/')
        && !str_starts_with($value, '//')) {
        return rtrim($base, '/') . $value;
    }
    return $value;
}

function collector_allowed_url(string $url, array $hosts): array
{
    $parts = parse_url($url);
    if (!https_link($url) || !in_array(strtolower($parts['host'] ?? ''), $hosts, true)
        || isset($parts['port']) || isset($parts['fragment'])) {
        throw new RuntimeException('Kaynak adresi izin kapsamı dışında.');
    }
    return $parts;
}

function collector_robots_allowed(string $body, string $path): bool
{
    $groups = [];
    $agents = [];
    $rules = [];
    $flush = static function () use (&$groups, &$agents, &$rules): void {
        if ($agents) {
            $groups[] = [$agents, $rules];
        }
        $agents = [];
        $rules = [];
    };
    foreach (preg_split('/\r?\n/', $body) as $line) {
        $line = trim(explode('#', $line, 2)[0]);
        if (!str_contains($line, ':')) {
            continue;
        }
        [$key, $value] = array_map('trim', explode(':', $line, 2));
        $key = strtolower($key);
        if ($key === 'user-agent') {
            if ($rules) {
                $flush();
            }
            $agents[] = strtolower($value);
        } elseif (in_array($key, ['allow', 'disallow', 'crawl-delay'], true)) {
            $rules[] = [$key, $value];
        }
    }
    $flush();
    $specific = array_filter($groups, static fn ($g) => in_array('mahanprocollector', $g[0], true));
    $selected = $specific ?: array_filter($groups, static fn ($g) => in_array('*', $g[0], true));
    $length = -1;
    $allowed = true;
    foreach ($selected as [, $entries]) {
        foreach ($entries as [$key, $pattern]) {
            if ($key === 'crawl-delay' || $pattern === '') {
                continue;
            }
            $regex = '~^' . str_replace(['\\*', '\\$'], ['.*', '$'], preg_quote($pattern, '~')) . '~';
            if (preg_match($regex, $path) && (strlen($pattern) > $length
                || (strlen($pattern) === $length && $key === 'allow'))) {
                $length = strlen($pattern);
                $allowed = $key === 'allow';
            }
        }
    }
    return $allowed;
}

class CollectorHttp
{
    private array $robots = [];
    private array $nextRequest = [];
    private float $deadline;

    public function __construct(int $seconds = 240)
    {
        $this->deadline = microtime(true) + $seconds;
    }

    private function raw(string $url, array $hosts, int $limit): array
    {
        $parts = collector_allowed_url($url, $hosts);
        $host = strtolower($parts['host']);
        // Resolve and pin a public address. Redirects are checked afresh.
        $ips = gethostbynamel($host) ?: [];
        $ip = $ips[0] ?? '';
        if (!filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
            throw new RuntimeException('Kaynak DNS adresi herkese açık değil.');
        }
        if (microtime(true) >= $this->deadline) {
            throw new RuntimeException('Tarama süre sınırına ulaştı.');
        }
        $wait = ($this->nextRequest[$host] ?? 0) - microtime(true);
        if ($wait > 0) {
            usleep((int) (min($wait, 30) * 1000000));
        }
        $body = '';
        $headers = [];
        $curl = curl_init($url);
        curl_setopt_array($curl, [
            CURLOPT_PROTOCOLS => CURLPROTO_HTTPS, CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_PROXY => '', CURLOPT_RESOLVE => ["$host:443:$ip"],
            CURLOPT_USERAGENT => COLLECTOR_AGENT, CURLOPT_CONNECTTIMEOUT => 6,
            CURLOPT_TIMEOUT => max(1, min(15, (int) ($this->deadline - microtime(true)))),
            CURLOPT_WRITEFUNCTION => static function ($handle, string $chunk) use (&$body, $limit): int {
                if (strlen($body) + strlen($chunk) > $limit) {
                    return 0;
                }
                $body .= $chunk;
                return strlen($chunk);
            },
            CURLOPT_HEADERFUNCTION => static function ($handle, string $line) use (&$headers): int {
                if (str_contains($line, ':')) {
                    [$key, $value] = explode(':', $line, 2);
                    $headers[strtolower(trim($key))] = trim($value);
                }
                return strlen($line);
            },
        ]);
        $ok = curl_exec($curl);
        $status = curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
        curl_close($curl);
        $this->nextRequest[$host] = microtime(true) + 2;
        if ($ok === false) {
            throw new RuntimeException('Kaynak bağlantısı veya boyut sınırı hatası.');
        }
        return [$status, $body, $headers];
    }

    public function get(string $url, array $hosts, int $limit = 4000000): string
    {
        for ($redirect = 0; $redirect < 4; $redirect++) {
            $parts = collector_allowed_url($url, $hosts);
            $origin = 'https://' . $parts['host'];
            if (!isset($this->robots[$origin])) {
                [$status, $body] = $this->raw($origin . '/robots.txt', $hosts, 100000);
                if ($status !== 200 && $status !== 404) {
                    throw new RuntimeException('robots.txt okunamadı (' . $status . ').');
                }
                $this->robots[$origin] = $status === 404 ? '' : $body;
                if (preg_match_all('/^Crawl-delay:\s*(\d+)/mi', $body, $delays)) {
                    $delay = max(array_map('intval', $delays[1]));
                    if ($delay > 30) {
                        throw new RuntimeException('Kaynak tarama bekleme süresi bu çalışma sınırını aşıyor.');
                    }
                    $this->nextRequest[$parts['host']] = microtime(true) + max(2, $delay);
                }
            }
            if (!collector_robots_allowed($this->robots[$origin], ($parts['path'] ?? '/') . (isset($parts['query']) ? '?' . $parts['query'] : ''))) {
                throw new RuntimeException('Kaynak robots.txt bu yola izin vermiyor.');
            }
            for ($attempt = 0; $attempt < 3; $attempt++) {
                [$status, $body, $headers] = $this->raw($url, $hosts, $limit);
                if (!in_array($status, [429, 500, 502, 503, 504], true)) {
                    break;
                }
                $retry = $headers['retry-after'] ?? '';
                $delay = ctype_digit($retry) ? (int) $retry : ($retry !== '' ? max(0, (strtotime($retry) ?: time()) - time()) : 2 ** ($attempt + 1));
                if ($delay > 20 || microtime(true) + $delay >= $this->deadline) {
                    throw new RuntimeException('Kaynak hız sınırı, sonraki günlük çalışmada yeniden denenecek.');
                }
                sleep($delay);
            }
            if (in_array($status, [301, 302, 303, 307, 308], true)) {
                $url = collector_absolute_url($headers['location'] ?? '', $origin);
                continue;
            }
            if ($status !== 200) {
                throw new RuntimeException('Kaynak HTTP ' . $status . '.');
            }
            return $body;
        }
        throw new RuntimeException('Kaynak yönlendirme sınırı aşıldı.');
    }
}

// Only decode public server-rendered JSON. Never execute scripts from a source.
function collector_rows(string $html, string $source): array
{
    $data = $html;
    preg_match_all('~self\.__next_f\.push\((\[.*?\])\)</script>~s', $html, $matches);
    foreach ($matches[1] as $chunk) {
        $parsed = json_decode($chunk, true);
        if (is_string($parsed[1] ?? null)) {
            $data .= $parsed[1];
        }
    }
    $field = collector_sources()[$source]['field'];
    $offset = strpos($data, '"' . $field . '":[');
    if ($offset === false) {
        throw new RuntimeException('Kaynak sayfa biçimi değişmiş, kayıt listesi bulunamadı.');
    }
    $start = strpos($data, '[', $offset);
    $depth = 0;
    $quoted = false;
    $escaped = false;
    for ($i = $start; $i < strlen($data); $i++) {
        $char = $data[$i];
        if ($quoted) {
            if ($escaped) {
                $escaped = false;
            } elseif ($char === '\\') {
                $escaped = true;
            } elseif ($char === '"') {
                $quoted = false;
            }
            continue;
        }
        if ($char === '"') {
            $quoted = true;
        } elseif ($char === '[') {
            $depth++;
        } elseif ($char === ']' && --$depth === 0) {
            return json_decode(substr($data, $start, $i - $start + 1), true, 64, JSON_THROW_ON_ERROR);
        }
    }
    throw new RuntimeException('Kaynak JSON listesi eksik.');
}

function collector_category(string $value): ?string
{
    return match (strtolower($value)) {
        'war', 'cwl' => 'War', 'farming', 'farm' => 'Farming',
        'trophy', 'ranked', 'legend' => 'Trophy', 'hybrid' => 'Hybrid', default => null,
    };
}

function collector_normalize(array $row, string $source, string $html): ?array
{
    $spec = collector_sources()[$source];
    $key = (string) ($row['id'] ?? '');
    $hall = $row['thLevel'] ?? $row['hall'] ?? ('TH' . ($row['level'] ?? ''));
    if (!preg_match('/^TH(\d+)$/iD', (string) $hall, $match)
        || ($row['village_type'] ?? 'th') !== 'th') {
        return null;
    }
    $th = (int) $match[1];
    $type = collector_category((string) ($row['category'] ?? $row['baseTag'] ?? ''));
    if ($th < 3 || $th > 19 || $type === null) {
        return null;
    }
    $url = $row['copyUrl'] ?? $row['link'] ?? $row['shareUrl'] ?? '';
    $gameId = collector_game_id($url, $th);
    if ($key === '' || strlen($key) > 160) {
        throw new RuntimeException('Kaynak kayıt kimliği geçersiz.');
    }
    $image = (string) ($row['imageUrl'] ?? $row['image_url'] ?? '');
    collector_allowed_url($image, $spec['images']);
    $page = match ($source) {
        'finder' => '/base/' . rawurlencode($key),
        'clashly' => '/layout/' . rawurlencode($key),
        default => '',
    };
    if ($source === 'loot') {
        preg_match('~href="(/coc/bases/[^"]*' . preg_quote($key, '~') . ')"~', $html, $m);
        $page = html_entity_decode($m[1] ?? '', ENT_QUOTES | ENT_HTML5);
        if ($page === '') {
            throw new RuntimeException('Kaynak detay adresi bulunamadı.');
        }
    }
    $published = $row['dateAdded'] ?? $row['created_at'] ?? $row['createdAt'] ?? null;
    if ($published !== null && (!is_string($published) || !preg_match('/^\d{4}-\d{2}-\d{2}(?:T[^ ]+)?$/D', $published) || strtotime($published) === false)) {
        throw new RuntimeException('Kaynak yayın tarihi geçersiz.');
    }
    $sourceTitle = (string) ($row['title'] ?? "TH$th " . ($row['baseTag'] ?? $row['category'] ?? '') . ' ' . $key);
    $features = [];
    foreach ([
        'Central Town Hall' => '/central(?:ized)?[ -]+town hall/i',
        'Offset Town Hall' => '/offset[ -]+town hall/i',
        'Compartments' => '/compartment/i',
        'Diamond Shape' => '/diamond/i',
        'Symmetry' => '/symmetr/i',
        'Anti Air' => '/anti[ -]+air|anti[ -]+dragon/i',
        'Anti 3 Star' => '/anti[ -]+3[ -]+star/i',
    ] as $feature => $pattern) {
        if (preg_match($pattern, $sourceTitle)) {
            $features[] = $feature;
        }
    }
    return ['key' => $key, 'th' => $th, 'type' => $type, 'url' => $url, 'gameId' => $gameId,
        'imageUrl' => $image, 'sourceUrl' => $spec['origin'] . $page, 'sourcePublishedAt' => $published,
        'sourceTitle' => $sourceTitle, 'sourceFeatures' => $features,
        'sourceUpdatedAt' => $row['updated_at'] ?? null];
}

function collector_game_id(mixed $url, int $th): string
{
    if (!game_link($url, 'OpenLayout', 'id', '/^TH' . $th . ':(?:HV|WB):[A-Za-z0-9_-]{10,}$/D')) {
        throw new RuntimeException('Geçersiz OpenLayout bağlantısı veya TH uyuşmazlığı.');
    }
    $parts = parse_url($url);
    parse_str($parts['query'], $query);
    if (!preg_match('~^/[a-z]{2}/?$~D', $parts['path'] ?? '') || array_diff(array_keys($query), ['action', 'id'])) {
        throw new RuntimeException('Oyun bağlantısının yolu veya parametreleri geçersiz.');
    }
    return $query['id'];
}

function collector_image(string $bytes): array
{
    $size = getimagesizefromstring($bytes);
    if (!$size || !in_array($size['mime'], ['image/webp', 'image/png', 'image/jpeg'], true)
        || $size[0] < 400 || $size[1] < 300 || $size[0] * $size[1] > 16000000) {
        throw new RuntimeException('Görsel bozuk, küçük veya desteklenmeyen biçimde.');
    }
    $image = imagecreatefromstring($bytes);
    if (!$image) {
        throw new RuntimeException('Görsel çözümlenemedi.');
    }
    $small = imagecreatetruecolor(9, 8);
    imagecopyresampled($small, $image, 0, 0, 0, 0, 9, 8, $size[0], $size[1]);
    $bits = '';
    for ($y = 0; $y < 8; $y++) {
        for ($x = 0; $x < 8; $x++) {
            $gray = static function (int $color): float {
                return (($color >> 16) & 255) * .299 + (($color >> 8) & 255) * .587 + ($color & 255) * .114;
            };
            $bits .= $gray(imagecolorat($small, $x, $y)) > $gray(imagecolorat($small, $x + 1, $y)) ? '1' : '0';
        }
    }
    $dhash = '';
    foreach (str_split($bits, 4) as $nibble) {
        $dhash .= dechex(bindec($nibble));
    }
    // Keep full dimensions and creator marks. Re-encode without cropping.
    ob_start();
    $ok = imagewebp($image, null, 84);
    $webp = ob_get_clean();
    imagedestroy($small);
    imagedestroy($image);
    if (!$ok || !$webp) {
        throw new RuntimeException('WebP kodlama başarısız.');
    }
    if ($size['mime'] === 'image/webp' && strlen($bytes) < strlen($webp)) {
        $webp = $bytes;
    }
    return ['bytes' => $webp, 'sha256' => hash('sha256', $webp),
        'sourceSha256' => hash('sha256', $bytes), 'dhash' => $dhash];
}

function collector_similar(string $a, string $b): bool
{
    $distance = 0;
    for ($i = 0; $i < 16; $i++) {
        $distance += substr_count(decbin(hexdec($a[$i]) ^ hexdec($b[$i])), '1');
    }
    return $distance <= 3;
}
