# Aturan Proyek — Hidroponik Horizontal IoT DFT

Dokumen ini adalah sumber keputusan produk untuk aplikasi monitoring hidroponik DFT horizontal. Produk mendukung budidaya kangkung dan pakcoy. Bahasa antarmuka adalah Bahasa Indonesia.

## Arsitektur dan batas integrasi

- Laravel adalah backend web dan REST API. Flutter adalah klien seluler melalui REST API, bukan MQTT.
- ESP32 mengirim telemetri ke broker MQTT; subscriber Laravel yang tervalidasi menyimpan telemetri. Browser dan Flutter tidak terhubung langsung ke broker serta tidak menyimpan kredensial broker.
- Database lokal tetap SQLite sampai pengguna menyetujui perubahan lain. Jangan menjalankan reset database, `migrate:fresh`, atau mengubah akun/data pengguna.
- Integrasi broker, sensor fisik, interval publish, kalibrasi, rentang alarm, aktuator, dan status online belum boleh diklaim aktif tanpa verifikasi perangkat.

## Node dan parameter tetap

| Node | ID | Peran | Parameter |
| --- | --- | --- | --- |
| Node Nutrisi | `1` | Larutan nutrisi DFT | `ph`, `water_temperature`, `water_level` |
| Node Iklim | `2` | Kondisi lingkungan dan aliran | `air_temperature`, `air_humidity`, `light_intensity`, `water_flow` |

Nilai yang belum diterima adalah `null`, bukan nol. Satuan/rentang yang tampil harus mengikuti driver dan kalibrasi perangkat nyata. Jangan mencampur parameter dengan satuan berbeda pada sumbu grafik yang sama.

## MQTT

- Topik telemetri persis: `hidroponik/v1/nodes/1/telemetry` dan `hidroponik/v1/nodes/2/telemetry`.
- Perangkat hanya boleh publish pada topik node miliknya. Laravel hanya subscribe telemetri. QoS 1 dan `retain=false` direkomendasikan.
- Payload JSON, identitas node, waktu RFC3339, UUID pesan, parameter yang diizinkan, deduplikasi, serta aturan validasi didokumentasikan di `docs/mqtt.md`.
- Gunakan broker privat dengan TLS, autentikasi, ACL per perangkat, batas ukuran pesan, dan rate limit. Jangan menaruh kredensial pada firmware, repository, Flutter, atau browser.

## Identitas visual

Pertahankan navy, ivory, dan yellow sebagai identitas aplikasi. Gunakan navy untuk identitas/teks utama, ivory untuk permukaan terang, dan yellow sebagai aksen terukur. Jangan memakai nilai contoh atau status aman/optimal seolah data perangkat.

## Verifikasi

Perubahan dokumentasi atau firmware harus diperiksa terhadap nama produk, kedua node, delapan parameter, dan topik MQTT yang tepat. Firmware hanya boleh memakai pin/driver/kalibrasi yang terkonfirmasi; sisanya harus menjadi placeholder eksplisit dan tidak menghasilkan telemetry palsu.
