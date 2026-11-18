<?php
declare(strict_types=1);

require dirname(__DIR__) . '/cpanel/private/content.php';
require dirname(__DIR__) . '/cpanel/private/layout-detail.php';

function confirm_detail(bool $valid): void
{
    if (!$valid) {
        throw new RuntimeException('Layout detail test failed');
    }
}

$item = [
    'id' => 'test-layout', 'th' => 19, 'type' => 'War', 'title' => 'Title <safe>',
    'description' => 'Description <safe>', 'image' => 'https://example.com/layout.webp',
    'url' => 'https://link.clashofclans.com/en?action=OpenLayout&id=TH19:test',
];
$catalog = [
    array_replace($item, ['id' => 'hidden', 'hidden' => true]),
    $item,
    array_replace($item, ['id' => 'different-th', 'th' => 18]),
    array_replace($item, ['id' => 'same-th', 'type' => 'Trophy']),
    array_replace($item, ['id' => 'best-match']),
];

$selected = layout_detail_selection('test-layout', $catalog);
confirm_detail($selected !== null && $selected[0]['id'] === 'test-layout');
confirm_detail($selected[1] === null && $selected[2]['id'] === 'different-th');
confirm_detail(array_column($selected[3], 'id') === ['best-match', 'same-th', 'different-th']);
confirm_detail(layout_detail_selection('hidden', $catalog) === null);
confirm_detail(layout_detail_selection('missing', $catalog) === null);
confirm_detail(preg_match('~^/bases/title-safe--[a-f0-9]{12}$~D', layout_detail_url($item)) === 1);
$segment = basename(layout_detail_url($item));
confirm_detail(layout_detail_request($segment, $catalog)[0]['id'] === 'test-layout');
confirm_detail(layout_detail_request('old-title--' . substr($segment, -12), $catalog)[0]['id'] === 'test-layout');
confirm_detail(layout_detail_request(basename(layout_detail_url($catalog[0])), $catalog) === null);
confirm_detail(layout_meta_summary("Birinci satır.\nİkinci satır.", '') === 'Birinci satır. İkinci satır.');
confirm_detail(str_ends_with(layout_meta_summary(str_repeat('köy düzeni ', 80), ''), '…'));
confirm_detail(count(layout_notes('War', 19)[1]) === 2);
$galleryTemplate = file_get_contents($argv[1] ?? dirname(__DIR__) . '/bases.html');
$shell = layout_detail_shell($galleryTemplate);
confirm_detail(str_contains($shell['theme'], 'href="/mahanpro-night.css'));
confirm_detail(str_contains($shell['header'], 'class="top-nav"') && !str_contains($shell['header'], 'id="language"'));
confirm_detail(str_contains($shell['mobile'], 'aria-current="location"'));
confirm_detail(str_contains($shell['footer'], 'Fan Content Policy'));
render_layout_detail('test-layout', $catalog, 'https://mahanpro.fun/', galleryTemplate: $galleryTemplate);
