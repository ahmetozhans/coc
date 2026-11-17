<?php
declare(strict_types=1);

function clan_tag(mixed $input): ?string
{
    if (!is_string($input) || strlen($input) > 40) {
        return null;
    }
    $tag = str_replace('O', '0', strtoupper(trim($input)));
    if (str_starts_with($tag, '#')) {
        $tag = substr($tag, 1);
    }
    return preg_match('/^[0289PYLQGRJCUV]{3,15}$/D', $tag) === 1 ? $tag : null;
}

function supercell_clan(string $tag, string $token): array
{
    $body = '';
    $handle = curl_init('https://api.clashofclans.com/v1/clans/' . rawurlencode('#' . $tag));
    curl_setopt_array($handle, [
        CURLOPT_HTTPHEADER => ['Authorization: Bearer ' . $token, 'Accept: application/json'],
        CURLOPT_FOLLOWLOCATION => false,
        CURLOPT_CONNECTTIMEOUT => 3,
        CURLOPT_TIMEOUT => 7,
        CURLOPT_WRITEFUNCTION => static function ($handle, string $chunk) use (&$body): int {
            if (strlen($body) + strlen($chunk) > 1048576) {
                return 0;
            }
            $body .= $chunk;
            return strlen($chunk);
        },
    ]);
    $ok = curl_exec($handle);
    $status = curl_getinfo($handle, CURLINFO_RESPONSE_CODE);
    curl_close($handle);
    if ($ok === false) {
        return [502, ['error' => 'unavailable']];
    }
    if ($status !== 200) {
        return match ($status) {
            404 => [404, ['error' => 'not_found']],
            429 => [429, ['error' => 'busy']],
            401, 403 => [503, ['error' => 'api_access']],
            default => [502, ['error' => 'unavailable']],
        };
    }
    $data = json_decode($body, true);
    if (!is_string($data['name'] ?? null) || ($data['tag'] ?? null) !== '#' . $tag ||
        !is_int($data['clanLevel'] ?? null) || !is_int($data['members'] ?? null) ||
        !is_int($data['clanPoints'] ?? null)) {
        return [502, ['error' => 'unavailable']];
    }
    return [200, [
        'name' => $data['name'], 'tag' => $data['tag'],
        'level' => $data['clanLevel'], 'members' => $data['members'], 'points' => $data['clanPoints'],
    ]];
}

function clan_lookup(string $tag, string $token, ?callable $fetch = null, ?string $cachePath = null): array
{
    $fetch ??= 'supercell_clan';
    $cache = @fopen($cachePath ?? __DIR__ . '/.clan-cache.json', 'c+');
    if ($cache === false) {
        return [503, ['error' => 'unavailable']];
    }
    try {
        if (!flock($cache, LOCK_EX | LOCK_NB)) {
            return [429, ['error' => 'busy']];
        }
        $now = time();
        $state = json_decode(stream_get_contents($cache, 262144), true);
        $entries = array_filter($state['entries'] ?? [], static fn ($entry) => $entry['expires'] > $now);
        $attempts = array_values(array_filter($state['attempts'] ?? [], static fn ($time) => $time > $now - 60));
        if (isset($entries[$tag])) {
            return $entries[$tag]['result'];
        }
        if (count($attempts) >= 24) {
            return [429, ['error' => 'busy']];
        }
        $attempts[] = $now;
        // Reserve this request before the network call so repeated failures still count.
        $save = static function () use ($cache, &$entries, $attempts): bool {
            $bytes = json_encode(['entries' => $entries, 'attempts' => $attempts], JSON_THROW_ON_ERROR);
            return rewind($cache) && ftruncate($cache, 0) &&
                fwrite($cache, $bytes) === strlen($bytes) && fflush($cache);
        };
        if (!$save()) {
            return [503, ['error' => 'unavailable']];
        }
        $result = $fetch($tag, $token);
        if (in_array($result[0], [200, 404], true)) {
            $entries[$tag] = ['expires' => $now + ($result[0] === 200 ? 120 : 30), 'result' => $result];
            $save();
        }
        return $result;
    } finally {
        fclose($cache);
    }
}

function clan_route(): never
{
    if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'GET') {
        header('Allow: GET');
        json_response(['error' => 'method_not_allowed'], 405);
    }
    $tag = clan_tag($_GET['tag'] ?? null);
    if ($tag === null) {
        json_response(['error' => 'invalid_tag'], 400);
    }
    $token = config()['clash_api_token'] ?? '';
    if (!is_string($token) || !preg_match('/^[A-Za-z0-9._~-]{1,4096}$/D', $token) || !extension_loaded('curl')) {
        json_response(['error' => 'not_configured'], 503);
    }
    [$status, $data] = clan_lookup($tag, $token);
    if ($status === 429) {
        header('Retry-After: 30');
    }
    json_response($data, $status);
}
