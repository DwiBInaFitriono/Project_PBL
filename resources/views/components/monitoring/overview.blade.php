@props(['nodes', 'sensorCount', 'sensors'])

@php($parametersPerNode = count($nodes) > 0 ? (int) ($sensorCount / count($nodes)) : 0)

<section class="monitoring-overview" aria-label="Ringkasan sistem">
    <div>
        <span>Node pemantauan</span>
        <strong>{{ count($nodes) }} <small>node</small></strong>
    </div>
    <div>
        <span>Parameter pemantauan</span>
        <strong>{{ $sensorCount }} <small>{{ $parametersPerNode }} per node</small></strong>
    </div>
    <div>
        <span>Pembacaan terakhir</span>
        <strong class="summary-text" data-latest-reading>Belum ada pembacaan</strong>
    </div>
</section>
