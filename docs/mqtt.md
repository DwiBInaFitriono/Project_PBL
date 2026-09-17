# Persiapan MQTT perangkat → Laravel

**Status: rancangan protokol, belum dikonfirmasi terhadap hardware.** Subscriber nyata memakai `php-mqtt/client` v2.3.2 (MQTT 3.1.1), tetapi default `MQTT_ENABLED=false`, host kosong, dan tidak berjalan otomatis. Pengerjaan ini tidak menjalankan migrasi database pengguna, mengubah akun, mengubah `.env`, atau menghubungi broker publik/perangkat fisik.

## Kontrak telemetri usulan

Publish ke topik persis `rebung-pintar/v1/nodes/1/telemetry` atau `rebung-pintar/v1/nodes/2/telemetry`; QoS 1 disarankan, **retain=false** wajib. Identitas node dan sensor bersumber dari `config/monitoring.php`. Payload UTF-8 JSON maksimal **8192 byte**:

```json
{
  "schema_version": 1,
  "message_id": "5ef03860-30ae-44b2-a946-2ec66b397510",
  "node_id": "1",
  "recorded_at": "2026-09-16T07:00:00+07:00",
  "readings": {
    "temperature": 27.5,
    "air_humidity": 70,
    "soil_moisture": 55
  }
}
```

- Ini contoh bentuk pesan, **bukan pembacaan hardware**. Ganti UUID dan waktu untuk setiap sampel baru. Pengiriman ulang sampel yang sama harus memakai UUID yang sama.
- Kelima field wajib; field tambahan ditolak. `schema_version` harus integer `1`; `node_id` harus string dan sama dengan node pada topik. UUID divalidasi sebelum disimpan dalam huruf kecil agar variasi kapital tidak melewati deduplikasi.
- `readings` harus objek berisi 1–3 parameter yang dikenal: `temperature`, `air_humidity`, `soil_moisture`. Parameter boleh tidak lengkap; parameter yang tidak dikirim tidak dibuat sebagai nol.
- Nilai harus angka JSON berhingga. String angka, boolean, null, array, objek, NaN/Infinity, overflow angka, sensor asing, dan objek kosong ditolak seluruhnya. Rentang agronomi belum ditetapkan; nilai negatif/nol berhingga bukan otomatis invalid.
- Waktu memakai profil RFC3339 dengan `T` dan `Z` kapital atau offset numerik `±HH:MM`; pecahan detik opsional 1–6 digit. Tanggal mustahil, zona waktu hilang, offset tak diketahui `-00:00`, leap second, dan waktu **lebih dari 60 detik** di masa depan ditolak. Pecahan diperiksa sebelum penyimpanan berpresisi detik pada schema existing.
- Semua waktu dikonversi UTC termasuk pergantian tanggal. Pesan historis non-retained boleh masuk; snapshot existing menentukan kesegaran, bukan koneksi fisik. Sinkronisasi jam perangkat/NTP wajib disiapkan.
- Pesan dengan flag retained ditolak. MQTT 3.1.1 dapat meneruskan publish retained kepada subscriber yang sedang aktif dengan flag retain yang tidak menunjukkan asal publish; larangan retained juga harus diterapkan di firmware/broker, bukan hanya callback.

## Penyimpanan dan batas jaminan

`TelemetryIngestor` memvalidasi seluruh pesan sebelum penulisan. Satu transaksi menyimpan penanda di `mqtt_messages` dan 1–3 baris ke model `SensorReading` existing. Kegagalan sensor mana pun membatalkan semuanya. Unique index `(node_id, message_id)` mencegah insert ganda; hasil `duplicate` tidak menulis/mengubah baris. UUID sama pada node berbeda boleh digunakan. Payload tidak disimpan di tabel deduplikasi; jangan menghapus penanda deduplikasi tanpa kebijakan retensi karena replay lama bisa diterima kembali.

Ini **bukan jaminan exactly-once end-to-end**. Source v2.3.2 mengirim PUBACK QoS 1 sebelum callback/persistensi. Crash/kegagalan DB dapat kehilangan sampel yang sudah di-ACK. Clean session dan repository in-memory tidak menyimpan antrean broker lintas restart. Sebelum produksi yang memerlukan durability, sepakati outbox/retry perangkat dengan UUID stabil dan application-level ACK atau adapter dengan ACK setelah commit. Tidak ada ACK aplikasi/publisher perintah ESP pada implementasi ini.

Library juga menelan exception callback. Subscriber menangkap kegagalan penyimpanan, menghentikan loop, dan mengembalikan exit nonzero di luar callback. Kegagalan koneksi/DB tidak dicetak beserta payload/password; logger library memakai `NullLogger` karena debug library dapat mengandung payload. Output CLI hanya hitungan diterima/duplikat/ditolak dan pesan error generik.

SUBACK wajib dikonfirmasi untuk **setiap topik yang diminta**, bukan sekadar berhasil mengirim SUBSCRIBE atau menerima satu pesan. Pada source v2.3.2, SUBACK `128` hanya dicatat lalu dilewati; library menghapus permintaan pending tanpa menambah subscription yang ditolak. `SubscriptionRepository` memakai state `MemoryRepository` yang benar-benar ditambahkan oleh SUBACK sukses dan mencocokkan filter topik persis. Factory menyediakan pemeriksaan baca-saja atas topik yang dikonfirmasi dan antrean pending (subscriber ini hanya mengirim SUBSCRIBE). Logger tetap `NullLogger`; tidak ada parsing log atau perubahan vendor.

Jika seluruh jawaban telah diterima tetapi ada topik yang tidak disetujui, subscriber gagal pada pemeriksaan loop berikutnya. Jika satu/semua SUBACK tidak datang, batas konfirmasi menghentikan loop dengan exit `1`, termasuk saat `--timeout=0`. Pemeriksaan akhir menolak hasil sukses bila konfirmasi belum lengkap. Exception hook loop juga ditelan library, sehingga kegagalan dicatat sebagai state lalu dilempar **di luar loop**. Ini membedakan langganan gagal dari langganan lengkap yang sah tetapi belum menerima data.

Batas 8192 byte ditegakkan sebelum JSON decode, **sesudah library menerima paket**. v2.3.2 tidak menyediakan setting maximum incoming packet size; ini bukan pembatas memori socket. Aktifkan batas payload/packet, rate limit, ACL, dan quota koneksi pada broker. Jangan membuka subscriber kepada broker/publisher tak tepercaya.

## Konfigurasi

Variabel berikut hanya didokumentasikan, tidak otomatis ditulis ke `.env`:

| Variabel | Default / kebutuhan |
| --- | --- |
| `MQTT_ENABLED` | `false`; aktivasi eksplisit setelah persiapan disetujui |
| `MQTT_HOST` | kosong; hostname/IP broker tanpa scheme/path/kredensial |
| `MQTT_PORT` | `8883` |
| `MQTT_CLIENT_ID` | `rebung-pintar-ingestor`; gunakan ID unik tiap proses |
| `MQTT_USERNAME`, `MQTT_PASSWORD` | tidak diisi; set lewat environment/secret manager sesuai broker |
| `MQTT_TLS` | `true` |
| `MQTT_TLS_CA_FILE` | opsional path CA tepercaya yang dapat dibaca; bila kosong gunakan trust store sistem |
| `MQTT_ALLOW_INSECURE_LOCAL` | `false`; hanya pengecualian eksperimen loopback |

Verifikasi peer dan hostname selalu wajib, self-signed bypass ditolak. CA privat dapat digunakan dengan trust root yang benar tanpa mematikan verifikasi. TLS off hanya diizinkan ketika **ketiganya** terpenuhi: IP literal `127.0.0.1`/`::1`, `APP_ENV=local` atau `testing`, dan `MQTT_ALLOW_INSECURE_LOCAL=true`. Flag tersebut tidak mengizinkan cleartext ke host remote atau lingkungan production. Timeout connect 10 detik, socket 5 detik, keepalive 30 detik; konfigurasi numerik divalidasi offline.

`config/mqtt.php` menetapkan `subscription_ack_timeout=10` detik, terpisah dari timeout menunggu data. Subscriber memvalidasi nilai integer 1–60 sebelum membuat client; nilai invalid gagal tanpa koneksi. Batas diukur sejak loop dimulai setelah connect/SUBSCRIBE, diperiksa setiap iterasi, bukan hard deadline wall-clock untuk operasi socket atau callback DB. `--timeout` yang lebih singkat tetap membatasi loop dan gagal jika SUBACK belum lengkap. `mqtt:check` tetap pemeriksaan konfigurasi offline, bukan bukti penerimaan SUBACK/izin ACL.

## Perintah dan aktivasi

Pemeriksaan ini **tidak membuka jaringan atau database**, tidak memverifikasi migrasi/ACL/hardware, dan tidak menampilkan nilai rahasia:

```bash
php artisan mqtt:check --no-interaction
```

Exit `1` saat disabled/konfigurasi tidak siap; exit `0` berarti konfigurasi lolos pemeriksaan lokal, bukan koneksi berhasil.

Sebelum aktivasi produksi:

1. Konfirmasi hardware, parameter/satuan/kalibrasi, interval, payload, jam, strategi retry/ACK dan retained=false.
2. Siapkan broker TLS, CA, autentikasi, pembatas ukuran/laju, serta ACL per perangkat: perangkat hanya boleh publish topik node sendiri; Laravel hanya subscribe dua topik telemetri. Jangan membagikan kredensial subscriber ke firmware atau Flutter.
3. **Minta persetujuan migrasi live terpisah** dan backup SQLite secara konsisten. Migrasi aditif yang dibutuhkan ialah `database/migrations/2026_09_16_040942_create_mqtt_messages_table.php`; schema `sensor_readings` dan `users` tidak diubah oleh migrasi ini. Jalankan hanya migrasi yang telah ditinjau, bukan `migrate:fresh` dan bukan seluruh pending migration tanpa review. Tidak ada migrasi live dijalankan dalam pekerjaan persiapan ini.
4. Isi environment broker/secret; set `MQTT_ENABLED=true`, perbarui cache konfigurasi pada deployment bila dipakai, lalu jalankan `mqtt:check`.
5. Setelah izin koneksi diberikan, uji satu pesan:

```bash
php artisan mqtt:subscribe --once --timeout=30 --no-interaction
```

`--once` memproses hanya pesan pertama termasuk duplikat/invalid, dengan batas default 30 detik jika `--timeout=0`/tidak diberikan. Jika pesan datang sebelum SUBACK topik lain, loop tetap menunggu konfirmasi lengkap dalam batas konfirmasi/data; pesan berikutnya tidak diproses oleh ingestor. Exit `0` hanya bila **semua langganan dikonfirmasi** dan pesan pertama accepted/duplicate. Pesan pertama dapat sudah tersimpan sebelum topik lain ditolak/tidak dikonfirmasi; exit tetap `1`, bukan rollback lintas langganan atau perbaikan batas ACK-before-commit di atas.

Timeout tanpa pesan atau pesan ditolak menghasilkan exit `1` pada `--once`. Pada loop normal, timeout tanpa pesan menghasilkan hitungan nol dan exit `0` **hanya jika semua SUBACK sukses**; ini bukan bukti perangkat online. Loop normal memakai `--timeout=0` tanpa batas **setelah konfirmasi langganan**, dengan batas opsional 1–86400 detik diukur sejak loop setelah connect, bukan batas wall-clock seluruh proses. Disconnect dijalankan pada `finally` saat normal/exception; kill paksa proses tidak menjamin cleanup.

```bash
php artisan mqtt:subscribe --no-interaction
```

Jalankan melalui process supervisor/service terpisah dengan backoff restart dan pemantauan exit/error. Auto-reconnect library dinonaktifkan: tidak ada loop retry koneksi tak terbatas. Gunakan satu subscriber untuk SQLite kecuali concurrency/locking sudah ditinjau. Jangan menganggap status kesegaran di dashboard sebagai online/offline perangkat.

## Pengujian yang aman

```bash
php artisan test --compact tests/Feature/MqttIngestionTest.php tests/Feature/MqttCommandsTest.php tests/Feature/MqttLocalBrokerTest.php tests/Feature/MqttRestFlowTest.php
```

Tes memakai SQLite `:memory:`/`RefreshDatabase`. Pengujian ingestion membuktikan persistensi dan snapshot, duplikasi, atomic rollback, mismatch, schema, nilai invalid, retained, byte limit, timezone rollover, dan batas future. Tes command menyuntikkan client di boundary jaringan untuk disabled/configuration guard, settings TLS, timeout, cleanup, callback failure, dan payload privacy.

`MqttLocalBrokerTest` menjalankan **client php-mqtt nyata**, tanpa mock factory, melawan fixture wire MQTT 3.1.1 sementara yang bind hanya ke `127.0.0.1` port ephemeral. Fixture memverifikasi CONNECT/dua topik SUBSCRIBE QoS 1, mengirim SUBACK/PUBLISH, dan memverifikasi PUBACK serta DISCONNECT; hasilnya ditulis hanya ke SQLite in-memory. Kasus meliputi sukses, satu/kedua SUBACK `128`, satu/semua SUBACK hilang, pesan pertama sebelum SUBACK kedua yang sukses/ditolak/hilang, timeout normal tanpa data setelah konfirmasi lengkap, dan timeout `--once` tanpa data. Fixture dihentikan sesudah tes; tidak ada instalasi broker/service persisten. Ini verifikasi transport lokal, **bukan** uji broker TLS produksi atau perangkat fisik.
