<?php
require_once __DIR__ . '/common.php';
mobile_require_secure_transport();
header('Cache-Control: no-store, private');
header('X-Content-Type-Options: nosniff');
header('Content-Security-Policy: default-src \'self\'; script-src \'self\' \'unsafe-inline\'; style-src \'self\' \'unsafe-inline\'; connect-src \'self\'; base-uri \'none\'; frame-ancestors \'none\'');
try {
    $device = mobile_ensure_device();
    if ($device['status'] === 'approved') {
        mobile_touch_device((int)$device['id']);
        header('Location: '.MOBILE_TARGET_URL, true, 302);
        exit;
    }
} catch (Throwable $e) { mobile_fail_closed($e); }
$revoked = $device['status'] === 'revoked';
?><!doctype html><html lang="id"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1,user-scalable=no">
<title>Aktivasi Delivery Stock</title><style>
*{box-sizing:border-box}body{margin:0;min-height:100vh;background:linear-gradient(150deg,#081a30,#153a54);color:#13263f;font-family:system-ui,-apple-system,Arial;display:grid;place-items:center;padding:24px}
.card{background:#fff;border-radius:22px;box-shadow:0 22px 70px #0004;width:min(460px,100%);padding:30px;text-align:center}
.mark{display:inline-grid;place-items:center;width:68px;height:68px;background:#123c60;color:#fff;border-radius:19px;font-size:22px;font-weight:900;letter-spacing:-1px}
h1{font-size:25px;margin:18px 0 8px}p{color:#526579;line-height:1.55}.code{margin:22px auto 12px;border:2px dashed #abc2d4;border-radius:14px;padding:16px;font-weight:850;letter-spacing:5px;font-size:31px;color:#0d497a}
.tag{display:inline-block;padding:7px 11px;border-radius:99px;background:#fff2d0;color:#8b5b00;font-weight:650;font-size:12px}.denied{background:#fee2e2;color:#991b1b}small{display:block;margin-top:25px;color:#8293a3}
</style></head><body><main class="card"><div class="mark">DS</div><h1>Delivery Stock</h1>
<?php if($revoked): ?>
<span class="tag denied">Akses dicabut</span><p>Perangkat ini telah dinonaktifkan. Hubungi administrator untuk mengaktifkan kembali.</p>
<?php else: ?>
<span class="tag">Menunggu persetujuan administrator</span><p>Berikan kode perangkat berikut kepada administrator untuk mengaktifkan aplikasi ini.</p>
<div class="code" id="deviceCode"><?= mobile_h($device['activation_code']) ?></div>
<p>Tidak perlu menginstal ulang. Halaman akan terbuka otomatis setelah admin menyetujui.</p>
<script>
async function check(){try{const r=await fetch('/delivery/mobile/status.php',{cache:'no-store',credentials:'same-origin'}); if(!r.ok)return; const d=await r.json(); if(d.status==='approved')location.replace(<?= json_encode(MOBILE_TARGET_URL) ?>);if(d.status==='revoked')location.reload();}catch(e){}}
setInterval(check,5000);
</script>
<?php endif; ?>
<small>PT Elmecon Multikencana · Internal Access</small></main></body></html>
