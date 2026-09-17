<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Riwayat pembacaan {{ $filters['from'] }} — {{ $filters['to'] }} | Rebung Pintar</title>
    <style nonce="{{ Vite::cspNonce() }}">
        :root { color-scheme: light; font-family: "Trebuchet MS", "Segoe UI", sans-serif; color: #17233d; background: #f2f1ef; }
        * { box-sizing: border-box; }
        body { margin: 0; padding: 24px; }
        .report-toolbar, .report { max-width: 1100px; margin: 0 auto; }
        .report-toolbar { padding-bottom: 20px; }
        .report-actions { display: flex; flex-wrap: wrap; gap: 16px; align-items: center; }
        .report-actions button, .report-actions a { display: inline-flex; min-height: 44px; align-items: center; padding: 10px 16px; border: 1px solid #011e60; border-radius: 8px; font: inherit; }
        .report-actions button { color: #fff; background: #011e60; cursor: pointer; }
        .report-actions a { color: #011e60; background: #fff; }
        :focus-visible { outline: 3px solid #011e60; outline-offset: 3px; }
        .report-toolbar p { margin-bottom: 0; line-height: 1.5; }
        .report { padding: 28px; background: #fff; border: 1px solid #c6cbd5; }
        .brand { margin: 0 0 8px; font-weight: bold; color: #011e60; }
        h1 { font-size: 26px; margin: 0 0 20px; }
        .report-filters { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 12px 24px; margin: 0 0 20px; }
        .report-filters div { min-width: 0; overflow-wrap: anywhere; }
        dt { font-size: 12px; color: #43516b; }
        dd { margin: 4px 0 0; }
        .report-count { font-weight: bold; }
        table { width: 100%; border-collapse: collapse; table-layout: fixed; font-size: 13px; }
        th, td { padding: 9px 10px; border: 1px solid #b9c1cf; vertical-align: top; overflow-wrap: anywhere; text-align: left; }
        th { background: #edf0f7; color: #011e60; }
        th:first-child { width: 24%; }
        th:nth-child(2) { width: 16%; }
        th:nth-child(3) { width: 30%; }
        th:nth-child(4), th:nth-child(5) { width: 15%; }
        td:nth-child(4) { font-variant-numeric: tabular-nums; }
        thead { display: table-header-group; }
        tr { break-inside: avoid; page-break-inside: avoid; }
        .report-note { font-size: 12px; color: #43516b; line-height: 1.5; }
        @media (max-width: 600px) {
            body { padding: 12px; }
            .report { padding: 16px; }
            h1 { font-size: 22px; }
            .report-filters { grid-template-columns: 1fr; }
            th, td { padding: 6px 4px; font-size: 11px; }
        }
        @page { size: A4 landscape; margin: 12mm; }
        @media print {
            :root, body { background: #fff; color: #000; }
            body { padding: 0; }
            .report-toolbar { display: none !important; }
            .report { max-width: none; margin: 0; padding: 0; border: 0; }
            h1 { font-size: 20pt; }
            .report-filters { grid-template-columns: repeat(2, minmax(0, 1fr)); }
            th, td { font-size: 9pt; padding: 6px 8px; }
            th { background: #edf0f7; print-color-adjust: exact; -webkit-print-color-adjust: exact; }
        }
    </style>
</head>
<body>
    @php($timezoneLabel = $filters['timezone'] === 'Asia/Jakarta' ? 'WIB' : 'UTC')
    <aside class="report-toolbar" aria-label="Tindakan laporan">
        <div class="report-actions">
            @if (! $tooManyRows)
                <button id="print-report" type="button">Cetak / Simpan PDF</button>
            @endif
            <a href="{{ route('history.index', $filters) }}">Kembali ke riwayat</a>
        </div>
        @if (! $tooManyRows)
            <p>Pilih tujuan <strong>Simpan sebagai PDF</strong> pada dialog cetak browser. Gunakan kertas A4 lanskap dan nonaktifkan header/footer browser agar URL tidak ikut tercetak. Pintasan: Ctrl+P (Windows/Linux) atau ⌘+P (Mac), termasuk jika JavaScript tidak aktif.</p>
        @endif
    </aside>
    <main class="report" aria-labelledby="report-title">
        <p class="brand">Rebung Pintar</p>
        <h1 id="report-title">Riwayat pembacaan</h1>
        <dl class="report-filters">
            <div><dt>Node</dt><dd>{{ $filters['node'] !== null ? $nodes[$filters['node']]['name'] : 'Semua node' }}</dd></div>
            <div><dt>Parameter</dt><dd>{{ $filters['sensor'] !== null ? $sensors[$filters['sensor']]['name'] : 'Semua parameter' }}</dd></div>
            <div><dt>Periode (inklusif)</dt><dd>{{ $filters['from'] }} sampai {{ $filters['to'] }}</dd></div>
            <div><dt>Zona waktu</dt><dd>{{ $timezoneLabel }}{{ $timezoneLabel === 'WIB' ? ' (UTC+7)' : '' }}</dd></div>
        </dl>
        @if ($tooManyRows)
            <p role="alert">Laporan melebihi batas {{ $maxRows }} pembacaan. Persempit filter node, parameter, atau tanggal lalu coba lagi.</p>
        @else
        <p class="report-count">Total: {{ $readings->count() }} pembacaan</p>
        <table aria-label="Riwayat pembacaan tersimpan">
            <thead>
                <tr><th scope="col">Waktu ({{ $timezoneLabel }})</th><th scope="col">Node</th><th scope="col">Parameter</th><th scope="col">Nilai</th><th scope="col">Satuan</th></tr>
            </thead>
            <tbody>
                @forelse ($readings as $reading)
                    <tr>
                        <td><time datetime="{{ $reading->recorded_at->toIso8601String() }}">{{ $reading->recorded_at->setTimezone($filters['timezone'])->format('Y-m-d H:i:s') }}</time></td>
                        <td>{{ $nodes[$reading->node_id]['name'] }}</td>
                        <td>{{ $sensors[$reading->sensor_id]['name'] }}</td>
                        <td>{{ $reading->value }}</td>
                        <td>{{ $sensors[$reading->sensor_id]['unit'] }}</td>
                    </tr>
                @empty
                    <tr><td colspan="5">Belum ada pembacaan untuk filter ini.</td></tr>
                @endforelse
            </tbody>
        </table>
        <p class="report-note">Seluruh pembacaan yang cocok dengan filter ditampilkan, dari yang terbaru. Waktu penyimpanan tetap UTC. Batas laporan {{ $maxRows }} pembacaan.</p>
        @endif
    </main>
    <script nonce="{{ Vite::cspNonce() }}">
        document.addEventListener('DOMContentLoaded', () => {
            document.getElementById('print-report')?.addEventListener('click', () => window.print());
        });
    </script>
</body>
</html>
