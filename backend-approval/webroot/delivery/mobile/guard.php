<?php
// Panggil dari AWAL media.php sebelum output untuk modul rpt_stock_new,
// serta dari endpoint AJAX/export khusus laporan yang sama.
require_once __DIR__ . '/common.php';
mobile_require_secure_transport();
try {
    $device = mobile_device_for_token();
    if (!$device || $device['status'] !== 'approved') {
        header('Cache-Control: no-store, private');
        header('Location: /delivery/mobile/start.php', true, 302);
        exit;
    }
    mobile_touch_device((int)$device['id']);
} catch (Throwable $e) { mobile_fail_closed($e); }
