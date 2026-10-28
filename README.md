# Hidroponik Horizontal IoT DFT

Aplikasi Laravel dan Flutter untuk memantau sistem hidroponik DFT horizontal bagi kangkung dan pakcoy.

## Arsitektur

```text
ESP32 Node Nutrisi / Node Iklim
             | MQTT telemetry
             v
      Broker privat + Laravel subscriber
             |
             v
        Laravel + SQLite
          |          |
       Web Laravel  Flutter REST
```

Flutter dan browser menggunakan REST API Laravel. Keduanya tidak terhubung langsung ke broker dan tidak menyimpan kredensial MQTT.

## Node monitoring

| Node | ID | Parameter |
| --- | --- | --- |
| Node Nutrisi | `1` | `ph`, `water_temperature`, `water_level` |
| Node Iklim | `2` | `air_temperature`, `air_humidity`, `light_intensity`, `water_flow` |

Topik telemetri:

- `hidroponik/v1/nodes/1/telemetry`
- `hidroponik/v1/nodes/2/telemetry`

Lihat [`docs/mqtt.md`](docs/mqtt.md) untuk kontrak payload dan keamanan, [`docs/integration.md`](docs/integration.md) untuk batas REST/Flutter/MQTT, dan [`hardware/circuit_schematic.md`](hardware/circuit_schematic.md) untuk kebutuhan hardware yang masih perlu dikonfirmasi.

## Menjalankan pengembangan Laravel

```bash
npm run build
php artisan serve
php artisan test --compact
```

Salin `.env.example` menjadi `.env` dan isi konfigurasi lokal sendiri. Jangan commit atau membagikan Wi-Fi, broker, token, password, atau sertifikat privat.

Status perangkat fisik, broker, sensor, dan kalibrasi tidak dinyatakan aktif oleh repository ini. Firmware di `hardware/` adalah template integrasi dengan placeholder yang harus diisi setelah hardware dipilih dan dikalibrasi.
