# Rebung Pintar — Aturan Proyek

Baca file ini sebelum mengubah aplikasi. Pertahankan keputusan di bawah kecuali pengguna meminta perubahan. File ini adalah sumber aturan desain dan implementasi proyek, bukan pengganti konfigurasi runtime Laravel.

## Identitas dan ruang lingkup
- Nama produk: **Rebung Pintar**. Bahasa antarmuka: Bahasa Indonesia.
- Tujuan aplikasi: monitoring rebung bambu menggunakan **node 1** dan **node 2**, bukan aplikasi pembelajaran. Nama node bukan role pengguna. Setiap node memiliki minimal tiga kanal sensor. Parameter awal usulan: suhu udara (°C), kelembapan udara (% RH), dan kelembapan tanah (%), didefinisikan di config/monitoring.php. Ini rancangan yang belum dikonfirmasi terhadap perangkat fisik; protokol, sensor fisik, kalibrasi, interval, dan ambang alarm belum ditentukan.
- Database aplikasi tetap SQLite. Perubahan tampilan tidak boleh mengubah database atau akun.
- Stack: Laravel 13, PHP minimal 8.3, Blade, CSS melalui Vite/Tailwind 4, JavaScript vanilla. Ikuti composer.lock dan package-lock.json untuk versi tepat.
- Direktori proyek: `D:\Project Laravel\Project PBL\template`.
- Halaman autentikasi nyata, bukan simulasi localStorage: login, register, logout, serta dashboard monitoring setelah login. Integrasi perangkat/data node, OAuth, reset password, dan verifikasi email belum tersedia. Jangan tampilkan tombol/link fitur yang belum tersedia.

## Sumber palet
Acuan pengguna adalah `download.jpg` di root proyek. Jangan menghapus/mengubah gambar tersebut. Warna di bawah adalah sampel piksel dominan JPEG, bukan klaim kode HEX yang terbaca dari teks gambar; artefak kompresi dapat menghasilkan sedikit perbedaan.

| Token | HEX | Penggunaan |
| --- | --- | --- |
| `--palette-ivory` | `#F2F1EF` | Latar terang, teks pada permukaan navy |
| `--palette-yellow` | `#F7B700` | Aksen, tombol mode gelap |
| `--palette-navy` | `#011E60` | Identitas, teks pada mode terang, tombol utama |
| `--palette-blue` | `#6A7FC0` | Aksen pendukung |

- Seluruh token ada di `resources/css/app.css`. Gunakan token semantik `--surface`, `--ink`, `--muted`, `--field`, `--border`, `--button-bg`, `--button-ink`, `--focus`, dan `--error`.
- Warna turunan navy digunakan untuk mode gelap: latar `#0A1530`, bidang input `#111F40`. Warna semantik merah diperbolehkan hanya untuk error.
- Jangan menggunakan kuning atau biru palet sebagai teks kecil pada latar ivory tanpa memeriksa kontras. Target teks normal minimal 4.5:1.

## Tata letak dan tipografi
- Login/register memprioritaskan formulir (surface Configure). Dashboard adalah surface Monitor: ringkasan, kartu node, dan riwayat; bukan landing page pemasaran.
- Login dan register: satu kolom, formulir terpusat dengan label rata kiri, lebar sekitar 400–440px. Hapus panel samping/banner/dekorasi besar, bukan sekadar menyembunyikannya pada mobile. Jangan mengembalikan layout dua kolom.
- Header berisi logo dan tombol tema; footer ringkas. Desktop maupun mobile tidak boleh memiliki scroll horizontal.
- Halaman login harus muat satu layar tanpa scroll pada viewport normal (diuji sampai 320×568 dan laptop 1280×600). Sesuaikan jarak vertikal dengan tinggi layar; sembunyikan hanya teks dekoratif pada layar pendek. Jangan memakai `overflow: hidden` atau tinggi tetap yang memotong input, tombol, pesan error, atau akses saat zoom/keyboard virtual. Register tidak dipaksa mengikuti batas tinggi login.
- Penyesuaian tinggi login dibatasi ke `data-page="login"`, bukan ke dashboard atau seluruh layout bersama.
- Font UI lokal: Trebuchet MS, Segoe UI, sans-serif. Tidak bergantung pada CDN font.
- Login/register memakai `resources/views/components/auth-layout.blade.php`; dashboard memakai `components/dashboard-layout.blade.php`. Identitas pada `components/brand.blade.php`; inisialisasi tema/aset pada `components/page-head.blade.php`; tombol tema bersama pada `components/theme-toggle.blade.php`.
- Radius bidang/tombol 8px; target sentuh >=44px; label tetap terlihat, fokus keyboard jelas. Tidak memakai placeholder sebagai pengganti label.
- Teks menjelaskan monitoring rebung bambu melalui node 1 dan node 2; tidak memakai narasi perjalanan belajar. Grafik tanpa sumber nyata harus kosong pada mode perangkat. Simulasi eksplisit boleh disediakan untuk pratinjau desain, tetapi wajib diberi label dan tidak boleh mengaku sebagai data perangkat atau status online.
- Hormati `prefers-reduced-motion`.

## Tema dan interaksi
- Tema dikendalikan atribut `data-theme="light|dark"` pada `<html>`.
- Simpan preferensi eksplisit pada localStorage `rebung-pintar-theme`; hanya nilai `light` dan `dark` yang valid. Jangan menyimpan password, token autentikasi, atau data akun di localStorage.
- Tanpa preferensi tersimpan, ikuti `prefers-color-scheme` OS. Storage yang diblokir tidak boleh membuat halaman gagal.
- Tema diterapkan sebelum render untuk menghindari kilatan tema salah.
- Tombol tema hanya ikon, tanpa teks terlihat, pada semua ukuran layar: bulan untuk mengaktifkan mode gelap dan matahari untuk mengaktifkan mode terang. Bentuk lingkaran 44×44px; pertahankan fokus keyboard, `aria-label`, tooltip `title`, serta `aria-pressed` yang mengikuti keadaan.
- Tombol mata hanya mengubah visibilitas password, `type="button"`, label aksesibel dan status sesuai keadaan.

## Autentikasi dan keamanan
- Route GET: `/`, `/login`, `/register`; pengguna terautentikasi diarahkan ke `/dashboard`.
- POST `/login` bernama `login.store`, POST `/register` bernama `register.store`, POST `/logout` bernama `logout`.
- `/dashboard` membutuhkan middleware auth. Gunakan named routes di Blade dan `@csrf` pada semua form POST.
- Validasi server-side; email di-trim/lowercase; email unik; password minimal 8 karakter dan dikonfirmasi. Tampilkan error dalam Bahasa Indonesia.
- Password di-hash oleh cast model User. Regenerasi sesi setelah autentikasi; invalidate sesi dan regenerasi CSRF saat logout.
- Batasi upaya login berdasarkan email+IP. Jangan membocorkan apakah email terdaftar melalui pesan gagal login.
- Pertahankan database yang dikonfigurasi pengguna. Jangan menjalankan migrate:fresh, menghapus data, atau mengganti `.env` tanpa permintaan.
- Tes backend memakai SQLite in-memory dengan RefreshDatabase. Jangan membuat akun pengujian permanen di database pengguna tanpa permintaan eksplisit.
- Akun baru default Pemantau (`viewer`); Operator (`operator`) ditetapkan eksplisit, bukan berdasarkan nama pengguna. Jangan menanam password ke seeder, kode, rule.md, atau localStorage; jangan menimpa akun yang sudah ada. Penetapan akun Operator dilakukan hanya atas izin pengguna.

## Dashboard monitoring
- `/dashboard` dilindungi middleware auth dan dilayani `DashboardController`.
- Tampilkan dua node tetap: Node 1 dan Node 2, masing-masing tiga kanal sensor usulan (total enam). Pada mode perangkat, status `Menunggu integrasi`, pembacaan terakhir null, nilai `—`, dan readings kosong. Null bukan nilai nol. Status koneksi tidak boleh berubah menjadi online/offline hanya karena simulasi ditampilkan.
- Dashboard memuat pembaruan snapshot server berkala tanpa reload halaman; kegagalan jaringan mempertahankan pembacaan terakhir dan menampilkan status gagal. Sesi habis menghentikan polling. Bedakan umur pembacaan, koneksi browser-server, dan koneksi fisik ESP; jangan menyamakan ketiganya.
- Sensor/rentang 1/6/24 jam disinkronkan ke URL dengan History API, termasuk reload, link Muat ulang, tombol kembali/maju, serta tautan aktif sidebar. Parameter tidak valid ditolak server, bukan diam-diam diperbaiki.
- Grafik membandingkan parameter yang sama untuk dua node; jangan mencampur °C dan persen pada satu sumbu. Pilihan parameter dan rentang 1/6/24 jam memperbarui grafik, statistik, serta tabel riwayat.
- Data perangkat adalah mode awal setiap reload. Tombol `Lihat simulasi grafik` mengaktifkan data contoh buatan secara lokal; tampilkan label `SIMULASI — bukan data perangkat` pada notifikasi, nilai, grafik, statistik, dan tabel. Tidak disimpan di database/localStorage dan tidak menggantikan integrasi perangkat.
- Kurva contoh memakai waktu relatif, bukan timestamp pembacaan sungguhan. Snapshot contoh tetap; jangan menyebutnya real-time atau normal/aman tanpa sumber dan ambang tervalidasi.
- Warna/garis dua node memakai solid dan dashed agar tidak hanya dibedakan lewat warna. SVG responsif dengan tabel alternatif; tidak memakai CDN chart library.
- Tampilan desktop dua kartu berdampingan; <=760px ditumpuk. Dashboard boleh scroll, tetapi tidak horizontal. Ketentuan satu layar hanya untuk login.
- CSS dashboard ada di `resources/css/dashboard.css`, diimpor oleh app.css dengan token warna yang sama.
- Tes browser dashboard merender view Laravel asli memakai User tanpa disimpan dan storage sementara. Ini tes UI, bukan bukti integrasi perangkat. Login/logout end-to-end akun pengguna diverifikasi terpisah.

## Lokasi implementasi
- `routes/web.php`: route.
- `app/Http/Controllers/AuthController.php`: alur autentikasi.
- `app/Http/Requests/`: validasi input autentikasi.
- `resources/views/auth/login.blade.php`: halaman login.
- `resources/views/auth/register.blade.php`: halaman register.
- `config/monitoring.php`: tiga parameter sensor usulan dan satuannya.
- `app/Http/Controllers/DashboardController.php`: struktur dua node dan kanal sensor kosong.
- `resources/views/dashboard.blade.php`: dashboard monitoring.
- `resources/css/dashboard.css`: layout dashboard.
- `resources/css/app.css`: desain dan token.
- `resources/js/app.js`: pergantian tema dan visibilitas password, mengimpor monitoring.js.
- `resources/js/monitoring.js`: simulasi eksplisit, SVG grafik, filter, statistik, mini-grafik, serta riwayat yang tidak menulis ke database.
- `tests/Feature/AuthenticationTest.php`: tes backend.
- `tests/Feature/DashboardTest.php`: tes dashboard backend.
- `tests/Browser/auth.spec.js`: tes browser autentikasi.
- `tests/Browser/dashboard.spec.js`: tes browser dashboard dan tema.

## Menjalankan dan memverifikasi
Dari direktori proyek:

    npm run build
    php artisan serve

Buka alamat yang ditampilkan Artisan (umumnya http://127.0.0.1:8000). Untuk perubahan frontend langsung, jalankan Vite dari terminal kedua dengan origin loopback yang tetap:

    npm run dev -- --host=127.0.0.1 --port=5173 --strictPort

Restart Laravel dari terminal pertama dengan izin CSP hanya untuk origin Vite tersebut (Git Bash, tanpa mengubah `.env`):

    APP_ENV=local APP_DEBUG=false CSP_VITE_ORIGIN=http://127.0.0.1:5173 php artisan serve --host=127.0.0.1 --port=8000 --no-interaction --no-reload

`CSP_VITE_ORIGIN` harus sama persis dengan skema, host, dan port Vite, tanpa path atau wildcard; origin WebSocket turunannya diizinkan otomatis. `--no-reload` mempertahankan override environment pada proses server PHP; restart manual setelah perubahan konfigurasi. Izin ini hanya berlaku saat `APP_ENV=local` dan diabaikan pada produksi. Produksi memakai aset `npm run build`, bukan server Vite. Untuk kembali ke aset build lokal, hentikan Vite dan jalankan kembali Laravel tanpa `CSP_VITE_ORIGIN`.

    php artisan test --compact
    npx playwright test --reporter=line
    php vendor/bin/pint --format agent

Playwright menggunakan server lokal port 8013. Konfigurasi dapat memilih Chrome yang sudah terpasang di Windows; gunakan env `BROWSER_EXECUTABLE` untuk lokasi browser lain. Jangan menguji fitur yang menulis data menggunakan database pengguna.

Pada terminal Git Bash lingkungan ini, wrapper Composer memiliki kendala path MSYS. Jika `composer` gagal dengan `Could not open input file`, jalankan:

    php "D:/Composer/composer.phar" --version

Gunakan pola yang sama untuk perintah Composer lainnya. Jangan mengubah instalasi global hanya untuk mengatasi wrapper tersebut.

## Sidebar dan halaman node
- Sidebar ada pada dashboard, detail node, dan halaman pengaturan; tidak pada login/register. Node 1 dan Node 2 memiliki dropdown interaktif berisi ringkasan, tiga parameter sensor, dan riwayat.
- `/nodes/{node}` memakai middleware auth dan hanya menerima ID `1` atau `2`. Query `sensor` memilih parameter grafik; ID atau parameter tak dikenal menghasilkan 404.
- Desktop memakai sidebar yang bisa diringkas dari 240px menjadi 80px (ikon saja) dengan tombol di samping judul header. Preferensi `rebung-pintar-sidebar-collapsed` di localStorage bersifat opsional dan hanya memuat boolean. Klik ikon node saat ringkas memperluas sidebar dan membuka submenu node tersebut.
- <=1024px tetap memakai drawer berlabel lengkap dengan overlay, tombol tutup, Escape, fokus terperangkap di dalam drawer, serta fokus kembali ke pemicu setelah ditutup. Mode ringkas desktop tidak mengecilkan drawer mobile.
- Header dashboard memakai teks 22px pada desktop dan 18–20px pada mobile. Menu utama sidebar 15px; submenu 12px. Node 1/2 memakai ikon chip dengan lencana nomor; seluruh submenu memakai ikon SVG sesuai sensor/ringkasan/riwayat, tanpa pustaka/CDN ikon. Ikon dekoratif disembunyikan dari pembaca layar; menu mode ringkas tetap punya nama aksesibel dan tooltip.
- Tombol/menu dashboard tidak memakai oranye/kuning sebagai status aktif. Gunakan aksen biru lembut `--dashboard-accent: #A1B2EA` pada menu sidebar aktif dan tombol sensor mode gelap, dengan teks navy. Tombol sensor mode terang tetap navy dengan teks ivory. Perubahan ini dibatasi ke dashboard/detail node, bukan login/register; warna seri grafik dan notifikasi tidak berubah.
- Setting Akun dipasang di area bawah sidebar yang tidak ikut menggulir, baik mode lebar, mode ikon, maupun drawer mobile. Hanya menu tengah `[data-sidebar-scroll]` yang overflow; jangan mengembalikan scroll ke seluruh aside.
- Scrollbar halaman dashboard dan menu sidebar memakai thumb biru membulat, track sesuai tema, hover lebih jelas, serta fallback Firefox/forced-colors. Tetap memakai scroll native tanpa library atau pengambilalihan wheel/touch; login/register tidak berubah.
- Transisi ringkas desktop memakai 280ms cubic-bezier(.22,1,.36,1) untuk width/margin-left dan transform ikon tombol. Label memudar melalui opacity tanpa mengubah posisi ikon/tinggi baris. Drawer mobile menunda visibility:hidden sampai gerakan keluar selesai dan overlay memudar. Jangan pakai display:none sebelum transisi keluar selesai; prefers-reduced-motion tetap menonaktifkan gerakan.
- Judul grup Pengaturan memakai Gate manage-esp yang sama dengan menu ESP sehingga tidak ada grup kosong bagi Pemantau. Setting Akun tetap tersedia di bawah untuk kedua peran.
- Warna serta pola garis grafik ditentukan oleh ID node, bukan posisi array, agar Node 2 tetap konsisten pada halaman detail.
- Implementasi sidebar: `resources/views/components/sidebar.blade.php`, `resources/js/ui/sidebar.js`, `resources/css/sidebar.css`. Tes: `tests/Feature/NodePageTest.php` dan `tests/Browser/sidebar.spec.js`.

## Pengaturan
- `/settings/esp` (`settings.esp`) dan `/settings/account` (`settings.account`) membutuhkan login. Menu Setting ESP ada di area menu; Setting Akun dipasang di bawah sidebar.
- Setting ESP saat ini hanya menampilkan rancangan node/parameter serta batas integrasi. Tidak ada koneksi perangkat, penyimpanan konfigurasi ESP, atau klaim status online tanpa implementasi protokol.
- PATCH `/settings/account` (`settings.account.update`) memperbarui hanya nama/email pengguna terautentikasi, dengan kata sandi saat ini sebagai konfirmasi. Email dinormalisasi dan unik; nama/email tervalidasi server-side; field lain tidak boleh di-mass-assign. Kata sandi tidak diubah atau disimpan di old input.
- Pembaruan profil dibatasi lima percobaan per pengguna per 60 detik, dengan pesan Bahasa Indonesia. Pengujian perubahan akun hanya memakai database in-memory, bukan akun pengguna nyata.
- CSS pengaturan di `resources/css/settings.css`; scrollbar di `resources/css/scrollbars.css`. Pengujian terkait di `tests/Feature/SettingsTest.php` dan `tests/Browser/{settings,navigation,scrollbars}.spec.js`.

## Riwayat, umur data, dan peran
- `sensor_readings` menyimpan node_id, sensor_id, value, recorded_at (UTC) dengan indeks filter. Integrasi/ingest ESP belum tersedia; jangan menambah pembacaan contoh ke database pengguna.
- Snapshot membaca maksimal 500 titik valid terbaru per sensor dalam 24 jam; nilai kartu dapat berasal dari pembacaan terakhir yang lebih lama dan wajib ditandai terlambat. Statistik grafik mengikuti titik yang dimuat, bukan klaim seluruh histori.
- Polling `/monitoring/data` default 15 detik, batas umur data default 300 detik di config/monitoring.php. Ini batas kesegaran data yang dapat disesuaikan, bukan ambang agronomi atau bukti online/offline perangkat.
- `/history` menyediakan filter node, sensor, tanggal UTC inklusif, default tujuh hari, maksimal 366 hari, pagination 25. `/history/export` mengekspor semua baris yang cocok (bukan hanya halaman aktif), dengan UTF-8 BOM dan perlindungan formula CSV. Semua route membutuhkan login.
- Operator dan Pemantau boleh monitoring/riwayat/ekspor serta mengubah profil sendiri. Gate `manage-esp` membatasi Setting ESP ke operator dan menyembunyikan menunya untuk pemantau; akses langsung ditolak server. Belum ada endpoint yang mengubah ESP fisik.
- Kolom users.role default viewer dan tidak mass-assignable. CLI `php artisan users:set-role {email} operator|viewer --no-interaction` menetapkan peran akun yang sudah ada; tidak membuat akun atau menampilkan kredensial.
- Sebelum migrasi live, backup SQLite dengan API backup yang konsisten. Gunakan migrasi aditif, jangan migrate:fresh. Browser fixture bermigrasi hanya di SQLite in-memory dan memakai identitas operator yang tidak disimpan.

- Dropdown Node/Parameter pada riwayat memakai combobox/listbox kustom sesuai tema, termasuk panel pilihan (bukan popup bawaan OS), navigasi keyboard, Escape/Tab, klik luar, serta pencarian ketik. Select asli tetap menyimpan nilai form dan menjadi fallback jika JavaScript gagal.
- Tombol Terapkan filter dan Reset filter berukuran sama, tinggi 44px dan jarak antartombol 16px. Filter desktop satu baris bila muat, tablet dua kolom, mobile satu kolom; jangan biarkan area aksi melebar memenuhi sisa ruang.
- Link Muat ulang pada dashboard/detail node hanya ikon 44×44px, dengan aria-label/title tetap Muat ulang dan query filter dipertahankan.

- Penolakan akses ditangani global untuk 401/403/419. HTML pengguna yang sudah login diarahkan ke Dashboard dengan peringatan; tamu diarahkan ke Login dengan peringatan lalu Dashboard setelah login. Dashboard tetap privat—jangan membuka akses tamu atau membuat loop redirect.
- API/JSON mempertahankan status401/403/419 dan mengirim code,message,redirect yang aman. Polling yang kehilangan akses berhenti, menonaktifkan pembaruan, menampilkan tautan pemulihan dan mengarahkan setelah3detik. Tidak memutar ulang POST/PATCH yang ditolak.
- Middleware AuthenticateSession memeriksa hash kata sandi sesi pada request web. Penggantian sandi memperbarui sesi saat ini, sedangkan sesi lama yang menyimpan hash sebelumnya ditolak.

## Kata sandi dan akses global
- Form ganti sandi terpisah memakai error bag changePassword, ID field unik, konfirmasi sandi saat ini, sandi baru minimal8karakter/max72byte, konfirmasi dan batas5percobaan/60detik. Sandi di-hash eksplisit; role/profil lain tidak ikut berubah. Remember token dirotasi dan sesi saat ini diregenerasi.
- Dropdown zona waktu telah dihapus atas permintaan; tampilan normal memakai WIB, database tetap UTC. Parameter lama UTC tetap divalidasi untuk kompatibilitas tautan, bukan menu baru.
- Header keamanan: nosniff, frame DENY, referrer strict-origin-when-cross-origin, batas permissions; CSP berbasis nonce untuk script/style, default-src self, serta larangan inline handler; output tetap wajib di-escape. HSTS hanya pada HTTPS produksi; tidak memaksa cookieSecure pada localhostHTTP.

- Login/register langsung mengisi fingerprint password_hash_web. Sesi lama yang mempunyai login tetapi belum mempunyai fingerprint wajib login ulang, sebelum AuthenticateSession dapat mengisinya. Pembaruan middleware tidak mengubah kata sandi akun nyata.
- Pendaftaran dibatasi10permintaan/menit/IP; ekspor5permintaan/menit/akun. Tetap validasi SQL dengan parameter binding dan escape semua HTML, termasuk label yang dirender di laporan.

## Responsif lintas perangkat
- Input autentikasi/profil/tanggal serta combobox memakai font minimal 16px agar terbaca dan menghindari auto-zoom fokus iOS; pinch zoom tidak dinonaktifkan.
- Viewport memakai `viewport-fit=cover`; token `--safe-area-*` berasal dari `env(safe-area-inset-*)` dan melindungi padding form, header, konten, serta drawer. Uji inset nonzero secara eksplisit karena emulasi desktop tidak memiliki notch fisik.
- Pada <=480px, sensor disusun per baris dengan nilai di kanan, status di bawah, dan sparkline hanya bila ada data. Pada <=760px teks sensor/kontrol minimal 12px dan badge tren dipindah ke bawah judul. Grid node desktop dan palet tetap dipertahankan.
- Tabel berisi pembacaan pada <=760px mempunyai lebar minimum 560px di region scroll horizontal berlabel yang dapat difokuskan; jangan mengecilkan teks ke 10px atau membuat seluruh halaman ikut melebar. Empty state tetap mengikuti lebar layar, dengan petunjuk geser hanya untuk tabel berisi data.
- Jangan membatalkan `pointerdown` daftar opsi: WebKit sentuh dapat kehilangan event click. Cegah perpindahan fokus mouse melalui `mousedown` saja, agar tap dan scroll sentuh tetap native.
- `tests/Browser/responsive.spec.js` mencakup seluruh halaman, lebar 320–2560px, portrait/landscape, inset, viewport pendek, operator/viewer, tabel terisi, serta emulasi sentuh. `BROWSER_ENGINE=webkit` atau `BROWSER_ENGINE=firefox` memilih mesin uji selain Chromium tanpa mengganti dependency proyek. Emulasi bukan pengujian pada perangkat fisik.

## Flutter Android
- Aplikasi Flutter berada di `android-app/` dengan nama package Dart `rebung_pintar`, target Android dan web untuk pratinjau lokal. Aplikasi Laravel dan database tetap terpisah/tidak diubah.
- Mode tanpa API_BASE_URL menyediakan login dan dashboard pratinjau: ringkasan, enam kanal sensor, detail Node 1/2, pilihan parameter/rentang 1/6/24 jam, statistik kosong, riwayat dengan filter tanggal, Setting ESP, dan form akun/sandi nonaktif. Akses melalui tombol `Lihat dashboard (pratinjau)`, bukan login palsu. Pada mode REST kredensial dikirim hanya ke login Laravel; sandi dikosongkan setelah sukses dan token hanya di memori.
- Sidebar desktop dapat diringkas; mobile memakai drawer dan Setting Akun terpisah di bawah. Filter riwayat bertahan selama sesi pratinjau. Back menelusuri tujuan internal sebelum kembali ke login; tombol Tutup pratinjau keluar langsung. Sensor aktif ditandai dan ikon node ringkas membuka submenu.
- Kalender dan komponen bawaan menggunakan `flutter_localizations` SDK dengan locale Indonesia (penambahan dependency diizinkan pengguna). Tanggal WIB disimpan sebagai nilai tanggal UTC untuk perhitungan rentang lokal yang tidak terpengaruh DST komputer; tombol tanggal mempunyai label aksesibel. Uji validasi rentang, pembesaran teks 2x, dan menu yang dapat disentuh, bukan hanya tanpa overflow.
- Desain Flutter terbaru memakai ivory/netral hangat sebagai bidang utama, teks charcoal, aksen kuning palet untuk aksi/seleksi, serta navy/periwinkle secukupnya; jangan kembali ke layar dominan biru. Keputusan ini khusus Flutter, bukan perubahan palet web Laravel.
- Ringkas teks Flutter: nilai, label sensor/satuan, judul pendek, dan badge status. Keterangan integrasi panjang tersedia lewat tombol info; jangan mengulang status kosong di setiap sensor. Tetap tampilkan pratinjau dan jangan mengganti data kosong dengan nol.
- Mode pratinjau tanpa URL tidak melakukan polling. Mode REST melakukan polling satu-per-satu, mempertahankan data terakhir pada gagal jaringan dan berhenti pada 401/logout. Profil mobile hanya baca; mutasi akun/sandi dan Ekspor PDF belum disediakan oleh API mobile.
- Verifikasi dari `android-app`: `flutter test`, `flutter analyze`, `flutter build web --no-web-resources-cdn`, `flutter build apk --debug`. Pratinjau browser memakai hasil Flutter asli, bukan HTML pengganti. Menjalankan di browser bukan bukti sudah berjalan di emulator/ponsel Android.
- Pada terminal Git Bash mesin ini, wrapper shell `flutter` dapat menggantung; gunakan executable SDK `flutter.bat` melalui subprocess Windows, tanpa mengubah SDK global. Gunakan timeout panjang untuk build Android pertama karena Gradle dapat mengunduh komponen SDK berlisensi yang sudah diterima.

## REST dan MQTT (persiapan)
- Pemisahan wajib: Flutter hanya REST HTTPS `/api/v1`; hardware publish MQTT TLS ke broker privat, subscriber Laravel menulis database untuk web dan REST. Jangan menambah MQTT langsung ke Flutter/browser atau endpoint telemetry publik REST.
- Paket diizinkan: Laravel Sanctum, php-mqtt/client, HTTP Flutter. Migrasi token REST telah diaktifkan dengan persetujuan pengguna setelah backup SQLite konsisten dan verifikasi akun/pembacaan tetap sama. Migrasi mqtt_messages tetap pending; aktivasi MQTT masih memerlukan persetujuan terpisah dan backup.
- REST tersedia untuk login/logout token 24 jam, profil baca, monitoring, riwayat paginasi, dan konfigurasi ESP operator. Token scope mobile:read, bearer-only (bukan cookie web), CORS allowlist, rate limit, pencabutan token setelah perubahan password. Tidak ada endpoint mutasi profil/ESP baru pada tahap ini.
- UI Flutter memakai ApiClient bila API_BASE_URL valid: login/logout nyata, polling monitoring, grafik nilai server, riwayat WIB/pagination, profil hanya baca, dan konfigurasi ESP Operator. Klien mempunyai timeout/validasi JSON, token privat memori, clear pada 401/logout, tanpa redirect/retry otomatis. Tanpa URL tetap pratinjau eksplisit; konfigurasi invalid menonaktifkan login. Jangan menyimpan password/token pada source/localStorage.
- MQTT default false dengan host kosong. Validasi JSON/topic/node/timestamp/nilai, transaksi dan UUID dedup. Batas 8192 byte di aplikasi harus dilengkapi broker packet limit; QoS1 ACK library terjadi sebelum commit, bukan exactly-once end-to-end. Baca docs/mqtt.md sebelum aktivasi.
- Tes MQTT menggunakan fixture wire loopback sementara dan SQLite in-memory. Tes lintas runtime memakai tests/Browser/support/rest-server.php dengan SQLite temporary khusus dan password acak env; bukan akun operator nyata. Hapus fixture dan cabut token uji sesudah eksekusi.
- Login REST memegang lock sampai penerbitan token; SQLite memakai no-op UPDATE sebelum SELECT karena FOR UPDATE diabaikan. Simpan password model dan revoke token dalam transaksi yang sama. Jangan gunakan raw SQL untuk perubahan password. Jalur email tak ditemukan tetap hash-check dengan dummy hash dan timebox minimum; jangan mengklaim constant-time jaringan.
- Subscriber wajib memverifikasi SUBACK sukses untuk setiap topik melalui repository library; subscribe() berhasil bukan bukti ACL disetujui. Penolakan/konfirmasi hilang menghasilkan exit nonzero dengan batas konfirmasi 10 detik (1–60). Mode --once tidak boleh sukses sebelum semua topik dikonfirmasi, meskipun pesan pertama sudah masuk.
- Kontrak dan konfigurasi lengkap: docs/integration.md dan docs/mqtt.md.

## Pengamanan aplikasi dan server lokal
- Input akun hanya melalui FormRequest tervalidasi/allowlist; peran tidak dapat diisi dari registrasi/profil. Belum ada fitur upload, sehingga jangan menambah jalur upload hanya untuk checklist keamanan. Jika ditambahkan kelak, validasi isi/MIME/ukuran, nama hash, penyimpanan non-eksekutabel dan kontrol akses wajib.
- Seluruh mutasi web tetap POST/PATCH dengan CSRF; REST mobile tetap Bearer-only, bukan autentikasi cookie. Blade/DOM tetap escape teks pengguna dan tidak memakai raw HTML untuk input luar.
- SecurityHeaders berjalan global termasuk respons API, 404/405/500, serta no-store pada respons dinamis/form/token. HSTS hanya HTTPS produksi. CSP Laravel memakai default-src/self, script dan style dengan nonce acak per respons, menolak inline handler/style attribute, tanpa unsafe-inline/unsafe-eval. Tema sebelum render dan skrip/cetak laporan membawa nonce. Escape output tetap wajib.
- APP_ENV=production memaksa debug false. Respons exception 5xx saat debug false menampilkan pesan umum Bahasa Indonesia tanpa detail internal, tetap menjaga status dan Retry-After. Konfigurasi cache produksi harus dibuat ulang saat deploy.
- Login web mempunyai batas IP 20/menit di samping email+IP existing; dashboard, halaman node, dan monitoring/data berbagi batas 120/menit/pengguna; filter invalid ditolak sebelum query sensor. Rate limiting aplikasi bukan pengganti mitigasi DDoS pada reverse proxy/infrastruktur.
- Semua varian .env.* diabaikan Git kecuali .env.example. Document root Laravel wajib public/, bukan root proyek. Jangan menyalin rahasia ke build Flutter.
- Preview Flutter lokal memakai android-app/serve_web.py, hanya bind 127.0.0.1:8091 dan hanya melayani build/web. Dotfile, traversal, symlink keluar root, directory listing, dan mutasi ditolak. Header CSP membatasi koneksi ke origin sendiri dan http://127.0.0.1:8000; bila API deployment berubah gunakan konfigurasi server produksi yang sesuai. Host server preview hanya localhost/127.0.0.1; restart proses setelah perubahan Python. Server ini bukan untuk produksi.
- Flutter membatasi body sukses REST 2 MiB saat streaming, membatalkan request/subscription pada timeout, serta memproses status error tanpa membaca body. Pesan server tidak ditampilkan langsung. Respons 401 membuang sesi meskipun body belum selesai.
- Token Flutter hanya dalam memori dengan expiry valid berzona waktu dan belum kedaluwarsa. Logout segera menghapus token dan subtree privat, lalu mencabut token server; overlay/drawer tidak boleh menahan dashboard setelah logout/401. Respons lama tidak boleh menghapus login baru. Snapshot hanya menerima ID node string kanonis unik 1/2 sebelum render. HTTP opt-in hanya localhost, 127.0.0.1, atau 10.0.2.2; LAN/host produksi wajib HTTPS. Redirect HTTP login/bearer tidak diikuti.
- Tes server preview: python android-app/serve_web_test.py. Tes integrasi memakai identitas acak dan SQLite sementara terisolasi, bukan akun/database pengguna. Laravel lokal dijalankan dengan APP_DEBUG=false tanpa mengubah .env.

## Pengamanan deployment
- Host Laravel memakai allowlist persis APP_URL dan APP_ALLOWED_HOSTS; loopback tetap diizinkan. Tidak memakai wildcard atau melewati validasi pada local/testing. Host langsung dan effective trusted-proxy wajib lolos.
- Emulator Android memakai Host `10.0.2.2` yang ditolak secara default, termasuk produksi. Jalankan Laravel lokal dengan `APP_DEBUG=false APP_ALLOWED_HOSTS=10.0.2.2 php artisan serve --host=127.0.0.1 --port=8000 --no-interaction --no-reload`; jangan ubah `.env`, tambahkan alias ke produksi, atau buka bind ke seluruh LAN. Tes HostAllowlistTest memastikan API tanpa token beralih dari 400 menjadi 401 setelah izin eksplisit, sementara Host lain tetap ditolak.
- CSP_VITE_ORIGIN mengizinkan origin Vite/WebSocket secara eksplisit hanya saat local; produksi memakai hasil npm run build. Browser-log watcher Boost dimatikan, tool CLI tetap aktif.
- Android release memakai keystore privat, bukan debug key; build tanpa konfigurasi signing ditolak. Jangan commit key.properties atau berkas signing. Release tanpa API_BASE_URL tetap pratinjau tanpa koneksi jaringan. Backup signing terenkripsi wajib sebelum distribusi.

## Perubahan selanjutnya
- Baca aturan ini serta AGENTS.md sebelum mengubah aplikasi.
- Tambahkan tes perilaku yang gagal terlebih dahulu, implementasikan, lalu jalankan ulang.
- Uji login/register, error form, tema setelah reload/navigasi, password toggle, desktop dan mobile.
- Build ulang aset setelah perubahan CSS/JS. Jangan mengklaim hasil visual sudah diperiksa jika hanya uji DOM yang dijalankan.
- Catat perubahan keputusan desain/arsitektur di file ini agar konsisten pada sesi berikutnya.
