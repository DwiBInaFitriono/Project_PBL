@props(['node'])

<article class="node-card" data-node-id="{{ $node['id'] }}" aria-labelledby="node-{{ $node['id'] }}-title">
    <header class="node-card-heading">
        <div class="node-identity">
            <span class="node-mark" aria-hidden="true">{{ str_pad($node['id'], 2, '0', STR_PAD_LEFT) }}</span>
            <div>
                <h3 id="node-{{ $node['id'] }}-title">{{ $node['name'] }}</h3>
                <p>Pemantauan rebung bambu</p>
            </div>
        </div>
        <span class="node-status"><span aria-hidden="true"></span><span data-node-status>{{ $node['status'] }}</span></span>
    </header>

    <div class="sensor-grid">
        @foreach ($node['sensors'] as $sensor)
            <div class="sensor-reading" data-sensor-reading data-sensor-node="{{ $node['id'] }}" data-sensor-id="{{ $sensor['id'] }}">
                <h4 class="sensor-name">{{ $sensor['name'] }}</h4>
                <div class="sensor-value">
                    <strong data-current-value>{{ $sensor['value'] === null ? '—' : number_format($sensor['value'], $sensor['decimals'], ',', '.') }}</strong>
                    <span>{{ $sensor['unit'] }}</span>
                </div>
                <div class="sensor-sparkline" data-sparkline aria-hidden="true"></div>
                <span class="sensor-source" data-sensor-source>Belum ada pembacaan</span>
            </div>
        @endforeach
    </div>

    <dl class="node-metadata">
        <div><dt>Koneksi perangkat</dt><dd>Belum terkonfirmasi</dd></div>
        <div><dt>Umur data terakhir</dt><dd data-node-age>Belum ada pembacaan</dd></div>
    </dl>
</article>
