@props(['nodes', 'sensorCount', 'sensors'])

<section class="monitoring-overview" aria-label="Ringkasan sistem">
    <div>
        <span>Node pemantauan</span>
        <strong>{{ count($nodes) }} <small>node</small></strong>
    </div>
    <div>
        <span>Kanal sensor usulan</span>
        <strong>{{ $sensorCount }} <small>{{ count($sensors) }} per node</small></strong>
    </div>
    <div>
        <span>Pembacaan terakhir</span>
        <strong class="summary-text" data-latest-reading>Belum ada pembacaan</strong>
    </div>
</section>
