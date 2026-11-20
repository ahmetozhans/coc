<?php
declare(strict_types=1);

function site_navigation_template(string $html): string
{
    // Reuse the existing call-to-action with a destination that remains available.
    $html = preg_replace_callback('~<a\b([^>]*href="(?:/)?upgrade(?:\.html)?"[^>]*)>(.*?)</a>~s',
        static function (array $match): string {
            if (!str_contains($match[1], 'primary')) {
                return $match[0];
            }
            $attributes = preg_replace('~href="[^"]*"~', 'href="/bases"', $match[1]);
            $content = preg_replace('~<span>[^<]*</span>~', '<span>Explore base layouts</span>', $match[2], 1);
            return '<a' . $attributes . '>' . $content . '</a>';
        }, $html);
    $html = preg_replace('~<a\b[^>]*href="(?:/)?(?:clan|upgrade)(?:\.html)?(?:[?#][^"]*)?"[^>]*>.*?</a>~s', '', $html);
    $html = preg_replace('~<span class="card-number">.*?</span>~s', '', $html);
    $html = str_replace('Four simple tools. One place to make your next move count.',
        'Explore base layouts and plan your next attack.', $html);
    return $html;
}
