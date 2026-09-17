# Panduan Arsitektur & Deployment Komputasi Awan — Rebung Pintar

Dokumen ini memandu implementasi rubrik **Komputasi Awan (Cloud Computing)** pada PBL:
1. **Arsitektur 3 Node Terisolasi (Docker / Multi-VM)**
2. **Pengolahan Data Agrikultur (VPD & Status)**
3. **Penggunaan Domain Publik (Cloudflare Tunnel & Ngrok)**
4. **Strategi Deployment Public Cloud (GCP / AWS / Cloudflare - Nilai Bonus)**

---

## 1. Arsitektur 3 Node (Docker / VM)

Sistem komputasi awan dibagi ke dalam 3 entitas terisolasi sesuai file `docker-compose.yml`:

```
+---------------------------------------------------------------------------------+
|                                KOMPUTASI AWAN                                   |
|                                                                                 |
|   +--------------------------+          +-----------------------------------+   |
|   |         NODE 1           |          |              NODE 2               |   |
|   |      Backend Server      |  SQL DB  |          Database Server          |   |
|   |  - Laravel 13 (PHP 8.3)  |<-------->|  - MariaDB 11.2 Dedicated         |   |
|   |  - Nginx Reverse Proxy   | Port 3306|  - Persistent Volume db_data      |   |
|   |  - REST API & Web Mon.   |          |                                   |   |
|   |  - MQTT Ingest Worker    |          +-----------------------------------+   |
|   +--------------------------+                            |                     |
|                ^                                          | Scheduled Dump      |
|                | Telemetry Stream                         v                     |
|   +-------------------------------------------------------------------------+   |
|   |                                 NODE 3                                  |   |
|   |                    MQTT Broker & Backup Server                          |   |
|   |  - Eclipse Mosquitto (Port 1883 TCP & Port 9001 WebSockets)             |   |
|   |  - Backup Daemon (Cron harian, kompresi .sql.gz, retensi 7 hari)        |   |
|   +-------------------------------------------------------------------------+   |
+---------------------------------------------------------------------------------+
```

### Menjalankan Lingkungan 3 Node di Server:
```bash
# 1. Jalankan seluruh kontainer 3 Node
docker compose up -d

# 2. Periksa status ketiga node
docker compose ps

# 3. Jalankan migrasi tabel awal di database Node 2
docker compose exec backend-node php artisan migrate --no-interaction
```

---

## 2. Pengolahan Data (Data Processing)

Node 1 Backend Server memproses data mentah telemetri dari mikrokontroler melalui class `App\Monitoring\DataProcessingService`:
- **Vapor Pressure Deficit (VPD):** Dihitung dari temperatur udara dan kelembapan relatif dengan formula Tetens.
  - Rentang < 0.4 kPa: Udara terlalu jenuh (risiko jamur).
  - Rentang 0.8 – 1.2 kPa: **Kondisi Optimal** untuk pertumbuhan rebung bambu.
  - Rentang > 1.6 kPa: Udara kering ekstrem, memicu rekomendasi penyiraman / misting.
- **Evaluasi Kelembapan Tanah:** Deteksi otomatis kondisi kekeringan tanah (< 35%) untuk merekomendasikan aktivasi pompa irigasi.
- **Endpoint Data Olahan:** `GET /api/v1/analytics` (terproteksi Bearer token Sanctum).

---

## 3. Integrasi Domain Publik (Cloudflare Tunnel & Ngrok)

Untuk menghubungkan server lokal / private VM ke domain publik HTTPS tanpa membuka port forwarding pada router / firewall:

### Opsi A: Cloudflare Tunnel (Direkomendasikan — Gratis, Stabil, HTTPS Otomatis)
1. Buat akun di [Cloudflare Zero Trust](https://one.dash.cloudflare.com/) dan siapkan domain Anda.
2. Tambahkan Cloudflare Tunnel baru dan salin **Tunnel Token**.
3. Di server cloud, jalankan tunnel menggunakan Docker:
   ```bash
   docker run -d --name cloudflared --network host cloudflare/cloudflared:latest tunnel --no-autoupdate run --token <TUNNEL_TOKEN_ANDA>
   ```
4. Hubungkan hostname publik:
   - `api.domainanda.com` -> `http://localhost:8000` (Node 1 Laravel Backend)
   - `mqtt.domainanda.com` -> `http://localhost:9001` (Node 3 Mosquitto WebSocket)

### Opsi B: Ngrok (Cepat untuk Demo & Pengujian)
```bash
# Expose HTTP Backend Port 8000
ngrok http 8000

# Atau expose TCP Port 1883 untuk MQTT
ngrok tcp 1883
```

---

## 4. Panduan Deployment Public Cloud (Nilai Bonus)

Untuk mendapatkan nilai bonus PBL dengan deploy ke penyedia Public Cloud:

### Menggunakan Google Cloud Platform (GCP Compute Engine):
1. **Buat VM Instance di GCP:**
   - Masuk ke Google Cloud Console -> **Compute Engine** -> **VM Instances**.
   - Nama instance: `rebung-cloud-server`
   - Region: `asia-southeast2` (Jakarta)
   - Tipe mesin: `e2-small` atau `e2-medium`
   - Boot disk: Ubuntu 22.04 LTS (25 GB SSD)
   - Firewall: Centang *Allow HTTP traffic* dan *Allow HTTPS traffic*.
2. **Instalasi Docker di VM GCP:**
   ```bash
   sudo apt-get update
   sudo apt-get install -y docker.io docker-compose
   sudo usermod -aG docker $USER
   ```
3. **Deploy 3 Node:**
   - Clone repository ke VM.
   - Konfigurasikan `.env` produksi.
   - Jalankan `docker compose up -d`.
4. **Pasang Cloudflare Tunnel di VM:**
   - Install `cloudflared` deb package di VM sehingga server dapat diakses melalui domain kustom Anda dengan sertifikat SSL/TLS valid.
