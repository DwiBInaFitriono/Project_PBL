<x-dashboard-layout title="Dashboard">
    <div data-monitoring-root data-monitoring-url="{{ route('monitoring.data') }}" data-monitoring="{{ json_encode($monitoring) }}">
        <div class="dashboard-heading">
            <div>
                <div class="eyebrow"><span></span> REBUNG PINTAR / MONITORING</div>
                <h1>Ringkasan monitoring</h1>
                <p class="form-intro">Pantau rebung bambu melalui node 1 dan node 2.</p>
            </div>
            <a class="dashboard-refresh dashboard-refresh-icon" href="{{ route('dashboard') }}" aria-label="Muat ulang" title="Muat ulang">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" aria-hidden="true">
                    <path d="M20 7v5h-5M4 17v-5h5"/>
                    <path d="M6.2 6.2A8 8 0 0 1 20 12M4 12a8 8 0 0 0 13.8 5.8"/>
                </svg>
            </a>
        </div>

        <div class="integration-notice" role="note">
            <span class="notice-icon" aria-hidden="true">i</span>
            <p>
                <span data-source-notice><strong>Menunggu integrasi perangkat.</strong> Nilai dan grafik belum berisi pembacaan perangkat.</span>
                <span class="proposal-note">Parameter usulan: {{ implode(', ', array_column($sensors, 'name')) }}. Sesuaikan dengan sensor yang digunakan.</span>
            </p>
        </div>
        <div class="monitoring-live-bar">
            <p data-poll-status role="status">Menyiapkan pembaruan otomatis...</p>
            <div class="monitoring-access-actions">
                <button type="button" class="dashboard-refresh" data-poll-refresh>Perbarui data</button>
                <a class="dashboard-refresh" data-access-recovery href="{{ route('login', ['access' => 'login']) }}" data-dashboard-url="{{ route('dashboard', ['access' => 'denied']) }}" hidden>Masuk kembali</a>
            </div>
        </div>
        <p class="monitoring-error" data-monitoring-error role="alert" hidden>
            Data monitoring tidak dapat dimuat. Muat ulang halaman atau periksa sumber data.
        </p>

        <x-monitoring.overview :nodes="$nodes" :sensor-count="$sensorCount" :sensors="$sensors" />

        <section class="nodes-section" aria-labelledby="nodes-title">
            <div class="section-heading">
                <div>
                    <h2 id="nodes-title">Node pemantauan</h2>
                    <p>Dua titik pemantauan, satu tampilan.</p>
                </div>
                <span class="section-meta">{{ count($nodes) }} node</span>
            </div>
            <div class="nodes-grid">
                @foreach ($nodes as $node)
                    <x-monitoring.node-card :node="$node" />
                @endforeach
            </div>
        </section>

        <x-monitoring.chart-panel :nodes="$nodes" :sensors="$sensors" :active-sensor="$activeSensor" :active-hours="$activeHours" />
        <x-monitoring.history-table />

        <div class="dashboard-bottom">
            <span>Rebung Pintar adalah aplikasi monitoring rebung bambu melalui node 1 dan node 2.</span>
            <span data-source-footer>Data perangkat belum tersedia</span>
        </div>
    </div>
</x-dashboard-layout>
