# Rencana Hardware — Hidroponik Horizontal IoT DFT

Dokumen ini menjelaskan kebutuhan dua node ESP32 untuk DFT horizontal. Ini bukan skematik siap produksi: pilihan sensor, pin, tegangan, isolasi, driver, satuan, dan kalibrasi harus ditetapkan dari komponen fisik yang dipakai.

## Node

| Node | ID | Fungsi | Parameter |
| --- | --- | --- | --- |
| Node Nutrisi | `1` | Mengamati larutan nutrisi dan ketinggian air | pH, suhu air, level air |
| Node Iklim | `2` | Mengamati iklim area tanam dan aliran sirkulasi | suhu udara, kelembapan udara, intensitas cahaya, debit aliran |

## Kandidat antarmuka yang perlu dikonfirmasi

| Parameter | Antarmuka yang mungkin | Keputusan yang masih diperlukan |
| --- | --- | --- |
| pH | ADC melalui modul pH berpenguat | Sensor/modul, referensi tegangan, titik kalibrasi, isolasi listrik |
| Suhu air | OneWire atau I2C | Tipe probe, pull-up/alamat, kedap air |
| Level air | Digital, analog, atau ultrasonic | Metode ukur, posisi sensor, level aman |
| Suhu/kelembapan udara | I2C atau single-wire | Tipe sensor, posisi, perlindungan percikan |
| Cahaya | I2C atau ADC | Sensor, orientasi, satuan yang digunakan aplikasi |
| Debit aliran | Input pulsa | Sensor, GPIO interrupt, faktor pulsa-per-volume |

Jangan menganggap kandidat ini sebagai BOM final. Sensor analog yang berbagi catu dengan pompa/relay membutuhkan perencanaan ground, filtering, proteksi, dan pengujian noise. Tegangan sensor harus kompatibel dengan ESP32 3.3 V atau memakai level shifting/pembagi yang tervalidasi.

## Ketentuan instalasi DFT

- Letakkan Node Nutrisi di lokasi terlindung dari percikan tetapi akses probe dan konektor tetap memungkinkan perawatan.
- Letakkan sensor iklim pada tinggi kanopi, tidak langsung di lintasan semprotan/panas matahari lokal.
- Jalur sinyal sensor harus dipisahkan dari kabel pompa/beban. Gunakan fuse, enclosure yang sesuai lingkungan basah, strain relief, dan konektor berlabel.
- Jika aktuator ditambahkan kemudian, dokumentasikan fail-safe, rating daya, pemisahan tegangan, dan interlock sebelum menghubungkannya. Firmware saat ini tidak mengendalikan aktuator.

## MQTT

Firmware hanya publish telemetry ke `hidroponik/v1/nodes/1/telemetry` atau `hidroponik/v1/nodes/2/telemetry`. Detail payload ada di `docs/mqtt.md`.
