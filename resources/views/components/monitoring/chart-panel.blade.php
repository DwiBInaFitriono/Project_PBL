@props(['nodes', 'sensors', 'activeSensor' => null, 'activeHours' => 24])

@php
    $selectedSensor = collect($sensors)->firstWhere('id', $activeSensor) ?? $sensors[0];
    $singleNode = count($nodes) === 1;
@endphp

<section id="tren" class="trend-section" aria-labelledby="trend-title">
    <div class="section-heading">
        <div>
            <h2 id="trend-title">{{ $singleNode ? 'Tren sensor '.$nodes[0]['name'] : 'Tren & perbandingan node' }}</h2>
            <p>{{ $singleNode ? 'Pilih parameter untuk melihat perubahan pembacaan node ini.' : 'Satu parameter, dua node. Lihat perbedaannya dalam satu grafik.' }}</p>
        </div>
        <span class="source-badge">Data perangkat</span>
    </div>
    <div class="trend-card">
        <div class="chart-filters">
            <div class="sensor-tabs" role="group" aria-label="Pilih parameter grafik">
                @foreach ($sensors as $sensor)
                    <button type="button" data-select-sensor="{{ $sensor['id'] }}" aria-pressed="{{ $selectedSensor['id'] === $sensor['id'] ? 'true' : 'false' }}">
                        {{ $sensor['name'] }}
                    </button>
                @endforeach
            </div>
            <div class="range-tabs" role="group" aria-label="Rentang waktu grafik">
                @foreach ([1, 6, 24] as $hours)
                    <button type="button" data-select-range="{{ $hours }}" aria-pressed="{{ $hours === $activeHours ? 'true' : 'false' }}">{{ $hours }} jam</button>
                @endforeach
            </div>
        </div>

        <div class="chart-toolbar">
            <div class="chart-parameter">
                <span class="control-label">Parameter</span>
                <strong data-active-sensor>{{ $selectedSensor['name'] }} · {{ $selectedSensor['unit'] }}</strong>
            </div>
            <div class="chart-legend">
                @foreach ($nodes as $node)
                    <span><i class="{{ $node['id'] === '1' ? 'node-one-key' : 'node-two-key' }}"></i>{{ $node['name'] }}</span>
                @endforeach
            </div>
        </div>

        <div class="chart-stage">
            <svg class="comparison-chart" data-chart viewBox="0 0 920 260" role="img" aria-label="Grafik perbandingan node; belum ada pembacaan">
                <g class="chart-grid" aria-hidden="true"><path d="M54 28H890M54 74H890M54 120H890M54 166H890M54 212H890"/></g>
            </svg>
            <div class="chart-empty" data-chart-empty>
                <strong>Grafik menunggu data sensor</strong>
                <p>Grafik akan tampil setelah pembacaan perangkat tersedia.</p>
            </div>
        </div>
        <p class="chart-caption" data-chart-caption>Belum ada pembacaan perangkat. Data kosong tidak dianggap nol.</p>

        <div class="chart-summaries">
            @foreach ($nodes as $node)
                <section class="node-summary" data-node-summary="{{ $node['id'] }}" aria-label="Ringkasan {{ $node['name'] }}">
                    <h3>{{ $node['name'] }} <small data-summary-source>Data perangkat</small></h3>
                    <dl>
                        @foreach (['min' => 'Minimum', 'avg' => 'Rata-rata', 'max' => 'Maksimum'] as $key => $label)
                            <div><dt>{{ $label }}</dt><dd data-summary-value="{{ $key }}">—</dd></div>
                        @endforeach
                    </dl>
                </section>
            @endforeach
        </div>
    </div>
</section>
