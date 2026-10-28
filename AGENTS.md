# Hidroponik Horizontal IoT DFT — Project Rules

Baca `rule.md` sebelum mengubah proyek. Arah produk adalah monitoring hidroponik DFT horizontal untuk kangkung dan pakcoy, dengan Laravel sebagai backend, Flutter sebagai klien seluler REST, dan ESP32 mengirim telemetri ke Laravel melalui MQTT.

## Batas perubahan

- Pertahankan identitas visual navy, ivory, dan yellow kecuali pengguna mengubahnya.
- Jangan mengubah database pengguna, akun, atau `.env` aktif tanpa izin eksplisit.
- Flutter memakai HTTPS REST saja. Perangkat ESP32 memakai MQTT melalui broker. Browser dan Flutter tidak menerima kredensial broker.
- Node Nutrisi adalah ID `1`; Node Iklim adalah ID `2`. Lihat `rule.md` dan `docs/mqtt.md` untuk kontrak sensor dan telemetri.
- Jangan menyatakan perangkat, broker, kalibrasi, alarm, atau data sensor telah aktif tanpa bukti. Nilai sensor yang belum dibaca adalah `null`, bukan nol.
- Perubahan PHP, Blade, CSS/JS, Flutter, migrasi, dan tests berada di luar pekerjaan dokumentasi/hardware kecuali pengguna memberi izin baru.

## Verifikasi dokumentasi dan firmware

- Pastikan semua topik telemetri memakai `hidroponik/v1/nodes/{id}/telemetry`.
- Jangan menaruh Wi-Fi, broker, token, password, atau IP produksi di source/dokumentasi.
- Untuk firmware, gunakan placeholder yang jelas untuk pin, driver, dan kalibrasi yang belum diputuskan. Jangan mengganti pembacaan gagal dengan angka buatan.
