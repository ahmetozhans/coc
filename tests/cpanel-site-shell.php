<?php
declare(strict_types=1);
require dirname(__DIR__) . '/cpanel/private/site-shell.php';
$html = '<header><a href="/bases">Bases</a><a href="/clan">Clans</a><a href="upgrade.html">Upgrades</a></header>'
    . '<a class="tool-card" href="/clan"><h3>Clan Explorer</h3></a>'
    . '<a class="button primary" href="/upgrade"><span>Plan your upgrades</span></a>'
    . '<span class="card-number">03</span><p>Four simple tools. One place to make your next move count.</p>';
$result = site_navigation_template($html);
if (str_contains($result, 'href="/clan"') || str_contains($result, 'upgrade.html') || str_contains($result, 'card-number')
    || !str_contains($result, 'Explore base layouts') || !str_contains($result, 'plan your next attack')) {
    throw new RuntimeException('Shared navigation projection failed');
}
if (site_navigation_template($result) !== $result) {
    throw new RuntimeException('Shared navigation must be idempotent');
}
echo "PASS shared navigation removes retired pages and preserves an available primary action.\n";
