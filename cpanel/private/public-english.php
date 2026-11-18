<?php
declare(strict_types=1);

function public_english_text(string $value, string $fallback = ''): string
{
    static $translations;
    $translations ??= json_decode(file_get_contents(__DIR__ . '/public-english.json'), true, 32, JSON_THROW_ON_ERROR);
    $value = $translations[$value] ?? $value;
    $value = strtr($value, [
        'Savaş Düzeni' => 'War Base', 'Kupa Düzeni' => 'Trophy Base',
        'Ganimet Düzeni' => 'Farming Base', 'Kaynak Düzeni' => 'Farming Base',
        'Karma Düzeni' => 'Hybrid Base', 'Hibrit Düzeni' => 'Hybrid Base',
        'Köy Düzeni' => 'Base Layout', 'köy düzeni' => 'base layout',
    ]);
    return preg_match('/[çğıöşüÇĞİÖŞÜ]/u', $value) ? $fallback : $value;
}

function public_english_layout(array $layout): array
{
    // Keep the existing canonical path before translating the visible title.
    $layout['canonicalPath'] = layout_detail_url($layout);
    $fallback = 'TH' . (int) $layout['th'] . ' ' . $layout['type'] . ' Base Layout';
    $layout['title'] = public_english_text((string) ($layout['titleEn'] ?? $layout['title']), $fallback);
    $layout['description'] = public_english_text((string) ($layout['descriptionEn'] ?? $layout['description'] ?? ''));
    return $layout;
}

function public_english_site(object $site): object
{
    $copy = json_decode(json_encode($site, JSON_THROW_ON_ERROR), false, 64, JSON_THROW_ON_ERROR);
    if (isset($copy->tagline)) {
        $copy->tagline = public_english_text((string) $copy->tagline, 'Night Village');
    }
    foreach ($copy->pages ?? [] as $page) {
        $page->seoTitle = public_english_text((string) ($page->seoTitle ?? ''));
        $page->seoDescription = public_english_text((string) ($page->seoDescription ?? ''));
    }
    return $copy;
}

function public_english_template(string $template): string
{
    $template = preg_replace('~<label\b[^>]*for="language"[^>]*>.*?</label>~s', '', $template);
    $template = preg_replace('~<select\b[^>]*id="language"[^>]*>.*?</select>~s', '', $template);
    $template = preg_replace('~<div class="header-actions">\s*</div>~', '', $template);
    return str_contains($template, 'data-public-language="en"') ? $template
        : str_replace('<html lang="en"', '<html lang="en" data-public-language="en"', $template);
}
