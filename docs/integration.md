# Integrasi Laravel, Flutter, dan MQTT

## Batas koneksi

```text
Flutter -- HTTPS REST + Bearer token --> Laravel /api/v1
ESP32 --- MQTT TLS ---> Broker privat ---> Laravel subscriber
                                           |
                                           v
                                      penyimpanan telemetry
                                           |
                                  Web Laravel + REST Flutter
```

Flutter memakai REST saja. Browser dan Flutter tidak berlangganan MQTT, tidak mengirim telemetry ke REST, dan tidak memegang kredensial broker. Subscriber Laravel berjalan terpisah dari proses web dan REST.

## Domain DFT

| Node | ID | Parameter telemetry |
| --- | --- | --- |
| Nutrisi | `1` | `ph`, `water_temperature`, `water_level` |
| Iklim | `2` | `air_temperature`, `air_humidity`, `light_intensity`, `water_flow` |

Topik yang diterima subscriber adalah `hidroponik/v1/nodes/1/telemetry` dan `hidroponik/v1/nodes/2/telemetry`. Nilai atau status perangkat tidak boleh dianggap sah sebelum sensor, driver, satuan, dan kalibrasi dikonfirmasi.

## REST untuk Flutter

Gunakan basis URL `/api/v1` melalui HTTPS saat deployment. Android emulator memakai `10.0.2.2` untuk server lokal, bukan `localhost`. Kirim `Accept: application/json`, gunakan token Bearer hanya melalui header Authorization, dan jangan menaruh token pada URL.

Endpoint dan model data harus mencerminkan kontrak Node Nutrisi dan Node Iklim di atas. Respons `null` berarti belum ada pembacaan, sedangkan `0` tetap nilai sensor yang valid. Flutter wajib memperlakukan error/non-JSON sebagai error, bukan data kosong atau telemetry sukses.

## Aktivasi aman

1. Tetapkan broker privat, TLS/CA, autentikasi, ACL, batas payload, dan rate limit.
2. Konfirmasi sensor, pin, driver, satuan, kalibrasi, interval, NTP, dan retry perangkat.
3. Tinjau perubahan skema/migrasi dan cadangkan database sebelum menyetujui migrasi live.
4. Aktifkan subscriber hanya setelah kontrak payload `docs/mqtt.md` dan ACL per node disetujui.
5. Verifikasi dengan telemetry nyata yang tidak mengandung rahasia. Pengujian lokal tidak membuktikan koneksi broker produksi atau hardware.
