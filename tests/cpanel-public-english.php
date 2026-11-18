<?php
declare(strict_types=1);
require dirname(__DIR__) . '/cpanel/private/layout-detail.php';
require dirname(__DIR__) . '/cpanel/private/public-english.php';
function en_expect(bool $ok, string $message): void
{
    if (!$ok) {
        throw new RuntimeException($message);
    }
}
$original = ['id' => 'preserved-identity', 'th' => 18, 'type' => 'War',
    'title' => 'TH18 · Savaş Düzeni #1', 'description' => '', 'approvalFingerprint' => 'original-fingerprint'];
$url = layout_detail_url($original);
$english = public_english_layout($original);
en_expect($english['title'] === 'TH18 · War Base #1', 'Translate the existing visible title');
en_expect(layout_detail_url($english) === $url, 'Canonical URL must be preserved');
en_expect($original['title'] === 'TH18 · Savaş Düzeni #1', 'Stored source must remain intact');
en_expect($english['id'] === $original['id'] && $english['approvalFingerprint'] === $original['approvalFingerprint'], 'Identity and approval retained');
$site = (object) ['tagline'=>'Gece Köyü','pages'=>(object) [],'footer'=>(object) ['tr'=>'Türkçe metin','en'=>'English copy']];
$public = public_english_site($site);
en_expect($public->tagline === 'Night Village', 'Translate the current live tagline');
en_expect($site->tagline === 'Gece Köyü', 'Admin source retained');
en_expect($public->footer->en === 'English copy', 'English CMS field retained');
$template = '<html lang="en"><div class="header-actions"><label for="language">Language</label><select id="language"></select></div></html>';
$projected = public_english_template($template);
en_expect(!str_contains($projected, 'id="language"') && str_contains($projected, 'data-public-language="en"'), 'Server-side English-only markup');
en_expect(public_english_template($projected) === $projected, 'Template projection is idempotent');
echo "PASS existing title translation, stable canonical URLs, stored data/approval preservation and English CMS projection.\n";
