<?php
// PHP 7.4+; konfigurasi melalui env Nginx/PHP-FPM atau environment sistem.
// Gunakan DEV database ob2_dev; jangan gunakan database live untuk testing.
const MOBILE_TARGET_URL = '/delivery/media.php?module=rpt_stock_new';
const MOBILE_COOKIE_NAME = 'delivery_device_token';
const MOBILE_COOKIE_PATH = '/delivery/';
const MOBILE_DEVICE_TTL_DAYS = 180;

function mobile_env($name, $fallback = '') {
    $value = getenv($name);
    return $value === false ? $fallback : $value;
}
function mobile_https() {
    return !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
}
function mobile_require_secure_transport() {
    // Hanya untuk uji di IP HTTP. NONAKTIFKAN sebelum go-live.
    if (!mobile_https() && mobile_env('DELIVERY_MOBILE_ALLOW_HTTP_TEST') !== '1') {
        http_response_code(426);
        header('Content-Type: text/plain; charset=utf-8');
        exit('Akses perangkat memerlukan HTTPS. Aktifkan domain dan sertifikat TLS terlebih dahulu.');
    }
}
