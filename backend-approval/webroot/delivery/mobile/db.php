<?php
require_once __DIR__ . '/config.php';
function mobile_db() {
    static $db = null;
    if ($db instanceof PDO) return $db;
    $dsn = mobile_env('DELIVERY_MOBILE_PG_DSN', 'pgsql:host=127.0.0.1;port=5432;dbname=ob2_dev');
    $user = mobile_env('DELIVERY_MOBILE_PG_USER');
    $password = mobile_env('DELIVERY_MOBILE_PG_PASSWORD');
    if ($user === '') throw new RuntimeException('Konfigurasi database belum disiapkan');
    $db = new PDO($dsn, $user, $password, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);
    return $db;
}
