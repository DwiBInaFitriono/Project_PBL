# Dokumentasi Desain Hardware, PCB & Casing — Rebung Pintar

Dokumentasi ini merinci skematik rangkaian, pinout mikrokontroler, daftar komponen (Bill of Materials), panduan pembuatan PCB cetak, serta desain casing 3D untuk **Node 1** dan **Node 2**.

---

## 1. Spesifikasi Sistem Tertanam

Sistem terdiri dari dua perangkat mandiri (Node 1 dan Node 2) yang masing-masing dilengkapi:
- **3 Sensor:**
  1. Suhu Udara (`°C`) – Sensor DHT22 / SHT31
  2. Kelembapan Udara (`% RH`) – Sensor DHT22 / SHT31
  3. Kelembapan Tanah (`%`) – Capacitive Soil Moisture Sensor v1.2 (analog)
- **2 Aktuator:**
  1. **Buzzer Aktif 5V & LED Indikator:** Indikator alarm audio & visual status peringatan.
  2. **Relay 5V Modular (Active LOW):** Sakelar otomasi daya tinggi dengan terminal block (NO/COM/NC). Fleksibel dapat dihubungkan ke Pompa Air Irigasi 5V/12V, Solenoid Valve, Mist Maker, maupun Kipas Ventilasi.

---

## 2. Tabel Pinout ESP32 DevKit V1 (Node 1 & Node 2)

| Komponen | Tipe Sinyal | Pin Komponen | Pin ESP32 (GPIO) | Keterangan |
| :--- | :--- | :--- | :--- | :--- |
| **DHT22** | Digital In/Out | DATA (Pin 2) | **GPIO 4** | Memerlukan pull-up resistor 10kΩ ke 3.3V |
| **DHT22** | Power | VCC / GND | **3V3 / GND** | Tegangan logika 3.3V stabil |
| **Capacitive Soil** | Analog Out | AOUT | **GPIO 34 (ADC1_CH6)** | Input-only ADC1 (aman digunakan saat WiFi aktif) |
| **Capacitive Soil** | Power | VCC / GND | **3V3 / GND** | 3.3V untuk konsistensi pembacaan ADC |
| **Buzzer Aktif** | Digital Out | I/O (+) | **GPIO 18** | Driver transistor NPN 2N2222 / resistor 1kΩ |
| **LED Indikator** | Digital Out | Anoda (+) | **GPIO 19** | Diberi resistor pembatas arus 220Ω |
| **Relay Modular** | Digital Out | IN | **GPIO 23** | Active-LOW, driver optocoupler terisolasi |
| **Power Input** | Power In | VIN / GND | **5V (VIN) / GND** | Sumber daya 5V 2A dari adaptor DC |

---

## 3. Skematik Wiring Rangkaian

```
                   +------------------------+
                   |     ESP32 DevKit V1    |
                   |                        |
(3.3V) ------------| 3V3                    |
(GND) -------------| GND                    |
(5V Adaptor) ------| VIN                    |
                   |                        |
[DHT22 DATA] ------| GPIO 4   (Pull-up 10k) |
[Soil AOUT] -------| GPIO 34  (ADC1 In)     |
                   |                        |
[Buzzer Signal] <--| GPIO 18  (Base 2N2222) |
[LED Anoda] <------| GPIO 19  (Resistor 220)|
[Relay IN] <-------| GPIO 23  (Optocoupler) |
                   +------------------------+

Rangkaian Aktuator 2 (Relay Modular):
  [Relay VCC] -> 5V (VIN)
  [Relay GND] -> GND
  [Relay IN]  -> GPIO 23
  [Relay Terminal Block (COM - NO)] -> Sakelar beban eksternal (Pompa Air / Kipas)
```

---

## 4. Panduan Desain & Pembuatan PCB Cetak

### Langkah Desain (KiCad / EasyEDA)
1. **Skematik:**
   - Gunakan footprint socket header perempuan 2x15 pin untuk memasang modul ESP32 DevKit V1 (memudahkan pelepasan/penggantian modul tanpa solder ulang).
   - Terminal block screw (KF301-2P atau KF301-3P) dengan pitch 5.08mm untuk koneksi daya eksternal 5V, kabel sensor tanah, dan terminal relay.
   - Header pin 3-pin (VCC, GND, DATA) untuk sensor DHT22.
2. **Layout PCB (2-Layer FR4):**
   - **Ketebalan Tembaga:** 1 oz (35 µm).
   - **Lebar Jalur (Trace Width):**
     - Jalur Sinyal Data (GPIO 4, 18, 19, 23, 34): 0.3 mm – 0.4 mm (12–15 mil).
     - Jalur Daya (VCC 3.3V & 5V): Minimal 0.8 mm – 1.0 mm (30–40 mil).
     - Jalur Beban Relay AC/DC Tinggi: Minimal 1.5 mm – 2.0 mm (60–80 mil) dengan isolation clearance minimal 2.5 mm.
   - **Ground Plane:** Buat Ground Plane (Copper Pour) di lapisan bawah (Bottom Layer) dan lapisan atas (Top Layer) untuk meredam noise pada sinyal analog ADC sensor tanah.
3. **Ekspor Gerber Files:**
   - Ekspor gerber RS-274X beserta Excellon drill file (`.drl`) untuk dikirim ke vendor cetak PCB lokal atau manufaktur (JLCPCB/PCBWay).

---

## 5. Panduan Desain Casing 3D (Enclosure)

1. **Dimensi & Material:**
   - Material: **PETG** atau **ABS** (tahan terhadap kelembapan dan panas lingkungan rumah kaca/outdoor, dibanding PLA biasa).
   - Dimensi Rekomendasi: `110 mm x 85 mm x 45 mm`.
2. **Fitur Casing:**
   - **Ventilasi Sensor Udara:** Kisi-kisi (slits/louvers) miring pada dinding samping casing untuk sirkulasi udara sensor DHT22 agar terlindung dari cipratan air langsung.
   - **Kabel Gland / Grommet Karet:** Lubang kabel gland PG7/PG9 di bagian bawah untuk jalur kabel probe tanah dan kabel pompa air.
   - **Mounting Stand-off:** 4 buah pilar ulir kuningan M3 di dasar casing untuk mengencangkan board PCB dengan kokoh.
   - **Lubang LED & Buzzer:** Lubang 3mm di panel penutup atas untuk kepala LED dan lubang akustik buzzer.
   - **Penutup Kedap Air Ringan (IP54):** Bibir penutup dengan alur gasket karet busa (O-ring) dan 4 sekrup pengunci di sudut casing.
