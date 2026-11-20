V119 — Elmecon Stock V2.2.0 (build 202)
GitHub only. Upload all five source files (paths below) to the existing repository, keep android-source unchanged.
.github/workflows/build-delivery-stock-v2.yml
mobile-v2/MainActivity.java
mobile-v2/DeliveryStock.swift
mobile-v2/mobile.html
mobile-v2/elmecon-app-icon.png
Run NEW workflow, branch main, HTTPS DEV URL https://103.41.207.63:59825/delivery-dev/mobile-v2/api.php, ios_signed false for Android pilot.
Artifact: ElmeconStock-v2.2.0-Android-APK. Extract installer ElmeconStock-v2.2.0-Android-PILOT.apk (or signed ElmeconStock-v2.2.0-Android.apk).
Header and launcher icon use existing company artwork from delivery-dev/assets/img/elmecon-header-logo.png. Android safe area uses native root insets for API 30+ and normal decor fitting for API 24-29. iOS shares header and build number; iOS signing unchanged.
No backend patch, no Smart Sync, no Nginx/DB changes. Keep minimum build version unchanged until pilot succeeds.
Application ID and device key alias unchanged. Debug signing may differ between runners; if Android refuses upgrade, block old device, uninstall old PILOT and re-enroll. Stable signing key needed for production.
