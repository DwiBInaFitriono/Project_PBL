# REST API Flutter dan MQTT perangkat

## Pemisahan koneksi

    Flutter -- HTTPS REST + Bearer token --> Laravel /api/v1
    Perangkat -- MQTT TLS --> Broker --> Laravel subscriber
                                        |
                                        v
                               SQLite sensor_readings
                                        |
                          Web Laravel + REST Flutter

Flutter tidak menyimpan kredensial broker, tidak berlangganan MQTT, dan tidak mengirim telemetry ke REST API. Broker tidak diakses langsung oleh browser. Subscriber Laravel berjalan sebagai proses CLI terpisah; web dan REST membaca database yang sama.

Status aktivasi REST lokal: Sanctum dan tabel personal_access_tokens sudah diaktifkan atas persetujuan pengguna setelah backup SQLite yang tervalidasi. Data users dan sensor_readings dibandingkan sebelum/sesudah migrasi dan tidak berubah. Migrasi mqtt_messages masih pending. Broker MQTT belum diaktifkan/dihubungkan. MQTT tidak sama dengan koneksi browser ke server atau umur pembacaan sensor.

## REST v1

Base URL contoh lokal: `http://127.0.0.1:8000/api/v1`. Android emulator memakai `10.0.2.2`, bukan localhost komputer. Gunakan HTTPS untuk deployment; HTTP hanya pengembangan yang diaktifkan eksplisit.

Semua permintaan memakai `Accept: application/json`. Login memakai `Content-Type: application/json`. Route selain login memerlukan header Authorization dengan skema Bearer dan token hasil login. Tidak ada token pada URL.

| Metode | Path | Fungsi |
| --- | --- | --- |
| POST | `/auth/login` | Email, password, device_name; mengeluarkan token 24 jam |
| GET | `/me` | ID/nama/email/peran akun sendiri |
| POST | `/auth/logout` | Cabut token saat ini; HTTP 204 |
| GET | `/monitoring` | Snapshot dua node; query node=1 atau node=2 opsional |
| GET | `/history` | Pembacaan tersimpan, 25 baris per halaman |
| GET | `/settings/esp` | Rancangan konfigurasi; hanya Operator |

Login menerima `{email, password, device_name}` dan mengembalikan `{token, token_type: "Bearer", expires_at, user: {id, name, email, role}}`. Password tidak dikembalikan. Token disimpan sebagai hash oleh Sanctum. Token hanya memiliki kemampuan `mobile:read`; endpoint ini tidak mengubah profil, password, role, atau konfigurasi perangkat. Perubahan sandi melalui model User dan pencabutan token dijalankan dalam satu transaksi: kegagalan pencabutan membatalkan perubahan sandi. Login memegang lock sebelum memeriksa password dan menerbitkan token (SQLite memakai no-op UPDATE untuk write lock). Jangan memperbarui password lewat raw SQL/query builder yang melewati event model. Jalur akun ditemukan/tidak ditemukan sama-sama memeriksa hash, dengan timebox minimum 200 ms; ini mitigasi timing, bukan jaminan constant-time jaringan.

`/monitoring` mengembalikan `{data: {nodes, sensors, generatedAt, staleAfterSeconds, pollIntervalSeconds}}`. Nilai `null` berarti belum ada pembacaan; 0 tetap angka nol. Statistik/pembacaan mengikuti snapshot server; koneksi broker tidak otomatis membuktikan perangkat online.

`/history` menerima `node`, `sensor`, `from`, `to`, `timezone`, `page`. Format tanggal YYYY-MM-DD, default WIB (`Asia/Jakarta`), rentang maksimal 366 hari inklusif. Respons `{data: [...], meta: {current_page,last_page,per_page,total}, filters: {...}}`. Data waktu disimpan UTC.

Kesalahan memakai status HTTP: 401 autentikasi salah/token hilang-kedaluwarsa, 403 scope/peran ditolak, 422 validasi, 429 pembatasan permintaan. Flutter tidak boleh menganggap error atau respons non-JSON sebagai data kosong/sukses.

## Persiapan aktivasi Laravel

1. Cadangkan SQLite dengan API backup SQLite yang konsisten. Jangan `migrate:fresh` atau reset akun.
2. Minta persetujuan migrasi live, periksa `php artisan migrate:status`, lalu jalankan hanya file migrasi tambahan yang telah ditinjau lewat `php artisan migrate --path=database/migrations/NAMA_FILE --no-interaction`. Jangan menjalankan seluruh pending migration tanpa review.
3. Tentukan host HTTPS, CORS Flutter web melalui `MOBILE_API_ORIGINS` (daftar origin dipisah koma). Default pengembangan hanya localhost/127.0.0.1 port 8091, bukan wildcard. CORS tidak menggantikan autentikasi.
4. Jalankan Laravel pada alamat yang dapat diakses klien. Host persis dari APP_URL serta localhost/127.0.0.1/[::1] diizinkan; host tambahan harus ditulis eksplisit pada APP_ALLOWED_HOSTS (dipisah koma, tanpa skema/port/wildcard). Periksa Host langsung dan forwarded host saat mengatur trusted proxy. Setelah mengubah konfigurasi produksi, bangun ulang config cache. Vite HMR hanya di lingkungan local dan memerlukan CSP_VITE_ORIGIN yang sama persis dengan origin Vite, misalnya http://localhost:5173; origin WebSocket turunannya diizinkan otomatis. Tanpa konfigurasi itu gunakan aset npm run build. Endpoint browser log Boost dimatikan melalui config/boost.php; tool CLI tetap tersedia.
5. Tentukan broker, TLS/CA, autentikasi, dan ACL sebelum menyalakan subscriber. Perangkat hanya boleh publish topik node miliknya; subscriber Laravel hanya subscribe telemetry. Gunakan broker privat; jangan public broker untuk data/kredensial nyata.
6. Jalankan subscriber lewat process supervisor setelah skema/payload perangkat disepakati. Perintah dan kontrak terperinci: `docs/mqtt.md`.

Login akun Operator nyata melalui /api/v1 telah diuji: login dan endpoint baca merespons 200, logout 204, token dicabut ditolak 401. Identitas/kata sandi tidak ditanam pada source atau fixture. Jangan menaruh kata sandi akun atau broker ke source, README, fixture permanen, atau chat.

## Flutter

UI Flutter terhubung ke `android-app/lib/api_client.dart`: login/logout nyata, monitoring berkala, grafik dari titik server, riwayat dengan filter WIB/pagination, profil hanya baca, dan konfigurasi ESP untuk Operator. Error/401 tidak ditampilkan sebagai data kosong; 401 mengakhiri sesi dan polling. Filter/rentang grafik 1/6/24 jam dihitung dari snapshot server, bukan data buatan. Tanpa API_BASE_URL, aplikasi tetap menyediakan mode pratinjau eksplisit. URL invalid menonaktifkan login, bukan mengalihkan ke server lain.

URL factory klien ditentukan saat build dengan `--dart-define=API_BASE_URL=https://server.example/api/v1`. Untuk localhost pengembangan, gunakan opsi eksplisit `--dart-define=ALLOW_INSECURE_LOCAL_API=true`. Build web lokal menggunakan `http://127.0.0.1:8000/api/v1` dan dilayani pada port 8091 (sesuai CORS). APK debug lokal menggunakan `http://10.0.2.2:8000/api/v1` untuk emulator; bukan alamat HP fisik.

Untuk APK emulator tersebut, jalankan Laravel dari root proyek melalui Git Bash:

    APP_DEBUG=false APP_ALLOWED_HOSTS=10.0.2.2 php artisan serve --host=127.0.0.1 --port=8000 --no-interaction --no-reload

`10.0.2.2` bukan Host yang diizinkan secara default. Override ini hanya untuk proses lokal, tidak mengubah `.env` atau allowlist produksi. `--no-reload` diperlukan agar proses server PHP mempertahankan override environment; restart manual setelah perubahan konfigurasi. Bind tetap `127.0.0.1`, jangan dibuka ke seluruh LAN. Permintaan `GET /api/v1/me` tanpa token melalui Host emulator harus menghasilkan 401 setelah konfigurasi, bukan 400; Host lain yang tidak terdaftar tetap ditolak 400.

Manifest utama Android menyatakan izin INTERNET. Network security config khusus debug mengizinkan cleartext hanya localhost/127.0.0.1/10.0.2.2; base-config menolak cleartext global. Release tidak membawa pengecualian debug. Untuk produksi atau HP fisik gunakan host HTTPS yang dapat dijangkau, bukan 10.0.2.2.

Token hanya disimpan dalam memori proses. Menutup/reload aplikasi memerlukan login ulang. Tidak ada penyimpanan plaintext token/password. Jika nanti membutuhkan login persisten pada Android, integrasikan penyimpanan aman platform sebagai pekerjaan terpisah.

## Verifikasi

    php artisan test --compact
    php artisan route:list --path=api
    flutter test
    flutter analyze

Tes backend memakai SQLite in-memory; data dan identitas fixture bukan data perangkat/akun pengguna. Jangan menjalankan subscriber pengujian pada database asli.

`tests/Browser/support/flutter-live.mjs` adalah smoke test UI nyata yang dijalankan terpisah hanya atas izin login akun. Ambil kredensial dari environment REBUNG_SMOKE_EMAIL/REBUNG_SMOKE_PASSWORD, jangan menanam nilainya di file. Test login Flutter web → monitoring → profil → riwayat → ESP → logout, mencatat hanya metode/path/status, bukan password/token. Screenshot tersimpan di android-app/build/live-*.png. Uji transport lokal tidak membuktikan hardware/MQTT produksi terhubung.
