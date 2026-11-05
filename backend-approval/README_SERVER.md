# Instalasi backend pengunci perangkat (PHP 7.4+ / PostgreSQL)

## 1. Persiapan HTTPS (wajib produksi)
URL asli sekarang **http://103.41.207.63**. Pasang hostname/domain dengan HTTPS
(valid TLS) dan redirect semua HTTP->HTTPS sebelum penggunaan sungguhan.
Untuk uji sementara pada HTTP, tersedia `DELIVERY_MOBILE_ALLOW_HTTP_TEST=1`.
Jangan gunakan mode ini saat data nyata atau login diproses, karena cookie akses
bisa disadap di jaringan. Jangan kirim IMEI, password, atau token melalui HTTP.

## 2. Skema database pengembangan
Jalankan `schema_ob2_dev.sql` hanya pada database **ob2_dev**.
Contoh: `psql -h 127.0.0.1 -U NAMA_USER -d ob2_dev -f schema_ob2_dev.sql`.
Pastikan ekstensi PHP `pdo_pgsql` tersedia pada PHP-FPM.

## 3. Pasang ke web
Salin ISI `webroot/delivery/mobile/` menjadi `/var/www/html/delivery/mobile/`.
Tidak perlu memindahkan atau menulis ulang laporan lama.
Sisipkan potongan `PATCH_media.php.txt` di awal `/var/www/html/delivery/media.php`.
Jaga agar login existing situs tetap berjalan. Lindungi endpoint AJAX/export terkait.

## 4. Environment (contoh uji; bukan password sebenarnya)
Set melalui environment PHP-FPM atau `fastcgi_param` Nginx yang khusus untuk aplikasi:
```
DELIVERY_MOBILE_PG_DSN=pgsql:host=127.0.0.1;port=5432;dbname=ob2_dev
DELIVERY_MOBILE_PG_USER=YOUR_DEV_DB_USER
DELIVERY_MOBILE_PG_PASSWORD=YOUR_SECRET
DELIVERY_MOBILE_ADMIN_PASSWORD_HASH=<OUTPUT_PHP_HASH>
# HANYA UNTUK UJI HTTP SEMENTARA:
DELIVERY_MOBILE_ALLOW_HTTP_TEST=1
```
Hash password admin dibuat di terminal:
`php -r 'echo password_hash("GANTI_PASSWORD_PANJANG", PASSWORD_DEFAULT), PHP_EOL;'`
Atur konfigurasi di FPM pool atau dari config private luar document root.
**Jangan** menambahkan password di repository, APK, atau URL.
Restart/reload php-fpm sesudah mengubah environment.

## 5. Cara kerja
- HP membuka `media.php?module=rpt_stock_new`.
- Guard menolak akses belum disetujui, lalu mengalihkan ke `mobile/start.php`.
- Start membuat ID instalasi acak (token cookie HttpOnly; database hanya menyimpan hash).
- HP menampilkan kode pendaftaran 8 karakter. Admin masuk ke `mobile/admin.php`.
- Admin mencocokkan kode dan menekan Setujui. HP akan membuka laporan otomatis.
- Admin dapat mencabut izin; akses berikutnya diblokir oleh server.

## 6. Pengujian yang perlu dilakukan
1. HP baru -> kode aktivasi, belum bisa melihat laporan.
2. Admin approve -> halaman laporan tampil.
3. HP lain -> tetap tertolak walaupun mengetahui URL yang sama.
4. Admin revoke -> reload atau permintaan laporan berikutnya tertolak.
5. Ekspor Excel/PDF, menu AJAX, dan login situs lama tetap berfungsi sesuai otorisasi.
6. Tes langsung ke URL API data/export: tidak boleh dapat data tanpa persetujuan.

## Batas keamanan yang penting
Perangkat didaftarkan berdasarkan **instalasi/browser cookie**, bukan IMEI.
Cookie bisa dihapus sehingga perangkat harus meminta persetujuan ulang.
Dalam skenario perangkat di-root/jailbreak atau cookie dicuri, token bisa dipindahkan.
Untuk level keamanan tinggi, tambahkan login per pengguna, MFA, Android Play Integrity,
iOS App Attest, VPN/MDM, dan pencatatan audit di semua endpoint. Program ini adalah
*starter implementation*, bukan jaminan perangkat fisik anti-spoofing.

Halaman `mobile/admin.php` harus dibatasi dengan VPN/IP allowlist dan rate limit Nginx.
Untuk produksi, database operasional dan akses DB harus disepakati saat deployment;
**jangan mengalihkan development ke `ob2`** tanpa prosedur change control yang sah.
