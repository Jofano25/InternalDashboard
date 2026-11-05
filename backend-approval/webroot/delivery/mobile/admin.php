<?php
require_once __DIR__ . '/common.php';
mobile_require_secure_transport();
header('Cache-Control: no-store, private');
header('X-Frame-Options: DENY');
header('X-Content-Type-Options: nosniff');
ini_set('session.use_strict_mode', '1');
ini_set('session.cookie_httponly', '1');
ini_set('session.cookie_samesite','Lax');
if (mobile_https()) ini_set('session.cookie_secure','1');
session_start();
$hash = mobile_env('DELIVERY_MOBILE_ADMIN_PASSWORD_HASH');
if (!$hash) { http_response_code(503); exit('Set DELIVERY_MOBILE_ADMIN_PASSWORD_HASH terlebih dahulu.'); }
if(!isset($_SESSION['mobile_csrf'])) $_SESSION['mobile_csrf']=bin2hex(random_bytes(16));
$error = '';
if ($_SERVER['REQUEST_METHOD']==='POST') {
    if (!hash_equals($_SESSION['mobile_csrf'],(string)($_POST['csrf']??''))) {
        http_response_code(403); exit('CSRF tidak valid');
    }
    if (isset($_POST['login'])) {
        if (password_verify((string)($_POST['password']??''),$hash)) {
            session_regenerate_id(true);
            $_SESSION['mobile_admin']=true;
            header('Location: admin.php',true,303);exit;
        }
        $error='Password salah';
        usleep(300000); // perlambatan minimal; tambahkan rate-limit Nginx juga
    } elseif (isset($_POST['logout'])) {
        unset($_SESSION['mobile_admin']);
        session_regenerate_id(true);
        header('Location: admin.php',true,303);exit;
    } elseif (!empty($_SESSION['mobile_admin'])) {
        $id = filter_var($_POST['id']??null, FILTER_VALIDATE_INT);
        $act=(string)($_POST['action']??'');
        try {
            if (!$id) throw new RuntimeException('ID tidak valid');
            if (in_array($act,['approve','revoke','pending'],true)) {
                $state=['approve'=>'approved','revoke'=>'revoked','pending'=>'pending'][$act];
                $sql='UPDATE mobile_delivery_devices SET status=:s, approved_at=CASE WHEN :s2 = \'approved\' THEN NOW() ELSE NULL END WHERE id=:id';
                $q=mobile_db()->prepare($sql);
                $q->execute([':s'=>$state,':s2'=>$state,':id'=>$id]);
            } elseif($act==='rename') {
                $label=trim((string)($_POST['label']??''));
                if($label==='') throw new RuntimeException('Nama perangkat kosong');
                $q=mobile_db()->prepare('UPDATE mobile_delivery_devices SET device_label=:l WHERE id=:id');
                $q->execute([':l'=>substr($label,0,120),':id'=>$id]);
            } else throw new RuntimeException('Aksi tidak dikenal');
            header('Location: admin.php',true,303);exit;
        } catch(Throwable $e) { $error='Gagal mengubah data. Periksa log server.';error_log('[Delivery admin] '.$e->getMessage()); }
    }
}
$logged=!empty($_SESSION['mobile_admin']);
$rows=[];$stats=['pending'=>0,'approved'=>0,'revoked'=>0];
if($logged){try{
    $rows=mobile_db()->query("SELECT id,activation_code,device_label,platform,status,created_at,approved_at,last_seen_at FROM mobile_delivery_devices ORDER BY CASE status WHEN 'pending' THEN 0 WHEN 'approved' THEN 1 ELSE 2 END,created_at DESC LIMIT 500")->fetchAll();
    foreach($rows as $d) $stats[$d['status']]++;
}catch(Throwable $e){$error='Database gagal diakses';error_log($e->getMessage());}}
?><!doctype html><html lang="id"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Admin Delivery Stock Device</title><style>
*{box-sizing:border-box}body{margin:0;font-family:system-ui,Arial;color:#14304a;background:#eff4f8}header{background:#0c2845;color:#fff;padding:22px 6vw}header h1{margin:0;font-size:22px}main{max-width:1220px;margin:28px auto;padding:0 18px}.panel{background:white;border-radius:14px;padding:22px;box-shadow:0 3px 22px #142c4420}.btn{border:0;border-radius:8px;padding:9px 12px;color:#fff;font-weight:650;cursor:pointer;background:#225b88}.approve{background:#13764b}.revoke{background:#aa3636}.pending{background:#997000}.muted{color:#64748b}.err{color:#a11f2c}.cards{display:flex;gap:15px;margin:0 0 20px}.metric{flex:1;background:white;border-radius:12px;padding:18px}.metric strong{display:block;font-size:27px}table{border-collapse:collapse;width:100%;font-size:13px}th,td{text-align:left;border-bottom:1px solid #e6ebef;padding:11px 9px;vertical-align:middle}th{background:#eef5fa}input[type=text],input[type=password]{padding:9px;border:1px solid #c6d3dd;border-radius:6px;max-width:210px}form.inline{display:inline-block;margin:2px}form.rename{display:flex;gap:6px}.scroll{overflow-x:auto}.status{font-weight:700}.status.approved{color:#13764b}.status.revoked{color:#aa3636}.status.pending{color:#997000}code{font-size:14px;font-weight:bold}button{white-space:nowrap}
</style></head><body><header><h1>Delivery Stock · Manajemen Perangkat</h1></header><main>
<?php if($error): ?><p class="err"><?= mobile_h($error) ?></p><?php endif; ?>
<?php if(!$logged): ?>
<section class="panel" style="max-width:420px;margin:60px auto"><h2>Login Administrator</h2><form method="post"><input type="hidden" name="csrf" value="<?= mobile_h($_SESSION['mobile_csrf']) ?>"><p><input type="password" name="password" autocomplete="current-password" placeholder="Password administrator" required style="width:100%;max-width:none"></p><button class="btn" name="login" value="1">Masuk</button></form></section>
<?php else: ?>
<div class="cards"><div class="metric">Menunggu<strong><?= $stats['pending'] ?></strong></div><div class="metric">Disetujui<strong><?= $stats['approved'] ?></strong></div><div class="metric">Dicabut<strong><?= $stats['revoked'] ?></strong></div></div>
<section class="panel"><div style="display:flex;align-items:center;justify-content:space-between;gap:10px"><h2>Daftar HP</h2><form method="post"><input type="hidden" name="csrf" value="<?= mobile_h($_SESSION['mobile_csrf']) ?>"><button class="btn" name="logout" value="1">Keluar</button></form></div>
<p class="muted">Cocokkan kode di layar HP dengan kode di tabel. Setujui hanya perangkat yang Anda kenali.</p><div class="scroll"><table><thead><tr><th>Kode</th><th>Nama perangkat</th><th>Platform</th><th>Status</th><th>Terdaftar</th><th>Terakhir terlihat</th><th>Tindakan</th></tr></thead><tbody>
<?php foreach($rows as $d): ?><tr><td><code><?= mobile_h($d['activation_code']) ?></code></td><td><form class="rename" method="post"><input type="hidden" name="csrf" value="<?= mobile_h($_SESSION['mobile_csrf']) ?>"><input type="hidden" name="id" value="<?= (int)$d['id'] ?>"><input type="hidden" name="action" value="rename"><input type="text" name="label" value="<?= mobile_h($d['device_label']) ?>" required><button class="btn" title="Simpan nama">Simpan</button></form></td><td><?= mobile_h(strtoupper($d['platform'])) ?></td><td class="status <?= mobile_h($d['status']) ?>"><?= mobile_h($d['status']) ?></td><td><?= mobile_h($d['created_at']) ?></td><td><?= mobile_h($d['last_seen_at']?:'-') ?></td><td>
<?php foreach(['approve'=>'Setujui','revoke'=>'Cabut','pending'=>'Ulangi'] as $act=>$label): if($d['status']===['approve'=>'approved','revoke'=>'revoked','pending'=>'pending'][$act])continue; ?>
<form class="inline" method="post"><input type="hidden" name="csrf" value="<?= mobile_h($_SESSION['mobile_csrf']) ?>"><input type="hidden" name="id" value="<?= (int)$d['id'] ?>"><input type="hidden" name="action" value="<?= $act ?>"><button class="btn <?= $act==='approve'?'approve':($act==='revoke'?'revoke':'pending') ?>" onclick="return confirm('Yakin mengubah izin perangkat ini?')"><?= $label ?></button></form>
<?php endforeach; ?></td></tr><?php endforeach; ?>
<?php if(!$rows): ?><tr><td colspan="7">Belum ada perangkat yang mendaftar.</td></tr><?php endif; ?>
</tbody></table></div><p class="muted">Tabel menampilkan maksimum 500 perangkat terbaru. Konfigurasi password dan akses halaman admin harus dibatasi di Nginx/VPN.</p></section>
<?php endif; ?></main></body></html>
