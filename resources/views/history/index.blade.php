<x-dashboard-layout title="Riwayat pembacaan">
    @php($timezoneLabel = $filters['timezone'] === 'Asia/Jakarta' ? 'WIB' : 'UTC')
    <section class="history-page" aria-labelledby="history-title">
        <div class="section-heading">
            <div>
                <p class="eyebrow">Monitoring rebung bambu</p>
                <h1 id="history-title">Riwayat pembacaan</h1>
                <p>Tanggal filter dan waktu pembacaan mengikuti zona waktu {{ $timezoneLabel }}. Rentang filter maksimal 366 hari. Data tersimpan tetap dalam UTC.</p>
            </div>
            <a class="history-export" href="{{ route('history.export', $filters) }}" target="_blank" rel="noopener noreferrer" title="Buka laporan untuk dicetak atau disimpan sebagai PDF (tab baru)">Ekspor PDF</a>
        </div>

        <form class="history-filters" method="GET" action="{{ route('history.index') }}">
            <div class="form-field">
                <label for="history-node">Node</label>
                <select id="history-node" name="node" data-custom-select>
                    <option value="">Semua node</option>
                    @foreach ($nodes as $node)
                        <option value="{{ $node['id'] }}" @selected($filters['node'] === $node['id'])>{{ $node['name'] }}</option>
                    @endforeach
                </select>
                @error('node') <p class="field-error" role="alert">{{ $message }}</p> @enderror
            </div>
            <div class="form-field">
                <label for="history-sensor">Parameter</label>
                <select id="history-sensor" name="sensor" data-custom-select>
                    <option value="">Semua parameter</option>
                    @foreach ($sensors as $sensor)
                        <option value="{{ $sensor['id'] }}" @selected($filters['sensor'] === $sensor['id'])>{{ $sensor['name'] }}</option>
                    @endforeach
                </select>
                @error('sensor') <p class="field-error" role="alert">{{ $message }}</p> @enderror
            </div>
            <div class="form-field">
                <label for="history-from">Dari tanggal</label>
                <input id="history-from" type="date" name="from" value="{{ $filters['from'] }}">
                @error('from') <p class="field-error" role="alert">{{ $message }}</p> @enderror
            </div>
            <div class="form-field">
                <label for="history-to">Sampai tanggal</label>
                <input id="history-to" type="date" name="to" value="{{ $filters['to'] }}">
                @error('to') <p class="field-error" role="alert">{{ $message }}</p> @enderror
            </div>
            @error('timezone') <p class="field-error" role="alert">{{ $message }}</p> @enderror
            <div class="history-filter-actions">
                <button type="submit" class="primary-button">Terapkan filter</button>
                <a href="{{ route('history.index', ['timezone' => $filters['timezone']]) }}">Reset filter</a>
            </div>
        </form>

        <p class="history-result-count" role="status">{{ $readings->total() }} pembacaan ditemukan.</p>
        <p class="history-scroll-hint">Geser tabel ke samping untuk melihat semua kolom.</p>
        <div class="history-table-wrap" tabindex="0" role="region" aria-label="Riwayat pembacaan tersimpan">
            <table class="history-table">
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
        </div>
        @if ($readings->hasPages())
            <nav class="history-pagination" aria-label="Navigasi halaman riwayat">
                @if ($readings->onFirstPage())
                    <span aria-disabled="true">Sebelumnya</span>
                @else
                    <a rel="prev" href="{{ $readings->previousPageUrl() }}">Sebelumnya</a>
                @endif
                <span>Halaman {{ $readings->currentPage() }} dari {{ $readings->lastPage() }}</span>
                @if ($readings->hasMorePages())
                    <a rel="next" href="{{ $readings->nextPageUrl() }}">Berikutnya</a>
                @else
                    <span aria-disabled="true">Berikutnya</span>
                @endif
            </nav>
        @endif
    </section>
</x-dashboard-layout>
