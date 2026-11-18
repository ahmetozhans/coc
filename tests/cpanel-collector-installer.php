<?php
declare(strict_types=1);
require dirname(__DIR__) . '/cpanel/collector-installer-core.php';
function setup_expect(bool $valid, string $message): void
{
    if (!$valid) {
        throw new RuntimeException($message);
    }
}
$old = "before\nold line\nafter\n";
$new = "before\nnew line\nafter\n";
$edit = [['old' => $old, 'new' => $new]];
setup_expect(collector_setup_merge("live customization\n" . $old, $edit) === "live customization\n" . $new, 'Unrelated live changes must survive');
setup_expect(collector_setup_merge("live customization\n" . $new, $edit) === "live customization\n" . $new, 'Partially applied updates must be idempotent');
foreach (["before\nlive change\nafter\n", $old . $old] as $conflict) {
    try {
        collector_setup_merge($conflict, $edit);
        throw new LogicException('Conflict unexpectedly accepted');
    } catch (RuntimeException $e) {
        setup_expect(!$e instanceof LogicException, 'Ambiguous or overlapping edit must stop');
    }
}
$package = $argv[1];
$home = sys_get_temp_dir() . '/mahanpro-installer-test-' . bin2hex(random_bytes(5));
mkdir($home);
mkdir($home . '/public_html');
mkdir($home . '/mahanpro-private');
$manifest = json_decode(file_get_contents($package . '/manifest.json'), true, 32, JSON_THROW_ON_ERROR);
foreach ($manifest as $file) {
    if ($file['before']) {
        copy($package . '/reference/' . $file['path'], $home . '/' . $file['path']);
    }
}
$custom = $home . '/mahanpro-private/cms.php';
file_put_contents($custom, "\n// existing hosting customization\n", FILE_APPEND);
$plan = collector_setup_plan($home, $package);
setup_expect(!$plan['conflicts'], 'Unrelated real CMS customization should merge');
setup_expect(count(array_filter($plan['pending'], fn ($f) => $f['merged'])) === 1, 'One real file should be merged');
$backup = collector_setup_apply($home, $plan);
setup_expect(is_dir($backup), 'Private backup must exist');
setup_expect(str_contains(file_get_contents($custom), '// existing hosting customization'), 'Hosting customization retained');
setup_expect(collector_setup_plan($home, $package)['pending'] === [], 'Applied merge should be idempotent');
$changed = file_get_contents($custom);
file_put_contents($custom, str_replace('collector_check_capacity(', 'live_capacity_override(', $changed));
$conflict = collector_setup_plan($home, $package);
setup_expect(in_array('mahanpro-private/cms.php', $conflict['conflicts'], true), 'Same-line live changes must stop');
setup_expect(file_get_contents($custom) !== $changed, 'Preflight must not rewrite a conflict');
echo "PASS real file merge, overlapping/ambiguous conflict refusal, private backup, idempotent install and preserved hosting changes.\n";
