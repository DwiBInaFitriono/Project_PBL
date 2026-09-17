# Rebung Pintar — Flutter

Aplikasi terhubung ke REST Laravel berversi `/api/v1`: login/logout, monitoring berkala, grafik/ringkasan dari pembacaan server, riwayat WIB dengan filter/pagination, profil hanya baca, serta konfigurasi ESP khusus Operator.

Tema ivory/charcoal dengan aksen kuning; drawer mobile dan sidebar desktop. Filter bertahan selama sesi. Null tetap kosong, bukan nol. MQTT hanya hardware → Laravel; Flutter tidak berlangganan broker. Perubahan profil/sandi dan ekspor PDF belum tersedia melalui API mobile.

## Web lokal

Dari root Laravel jalankan `php artisan serve` (port 8000). Dari folder android-app:

    flutter build web --no-web-resources-cdn --dart-define=API_BASE_URL=http://127.0.0.1:8000/api/v1 --dart-define=ALLOW_INSECURE_LOCAL_API=true
    python serve_web.py

Buka http://127.0.0.1:8091 dan masukkan akun Laravel yang sudah ada. Origin port 8091 diizinkan CORS development. Tanpa API_BASE_URL, build tetap menjadi pratinjau eksplisit tanpa login jaringan.

## Android

Untuk emulator, jalankan Laravel dari root proyek melalui Git Bash:

    APP_DEBUG=false APP_ALLOWED_HOSTS=10.0.2.2 php artisan serve --host=127.0.0.1 --port=8000 --no-interaction --no-reload

`APP_ALLOWED_HOSTS` mengizinkan Host emulator hanya pada proses lokal ini, tanpa mengubah `.env`. Tanpa entri tersebut, `10.0.2.2` ditolak dengan HTTP 400. `--no-reload` mempertahankan override environment pada proses server PHP; restart manual setelah mengubah konfigurasi. Server tetap bind ke loopback, bukan seluruh LAN. Jangan tambahkan alias emulator ke allowlist produksi.

Dari folder android-app, build APK debug dengan URL emulator yang sama:

    flutter build apk --debug --dart-define=API_BASE_URL=http://10.0.2.2:8000/api/v1 --dart-define=ALLOW_INSECURE_LOCAL_API=true

APK: `build/app/outputs/flutter-apk/app-debug.apk`. Build lokal ini ditujukan untuk emulator Android. 10.0.2.2 bukan alamat komputer dari HP fisik. Untuk HP fisik/deployment, build dengan API_BASE_URL host HTTPS yang dapat dijangkau. Cleartext hanya diizinkan untuk loopback/emulator pada manifest debug; release tidak membawa pengecualian tersebut. Tidak ada kredensial di APK/source.

## Signing release

Release tidak memakai debug key. Gradle membaca `android/key.properties` (diabaikan Git); keystore dan salinan properti pemulihan disimpan di `C:\Users\Dwi Bina Fitriono\.rebung-pintar-signing\`. Hak akses hanya pengguna Windows saat ini dan SYSTEM. Jangan membagikan atau memasukkan file ini ke repository/APK. Cadangkan folder signing secara terenkripsi di lokasi lain: kehilangan kunci berarti tidak dapat menandatangani pembaruan aplikasi dengan identitas yang sama.

Build release tanpa konfigurasi signing yang lengkap ditolak sebelum kompilasi. Keystore tidak boleh diganti untuk pembaruan aplikasi yang sudah didistribusikan.

Karena belum ada backend HTTPS untuk HP fisik, release saat ini sengaja berupa pratinjau tanpa login jaringan:

    flutter build apk --release

APK: `build/app/outputs/flutter-apk/app-release.apk`. Setelah backend tersedia, build ulang dengan `--dart-define=API_BASE_URL=https://HOST-BACKEND/api/v1` tanpa flag HTTP lokal. Jangan memakai alamat contoh sebelum host nyata disepakati. Verifikasi HTTPS, CORS web, sertifikat, dan signing sebelum distribusi produksi.

## Pengujian dan batas

    flutter test
    flutter analyze

`test/rest_integration_test.dart` memerlukan fixture REST terisolasi melalui environment, sehingga dilewati pada test biasa. Smoke test UI nyata berada di `../tests/Browser/support/flutter-live.mjs`; kredensial hanya melalui environment dan logout dijalankan saat cleanup.

Token hanya di memori, tidak di localStorage. Reload/menutup aplikasi memerlukan login ulang. Perubahan tema berlaku selama proses berjalan. MQTT tetap nonaktif dan hardware belum diuji. Kontrak, migrasi, serta aktivasi: `../docs/integration.md` dan `../docs/mqtt.md`.
