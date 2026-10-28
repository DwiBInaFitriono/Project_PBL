# Kontrak MQTT Hydroponik DFT → Laravel

**Status: kontrak integrasi yang perlu divalidasi dengan hardware.** Dokumen ini tidak mengaktifkan broker, subscriber, perangkat, atau migrasi database.

## Topik dan akses

Perangkat publish hanya pada topik berikut:

- Node Nutrisi: `hidroponik/v1/nodes/1/telemetry`
- Node Iklim: `hidroponik/v1/nodes/2/telemetry`

Gunakan QoS 1 dan `retain=false`. Broker harus menolak perangkat yang mencoba publish topik node lain. Laravel subscriber hanya menerima dua topik telemetry tersebut. Gunakan TLS, autentikasi unik per perangkat, ACL, batas ukuran pesan, dan rate limit. Jangan gunakan broker publik atau memasukkan kredensial ke firmware/repository.

## Payload JSON

Payload harus UTF-8 JSON maksimal 8192 byte:

```json
{
  "schema_version": 1,
  "message_id": "00000000-0000-4000-8000-000000000000",
  "node_id": "1",
  "recorded_at": "2026-10-05T12:00:00+07:00",
  "readings": {
    "ph": 0,
    "water_temperature": 0,
    "water_level": 0
  }
}
```

Nilai `0` dalam contoh hanya menunjukkan tipe angka JSON, bukan pembacaan, target nutrisi, atau kalibrasi nyata.

Field wajib adalah `schema_version`, `message_id`, `node_id`, `recorded_at`, dan `readings`. `schema_version` bernilai integer `1`; `message_id` adalah UUID; `node_id` adalah string yang harus cocok dengan node pada topik; `recorded_at` adalah RFC3339 dengan zona waktu; `readings` adalah objek tidak kosong. Field tambahan ditolak.

| Node | Key yang diizinkan |
| --- | --- |
| `1` | `ph`, `water_temperature`, `water_level` |
| `2` | `air_temperature`, `air_humidity`, `light_intensity`, `water_flow` |

Setiap nilai pembacaan harus angka JSON berhingga. Jangan mengirim string angka, boolean, `null`, NaN, Infinity, array, atau objek. Parameter tidak dikirim bila driver gagal membaca, tetapi payload tidak boleh kosong. Rentang fisik, satuan final, dan kalibrasi belum ditetapkan oleh dokumen ini.

## Waktu dan deduplikasi

Sinkronkan jam perangkat melalui NTP sebelum publish. Simpan waktu dalam UTC di backend. Pengiriman ulang sampel yang sama harus memakai `message_id` yang sama agar deduplikasi dapat bekerja. Telemetry retained ditolak. QoS 1 dapat duplikat dan tidak menjamin exactly-once saat perangkat/broker/backend gagal.

## Konfigurasi contoh

`.env.example` mendokumentasikan nama variabel tanpa rahasia. Isi host, username, password, sertifikat, dan client ID unik melalui environment/secret manager di lingkungan target. Jangan menyalakan MQTT sebelum validasi hardware dan kebijakan operasional disetujui.
