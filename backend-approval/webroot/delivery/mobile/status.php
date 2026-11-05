<?php
require_once __DIR__ . '/common.php';
mobile_require_secure_transport();
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
header('X-Content-Type-Options: nosniff');
try {
    $device = mobile_device_for_token();
    echo json_encode(['status'=>$device ? $device['status'] : 'unknown'], JSON_UNESCAPED_SLASHES);
} catch (Throwable $e) { mobile_fail_closed($e); }
