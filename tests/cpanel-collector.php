<?php
declare(strict_types=1);

// Run only against an empty, disposable MySQL/MariaDB database.
$private = $argv[1] ?? '';
require $private . '/content.php';
require $private . '/stats.php';
require $private . '/reviews.php';
require $private . '/cms.php';
require $private . '/layout-detail.php';
require $private . '/collector-admin.php';
$pdo = new PDO(getenv('COLLECTOR_TEST_DSN'), 'root', '', [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_EMULATE_PREPARES => false]);
$other = new PDO(getenv('COLLECTOR_TEST_DSN'), 'root', '', [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_EMULATE_PREPARES => false]);

function expect(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}
function fails(callable $fn, string $part): void
{
    try {
        $fn();
    } catch (Throwable $e) {
        expect(str_contains($e->getMessage(), $part), 'Wrong error: ' . $e->getMessage());
        return;
    }
    throw new RuntimeException('Expected failure: ' . $part);
}
$source = dirname(__DIR__);
expect((int) $pdo->query('SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE()')->fetchColumn() === 0, 'Test database must be empty.');
$pdo->exec(file_get_contents($source . '/cpanel/database/schema.sql'));
$migration = file_get_contents($source . '/cpanel/database/migrations/20261118-layout-collector.sql');
$pdo->exec($migration);
$pdo->exec($migration);
expect(collector_settings($pdo)['revision'] === 0, 'Migration must be repeatable.');
$fixtures = __DIR__ . '/fixtures/collector/';
$provenance = json_decode(file_get_contents($fixtures . 'provenance.json'), true, 64, JSON_THROW_ON_ERROR);
$normalized = [];
foreach (collector_sources() as $id => $spec) {
    $row = json_decode(file_get_contents($fixtures . $id . '.json'), true, 64, JSON_THROW_ON_ERROR);
    $list = json_encode([$row], JSON_THROW_ON_ERROR);
    $html = '<script>self.__next_f.push(' . json_encode([1, '{"' . $spec['field'] . '":' . $list . '}']) . ')</script>';
    if ($id === 'loot') {
        $html .= '<a href="' . parse_url($provenance['loot'][0]['sourceUrl'], PHP_URL_PATH) . '">Preview</a>';
    }
    $rows = collector_rows($html, $id);
    $normalized[$id] = collector_normalize($rows[0], $id, $html);
    expect($normalized[$id]['th'] === 19, 'Real fixture TH19');
}
fails(fn () => collector_rows('<p>Access denied</p>', 'finder'), 'biçimi');
expect(collector_robots_allowed("User-agent: *\nDisallow: /api/\nAllow: /api/thumb/", '/api/thumb/one'), 'Robots allow longest match');
expect(!collector_robots_allowed("User-agent: *\nDisallow: /api/", '/api/secret'), 'Robots denied');
expect(!collector_robots_allowed("User-agent: *\nAllow: /\nUser-agent: MahanproCollector\nDisallow: /", '/'), 'Specific robots group');
foreach ([
    str_replace('https://', 'http://', $normalized['loot']['url']),
    $normalized['loot']['url'] . '&id=TH19:WB:AAAA0000000000',
    str_replace('TH19', 'TH18', $normalized['loot']['url']),
    str_replace('link.clashofclans.com', 'link.clashofclans.com.evil.test', $normalized['loot']['url']),
] as $bad) {
    fails(fn () => collector_game_id($bad, 19), 'Geçersiz');
}
fails(fn () => collector_allowed_url('https://127.0.0.1/one', ['clashly.app']), 'izin');
$image = collector_image(file_get_contents($fixtures . 'loot.webp'));
expect(str_starts_with($image['bytes'], 'RIFF'), 'Real screenshot becomes WebP');
expect(strlen($image['sha256']) === 64, 'Image fingerprint');
fails(fn () => collector_image('broken-image'), 'Görsel');
collector_store_image($image);
$record = $normalized['loot'];
expect(collector_queue($pdo, $record, 'loot', $image) === 'pending', 'Queue real record');
expect(collector_queue($pdo, $record, 'loot', $image) === 'unchanged', 'No unchanged requeue');
expect((int) $pdo->query('SELECT COUNT(*) FROM public_content')->fetchColumn() === 0, 'Discovery cannot publish');
$row = $pdo->query('SELECT * FROM collector_proposals')->fetch(PDO::FETCH_ASSOC);
$selection = [$row['id'] => $row['fingerprint']];
fails(fn () => collector_review($pdo, $selection, 'publish', false), 'ekran görüntüleri');
fails(fn () => collector_review($pdo, [$row['id'] => str_repeat('0', 64)], 'publish', true), 'değişti');
collector_edit_proposal($pdo, (int) $row['id'], $row['fingerprint'], 'TH19 resource protection base', 'Review this layout in a friendly challenge before using it.');
fails(fn () => collector_review($pdo, $selection, 'publish', true), 'değişti');
$row = $pdo->query('SELECT * FROM collector_proposals')->fetch(PDO::FETCH_ASSOC);
$selection = [$row['id'] => $row['fingerprint']];
$preview = collector_image_path($image['sha256']);
$bytes = file_get_contents($preview);
file_put_contents($preview, 'tampered');
fails(fn () => collector_review($pdo, $selection, 'publish', true), 'parmak izi');
expect((int) $pdo->query('SELECT COUNT(*) FROM public_content')->fetchColumn() === 0, 'Tampered image cannot publish');
file_put_contents($preview, $bytes);
collector_review($pdo, $selection, 'publish', true);
$published = content($pdo, 'layout')[0];
expect($published['inGameVerifiedAt'] === null, 'HTTP never marks gameplay verified');
expect($published['approvalFingerprint'] === $row['fingerprint'], 'Exact approval stored');
fails(fn () => collector_review($pdo, $selection, 'publish', true), 'işlendi');
$changedSource = $record;
$changedSource['sourceUpdatedAt'] = '2026-11-18T16:00:00Z';
expect(collector_queue($pdo, $changedSource, 'loot', $image) === 'pending', 'Changed source creates review');
$changedReview = $pdo->query("SELECT * FROM collector_proposals WHERE status = 'pending' ORDER BY id DESC LIMIT 1")->fetch(PDO::FETCH_ASSOC);
collector_review($pdo, [$changedReview['id'] => $changedReview['fingerprint']], 'publish', true);
$published = content($pdo, 'layout')[0];
expect($published['id'] === $row['content_id'], 'Source update keeps stable ID');
expect((int) $pdo->query('SELECT COUNT(*) FROM public_content')->fetchColumn() === 1, 'Source update does not duplicate public record');
$duplicate = $record;
$duplicate['key'] = 'same-link-other-id';
expect(collector_queue($pdo, $duplicate, 'loot', $image) === 'duplicate', 'Decoded game ID dedupe');
$duplicate['gameId'] = 'TH19:WB:AAAA111111111111';
$duplicate['url'] = 'https://link.clashofclans.com/en?action=OpenLayout&id=' . urlencode($duplicate['gameId']);
$duplicate['key'] = 'same-image-new-link';
expect(collector_queue($pdo, $duplicate, 'loot', $image) === 'duplicate', 'Screenshot dedupe');
$before = collector_hash($published);
$pdo->prepare("INSERT INTO layout_events VALUES (?, ?, 'view', 'test-bucket', 1)")->execute([$published['id'], str_repeat('1', 64)]);
$pdo->prepare('INSERT INTO layout_favorites VALUES (?, ?, 1, 1)')->execute([$published['id'], str_repeat('1', 64)]);
collector_verify_in_game($pdo, $published['id'], $before, true);
$verified = content($pdo, 'layout')[0];
expect(!empty($verified['inGameVerifiedAt']), 'Explicit gameplay confirmation saved');
collector_archive($pdo, $verified['id'], collector_hash($verified));
expect(count(content($pdo, 'layout')) === 0, 'Archive removes from active gallery');
$archived = content($pdo, 'layout', null, true)[0];
expect(layout_detail_selection($archived['id'], [$archived]) !== null, 'Archive preserves detail URL');
expect(stats($pdo, [$archived['id']])[$archived['id']]['views'] === 1, 'Archive preserves views');
expect(stats($pdo, [$archived['id']])[$archived['id']]['favorites'] === 1, 'Archive preserves favorites');
// Restore through the same CMS path, then fill a TH with existing stable IDs.
$state = cms_state($pdo);
$doc = $state['document'];
unset($doc->layoutOverrides->{$archived['id']});
$pdo->prepare('UPDATE admin_content SET document = ? WHERE id = 1')->execute([json_encode($doc)]);
for ($i = 1; $i < 20; $i++) {
    $item = $published;
    $item['id'] = 'existing-' . $i;
    $item['url'] = 'https://link.clashofclans.com/en?action=OpenLayout&id=TH19:WB:AAAA00000000' . $i;
    $item['image'] = '';
    unset($item['imageDhash'], $item['imageSha256']);
    $pdo->prepare("INSERT INTO public_content VALUES ('layout', ?, ?, ?)")->execute([$item['id'], $i, json_encode($item)]);
}
expect(collector_counts(content($pdo, 'layout'))[19] === 20, '20 active rows');
$candidate = $record;
$candidate['key'] = 'over-limit';
$candidate['gameId'] = 'TH19:WB:AAAA999999999999';
$candidate['url'] = 'https://link.clashofclans.com/en?action=OpenLayout&id=' . urlencode($candidate['gameId']);
$image2 = collector_image(file_get_contents(getenv('COLLECTOR_SECOND_IMAGE') ?: $fixtures . 'loot-second.webp'));
collector_store_image($image2);
collector_queue($pdo, $candidate, 'loot', $image2);
$row2 = $pdo->query("SELECT * FROM collector_proposals WHERE source_key = 'over-limit'")->fetch(PDO::FETCH_ASSOC);
fails(fn () => collector_review($pdo, [$row2['id'] => $row2['fingerprint']], 'publish', true), '20 aktif');
expect(collector_counts(content($pdo, 'layout'))[19] === 20, 'Limit failure rolls back');
collector_review($pdo, [$row2['id'] => $row2['fingerprint']], 'reject', false);
expect(collector_queue($pdo, $candidate, 'loot', $image2) === 'unchanged', 'Rejected unchanged version stays rejected');
// A newer source version can be proposed, but a manual override prevents overwriting it.
$updated = $record;
$updated['sourceUpdatedAt'] = '2026-11-18T18:00:00Z';
expect(collector_queue($pdo, $updated, 'loot', $image) === 'pending', 'Updated source has a new review');
$update = $pdo->query("SELECT * FROM collector_proposals WHERE status = 'pending' ORDER BY id DESC LIMIT 1")->fetch(PDO::FETCH_ASSOC);
$olderVersion = $update;
$updated['sourceUpdatedAt'] = '2026-11-18T19:00:00Z';
collector_queue($pdo, $updated, 'loot', $image);
fails(fn () => collector_review($pdo, [$olderVersion['id'] => $olderVersion['fingerprint']], 'publish', true), 'işlendi');
$update = $pdo->query("SELECT * FROM collector_proposals WHERE status = 'pending' ORDER BY id DESC LIMIT 1")->fetch(PDO::FETCH_ASSOC);

$doc = cms_state($pdo)['document'];
$doc->layoutOverrides->{$published['id']} = (object) $published;
$pdo->prepare('UPDATE admin_content SET document = ? WHERE id = 1')->execute([json_encode($doc)]);
fails(fn () => collector_review($pdo, [$update['id'] => $update['fingerprint']], 'publish', true), 'yönetici');
expect((int) $other->query("SELECT GET_LOCK('mahanpro-layout-collector', 0)")->fetchColumn() === 1, 'Acquire competing scan lock');
fails(fn () => collector_scan($pdo, 'manual'), 'tarama devam');
$other->query("SELECT RELEASE_LOCK('mahanpro-layout-collector')");
$pdo->prepare('UPDATE collector_settings SET last_discovery = ? WHERE id = 1')->execute([gmdate('Y-m-d')]);
expect(collector_scan($pdo, 'manual')['skipped'], 'Daily limit persists across connections');
// CMS entry point capacity guard.
$doc = cms_state($pdo)['document'];
$extra = (object) $published;
$extra->id = 'manual-over-cap';
$extra->url = 'https://link.clashofclans.com/en?action=OpenLayout&id=TH19:WB:AAAAManual1111';
$doc->layoutOverrides->{$extra->id} = $extra;
fails(fn () => check_collection($pdo, $doc), '20 aktif');
class FixtureCollectorHttp extends CollectorHttp
{
    public function get(string $url, array $hosts, int $limit = 4000000): string
    {
        if (str_contains($url, 'assets.clashloot.gg')) {
            return file_get_contents(__DIR__ . '/fixtures/collector/loot.webp');
        }
        if (str_contains($url, 'img.cocbasefinder.com') || str_contains($url, 'storage.clashly.app')) {
            throw new RuntimeException('Kaynak HTTP 403.');
        }
        $source = str_contains($url, 'cocbasefinder.com') ? 'finder' : (str_contains($url, 'clashloot.gg') ? 'loot' : 'clashly');
        $spec = collector_sources()[$source];
        $record = json_decode(file_get_contents(__DIR__ . '/fixtures/collector/' . $source . '.json'), true);
        $rows = str_contains($url, '19') ? [$record] : [];
        $html = '<script>self.__next_f.push(' . json_encode([1, '{"' . $spec['field'] . '":' . json_encode($rows) . '}']) . ')</script>';
        if ($source === 'loot') {
            $provenance = json_decode(file_get_contents(__DIR__ . '/fixtures/collector/provenance.json'), true);
            $html .= '<a href="' . parse_url($provenance['loot'][0]['sourceUrl'], PHP_URL_PATH) . '">Preview</a>';
        }
        return $html;
    }
}
$pdo->exec("UPDATE collector_settings SET last_discovery = NULL WHERE id = 1");
$result = collector_scan($pdo, 'manual', new FixtureCollectorHttp());
expect(!empty($result['run']) && $result['errors'] >= 2, 'Cron continues with source 403 errors');
expect($pdo->query('SELECT status FROM collector_runs ORDER BY id DESC LIMIT 1')->fetchColumn() === 'partial', 'Partial source failure is recorded');
expect(collector_scan($pdo, 'manual', new FixtureCollectorHttp())['skipped'], 'Second scan uses durable daily gate');
expect((int) $pdo->query('SELECT COUNT(*) FROM layout_events')->fetchColumn() === 1, 'Scanning preserves events');
echo "PASS real source adapters, real screenshot, malformed links, robot rules, queue idempotency, exact approval, changed text/image rejection, duplicates, gameplay confirmation, archive and counters, TH20 limit, rejected version, editorial conflicts, scan concurrency and daily gate, CMS capacity.\n";
