export function freshness(timestamp, now, thresholdSeconds) {
    if (!Number.isFinite(timestamp) || timestamp > now) {
        return { state: 'unavailable', label: 'Belum ada data', ageSeconds: null };
    }
    const ageSeconds = Math.max(0, Math.floor((now - timestamp) / 1000));
    const fresh = ageSeconds <= thresholdSeconds;
    return { state: fresh ? 'fresh' : 'stale', label: fresh ? 'Data terbaru' : 'Data terlambat', ageSeconds };
}

export function relativeAge(seconds) {
    if (!Number.isFinite(seconds)) return 'Belum ada pembacaan';
    if (seconds < 5) return 'Baru saja';
    if (seconds < 60) return `${seconds} detik lalu`;
    if (seconds < 3600) return `${Math.floor(seconds / 60)} menit lalu`;
    if (seconds < 86400) return `${Math.floor(seconds / 3600)} jam lalu`;
    return `${Math.floor(seconds / 86400)} hari lalu`;
}
