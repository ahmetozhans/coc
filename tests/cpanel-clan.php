<?php
declare(strict_types=1);

require dirname(__DIR__) . '/cpanel/private/clan.php';

function verify(bool $expectation): void
{
    if (!$expectation) {
        throw new RuntimeException('Clan lookup test failed');
    }
}

verify(clan_tag(' #pyo8 ') === 'PY08');
verify(clan_tag('##PYL') === null && clan_tag(['PYL']) === null);
verify(clan_tag('https://localhost') === null);

$file = tempnam(sys_get_temp_dir(), 'clan-test-');
if ($file === false) {
    throw new RuntimeException('Could not create temporary cache');
}
try {
    $calls = 0;
    $fetch = static function ($tag, $token) use (&$calls) {
        ++$calls;
        return [200, ['tag' => '#' . $tag]];
    };
    verify(clan_lookup('PYL', 'unused', $fetch, $file) === [200, ['tag' => '#PYL']]);
    verify(clan_lookup('PYL', 'unused', $fetch, $file) === [200, ['tag' => '#PYL']] && $calls === 1);
    file_put_contents($file, json_encode(['entries' => [], 'attempts' => array_fill(0, 24, time())]));
    verify(clan_lookup('PYL', 'unused', $fetch, $file)[0] === 429 && $calls === 1);
} finally {
    unlink($file);
}
echo "Clan tag, caching and rate limit checks passed.\n";
