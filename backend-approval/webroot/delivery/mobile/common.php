<?php
require_once __DIR__ . '/db.php';
function mobile_h($str) { return htmlspecialchars((string)$str, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }
function mobile_token() {
    $token = isset($_COOKIE[MOBILE_COOKIE_NAME]) ? (string)$_COOKIE[MOBILE_COOKIE_NAME] : '';
    return preg_match('/^[a-f0-9]{64}$/D', $token) ? $token : null;
}
function mobile_device_for_token() {
    $token = mobile_token();
    if ($token === null) return null;
    $stmt = mobile_db()->prepare('SELECT * FROM mobile_delivery_devices WHERE token_hash = :hash LIMIT 1');
    $stmt->execute([':hash'=>hash('sha256', $token)]);
    return $stmt->fetch() ?: null;
}
function mobile_platform() {
    $ua = (string)($_SERVER['HTTP_USER_AGENT'] ?? '');
    if (stripos($ua, 'android') !== false) return 'android';
    if (stripos($ua, 'iphone') !== false || stripos($ua, 'ipad') !== false) return 'ios';
    return 'web';
}
function mobile_new_device() {
    $db = mobile_db();
    for ($tries=0; $tries<5; $tries++) {
        $token = bin2hex(random_bytes(32));
        // Kode untuk identifikasi permintaan approval, BUKAN password autentikasi.
        $alphabet = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
        $code = '';
        for ($i=0; $i<8; $i++) $code .= $alphabet[random_int(0, strlen($alphabet)-1)];
        try {
            $stmt = $db->prepare('INSERT INTO mobile_delivery_devices
               (token_hash, activation_code, device_label, platform, user_agent)
               VALUES (:h,:c,:l,:p,:u) RETURNING *');
            $stmt->execute([
                ':h' => hash('sha256',$token), ':c'=>$code,
                ':l'=>substr(mobile_platform().' device',0,120),
                ':p'=>mobile_platform(), ':u'=>substr((string)($_SERVER['HTTP_USER_AGENT'] ?? ''),0,1024)
            ]);
            setcookie(MOBILE_COOKIE_NAME, $token, [
                'expires'=>time() + MOBILE_DEVICE_TTL_DAYS*86400,
                'path'=>MOBILE_COOKIE_PATH, 'secure'=>mobile_https(),
                'httponly'=>true, 'samesite'=>'Lax'
            ]);
            return $stmt->fetch();
        } catch (PDOException $e) {
            if ($e->getCode() !== '23505' || $tries===4) throw $e;
        }
    }
    throw new RuntimeException('Gagal membuat kode perangkat');
}
function mobile_ensure_device() {
    $device = mobile_device_for_token();
    return $device ?: mobile_new_device();
}
function mobile_touch_device($id) {
    $stmt = mobile_db()->prepare('UPDATE mobile_delivery_devices SET last_seen_at = NOW() WHERE id=:id AND (last_seen_at IS NULL OR last_seen_at < NOW()-INTERVAL \'5 minutes\')');
    $stmt->execute([':id'=>$id]);
}
function mobile_fail_closed($e) {
    error_log('[Delivery mobile access] '.$e->getMessage());
    http_response_code(503);
    header('Content-Type: text/plain; charset=utf-8');
    exit('Layanan izin perangkat sementara tidak tersedia. Hubungi administrator.');
}
