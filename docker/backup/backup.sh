#!/bin/sh
# ==============================================================================
# Rebung Pintar — Daemon Backup Otomatis Database (Node 3)
# Mengambil cadangan database dari Node 2 secara berkala dan menyimpan arsip terkompresi
# ==============================================================================

set -e

BACKUP_DIR="/backups"
TIMESTAMP=$(date +"%Y%m%d_%H%M%S")
BACKUP_FILE="${BACKUP_DIR}/rebung_backup_${TIMESTAMP}.sql.gz"

mkdir -p "${BACKUP_DIR}"

echo "[$(date -Iseconds)] Memulai backup database dari Node 2..."

if [ -n "$DB_HOST" ] && [ -n "$DB_DATABASE" ]; then
  # Jika menggunakan MariaDB / MySQL di Node 2
  mariadb-dump -h "${DB_HOST}" -u "${DB_USER:-root}" -p"${DB_PASSWORD:-}" "${DB_DATABASE}" | gzip -9 > "${BACKUP_FILE}"
elif [ -f "/data/database.sqlite" ]; then
  # Jika menggunakan SQLite persistent volume
  gzip -c /data/database.sqlite > "${BACKUP_FILE}"
else
  echo "[$(date -Iseconds)] Sumber database tidak ditemukan, melewati dump."
  exit 0
fi

# Hitung checksum SHA256 untuk verifikasi integritas
sha256sum "${BACKUP_FILE}" > "${BACKUP_FILE}.sha256"

echo "[$(date -Iseconds)] Backup selesai: ${BACKUP_FILE}"

# Hapus backup yang lebih lama dari 7 hari (kebijakan retensi otomatis)
find "${BACKUP_DIR}" -name "rebung_backup_*.sql.gz*" -type f -mtime +7 -exec rm -f {} +
echo "[$(date -Iseconds)] Retensi backup 7 hari dibersihkan."
