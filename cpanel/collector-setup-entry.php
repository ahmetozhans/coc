<?php
declare(strict_types=1);
$setup = dirname(__DIR__) . '/mahanpro-private/collector-setup/page.php';
if (!is_file($setup)) {
    http_response_code(503);
    header('Content-Type: text/plain; charset=UTF-8');
    echo 'Kurulum klasörü eksik. ZIP dosyasını public_html ile mahanpro-private klasörlerinin bulunduğu ana dizine çıkar.';
    exit;
}
require $setup;
