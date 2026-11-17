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
render_layout_detail('test-layout', $catalog, 'https://mahanpro.fun/');
