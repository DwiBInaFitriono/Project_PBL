<x-dashboard-layout title="Setting ESP">
    <div class="settings-page">
        <section class="settings-intro" aria-labelledby="settings-title">
            <p class="eyebrow">KONFIGURASI PERANGKAT</p>
            <h1 id="settings-title">Setting ESP</h1>
            <p>Ringkasan rancangan perangkat untuk monitoring rebung bambu melalui node 1 dan node 2.</p>
        </section>

        <section class="settings-panel" aria-labelledby="integration-title">
            <span class="settings-status">Menunggu integrasi</span>
            <h2 id="integration-title">Integrasi perangkat belum tersedia</h2>
            <p>Perangkat ESP belum terhubung dan belum dapat dikonfigurasi melalui aplikasi. Halaman ini hanya menampilkan rancangan konfigurasi, bukan status koneksi perangkat.</p>
            <dl class="settings-meta">
                <div>
                    <dt>Protokol komunikasi</dt>
                    <dd>Protokol komunikasi belum ditentukan.</dd>
                </div>
                <div>
                    <dt>Penyiapan perangkat</dt>
                    <dd>Model ESP, sensor fisik, jaringan, dan firmware perlu dikonfirmasi sebelum integrasi.</dd>
                </div>
                <div>
                    <dt>Pengambilan data</dt>
                    <dd>Kalibrasi, interval pembacaan, dan ambang alarm belum ditentukan.</dd>
                </div>
            </dl>
        </section>

        <div class="settings-grid">
            @foreach ($nodes as $node)
                <section class="settings-panel" aria-labelledby="esp-node-{{ $node['id'] }}">
                    <h2 id="esp-node-{{ $node['id'] }}">{{ $node['name'] }}</h2>
                    <span class="settings-status">{{ $node['status'] }}</span>
                    <h3>Parameter usulan</h3>
                    <p>Kanal pada konfigurasi aplikasi; belum dikonfirmasi terhadap sensor fisik.</p>
                    <dl class="settings-meta">
                        @foreach ($node['sensors'] as $sensor)
                            <div>
                                <dt>{{ $sensor['name'] }}</dt>
                                <dd>{{ $sensor['unit'] }}</dd>
                            </div>
                        @endforeach
                    </dl>
                </section>
            @endforeach
        </div>
    </div>
</x-dashboard-layout>
