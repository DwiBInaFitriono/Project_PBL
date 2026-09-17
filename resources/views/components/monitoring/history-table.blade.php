<section id="riwayat" class="history-section" aria-labelledby="history-title">
    <div class="section-heading">
        <div>
            <h2 id="history-title">Riwayat pembacaan</h2>
            <p>Pembacaan sensor yang diterima dari perangkat.</p>
        </div>
        <div class="history-actions">
            <span class="section-meta" data-history-source>Belum ada data</span>
            <a class="history-export" data-history-link href="{{ route('history.index', array_filter(['node' => request()->route('node'), 'sensor' => request()->query('sensor')])) }}">Riwayat lengkap &amp; ekspor</a>
        </div>
    </div>
    <p class="history-scroll-hint">Geser tabel ke samping untuk melihat semua kolom.</p>
    <div class="history-table-wrap" tabindex="0" role="region" aria-label="Riwayat pembacaan sensor node">
        <table class="history-table">
            <caption class="sr-only">Riwayat pembacaan sensor node</caption>
            <thead>
                <tr>
                    @foreach (['Waktu', 'Node', 'Parameter', 'Nilai'] as $column)
                        <th scope="col">{{ $column }}</th>
                    @endforeach
                </tr>
            </thead>
            <tbody data-history-body>
                <tr>
                    <td colspan="4">
                        <strong>Belum ada riwayat pembacaan</strong>
                        <p>Data akan muncul di sini setelah integrasi perangkat tersedia.</p>
                    </td>
                </tr>
            </tbody>
        </table>
    </div>
</section>
