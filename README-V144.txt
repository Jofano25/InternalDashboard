ELMECON STOCK V144 — MOBILE ADMIN DELETE DEVICE
===============================================
Android 2.9.0 / Build 209; iOS version metadata 2.9.0 / 209.

1. FIRST install BACKEND V144 and migrate tombstone table in LIVE ob2.
   APP hides Delete on older API versions. This prevents a dead button.
2. Copy to GitHub repo Jofano25/InternalDashboard, same relative folders:
   mobile-v2/mobile.html
   mobile-v2/MainActivity.java
   mobile-v2/DeliveryStock.swift
   .github/workflows/build-delivery-stock-v2.yml
3. Run Build Delivery Stock V2 GitHub Actions with API URL:
   https://103.41.207.63:59825/delivery/mobile-v2/api.php
4. Use same release signing key as previous build to install over the old APK.
   If Android forces uninstall, re-register and grant Mobile Admin again.
5. In Jofano APK: Mobile Admin > Registered Devices > Hapus Device.
   An in-app confirmation explains this removes registration and blocks old key.
6. Self-delete in APK is intentionally rejected to prevent locking out admin.

Old employee APKs are unchanged; all old API actions remain in backend.
Web dashboard and APK use the same LIVE ob2 device list.
