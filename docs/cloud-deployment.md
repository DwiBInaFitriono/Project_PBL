# Deployment Hidroponik Horizontal IoT DFT

Dokumen ini menjelaskan target deployment untuk monitoring hidroponik DFT horizontal bagi kangkung dan pakcoy. Ini adalah panduan perencanaan, bukan bukti bahwa layanan cloud, broker, atau perangkat fisik sudah aktif.

## Komponen deployment

```text
ESP32 Node Nutrisi (1) --- MQTT TLS ---+
                                        |
ESP32 Node Iklim (2) ----- MQTT TLS ---+--> Broker privat --> Laravel subscriber
                                                              |
                                                         Laravel + database
                                                              |
                                                    Web Laravel / Flutter REST
```

| Komponen | Tanggung jawab |
| --- | --- |
| Laravel | Web, REST API, validasi, dan penyimpanan telemetry |
| Broker MQTT privat | Menerima telemetry perangkat dengan TLS, autentikasi, ACL, batas ukuran, dan rate limit |
| Node Nutrisi `1` | `ph`, `water_temperature`, `water_level` |
| Node Iklim `2` | `air_temperature`, `air_humidity`, `light_intensity`, `water_flow` |

Topik telemetry adalah `hidroponik/v1/nodes/1/telemetry` dan `hidroponik/v1/nodes/2/telemetry`. Flutter dan browser hanya berkomunikasi dengan Laravel melalui HTTPS REST, bukan dengan broker.

## Pilihan lingkungan

Pengembangan lokal dapat memakai SQLite dan proses Laravel lokal. Untuk deployment, pilih database, broker, network, backup, dan observability sesuai kebutuhan operasional yang benar-benar disetujui. Tidak ada topologi Docker, VM, jadwal backup, domain publik, atau penyedia cloud yang diwajibkan oleh repository ini.

Jika menggunakan layanan publik atau tunnel, ekspos hanya HTTPS Laravel yang diperlukan. Jangan mengekspos broker MQTT ke internet tanpa TLS, autentikasi, ACL per perangkat, firewall, dan pemantauan. Jangan menaruh token tunnel, kredensial broker, atau private key di repository.

## Checklist sebelum aktivasi

1. Konfirmasi driver sensor, satuan, kalibrasi, interval publish, NTP, dan strategi retry setiap node.
2. Tetapkan identitas unik perangkat serta ACL: Node 1 hanya publish topik Node 1, Node 2 hanya publish topik Node 2, Laravel hanya subscribe telemetry.
3. Konfigurasi CA TLS, autentikasi, batas ukuran/rate, log yang tidak merekam rahasia, backup, dan pemulihan.
4. Tinjau migrasi dan cadangkan database sebelum perubahan live.
5. Verifikasi telemetry nyata dari kedua node tanpa membagikan kredensial.

Kontrak payload dan validasi ada di [`mqtt.md`](mqtt.md); batas Laravel, Flutter, dan MQTT ada di [`integration.md`](integration.md).
