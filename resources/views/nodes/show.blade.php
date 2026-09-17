<x-dashboard-layout :title="$node['name']">
    <div data-monitoring-root data-monitoring-url="{{ route('monitoring.data', ['node' => $node['id']]) }}" data-node="{{ $node['id'] }}" data-monitoring="{{ json_encode($monitoring) }}" class="node-detail-page">
        <div class="dashboard-heading">
            <div>
                <div class="eyebrow"><span></span> REBUNG PINTAR / {{ strtoupper($node['name']) }}</div>
                <h1>Monitoring {{ $node['name'] }}</h1>
                <p class="form-intro">Tiga parameter sensor untuk memantau rebung bambu di {{ $node['name'] }}.</p>
            </div>
            <a class="dashboard-refresh dashboard-refresh-icon" href="{{ route('nodes.show', $node['id']) }}" aria-label="Muat ulang" title="Muat ulang">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" aria-hidden="true"><path d="M20 7v5h-5M4 17v-5h5"/><path d="M6.2 6.2A8 8 0 0 1 20 12M4 12a8 8 0 0 0 13.8 5.8"/></svg>
            </a>
        </div>

        <div class="integration-notice" role="note">
            <span class="notice-icon" aria-hidden="true">i</span>
            <p data-source-notice><strong>Menunggu integrasi perangkat.</strong> Data {{ $node['name'] }} belum tersedia; nilai kosong tidak dianggap nol.</p>
        </div>
        <div class="monitoring-live-bar">
            <p data-poll-status role="status">Menyiapkan pembaruan otomatis...</p>
            <div class="monitoring-access-actions">
                <button type="button" class="dashboard-refresh" data-poll-refresh>Perbarui data</button>
                <a class="dashboard-refresh" data-access-recovery href="{{ route('login', ['access' => 'login']) }}" data-dashboard-url="{{ route('dashboard', ['access' => 'denied']) }}" hidden>Masuk kembali</a>
            </div>
        </div>
        <p class="monitoring-error" data-monitoring-error role="alert" hidden>Data monitoring tidak dapat dimuat. Muat ulang halaman atau periksa sumber data.</p>

        <section class="node-detail-sensors" aria-label="Sensor {{ $node['name'] }}">
            <x-monitoring.node-card :node="$node" />
        </section>
        <x-monitoring.chart-panel :nodes="$nodes" :sensors="$sensors" :active-sensor="$activeSensor" :active-hours="$activeHours" />
        <x-monitoring.history-table />
    </div>
</x-dashboard-layout>
