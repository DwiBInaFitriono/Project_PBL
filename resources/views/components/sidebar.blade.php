@php
    $currentNode = request()->route('node');
    $currentSensor = request()->query('sensor');
@endphp

<aside id="app-sidebar" class="app-sidebar" data-sidebar aria-label="Navigasi utama">
    <div class="sidebar-brand">
        <x-brand />
        <button type="button" class="sidebar-close" data-sidebar-close aria-label="Tutup navigasi">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path d="m6 6 12 12M6 18 18 6" stroke-linecap="round"/></svg>
        </button>
    </div>
    <p class="sidebar-caption">MONITORING REBUNG BAMBU</p>
    <nav class="sidebar-navigation" data-sidebar-scroll aria-label="Menu utama">
        <a class="sidebar-link" href="{{ route('dashboard') }}" aria-label="Dashboard" title="Dashboard" @if (request()->routeIs('dashboard')) aria-current="page" @endif>
            <x-nav-icon name="dashboard" />
            <span class="sidebar-menu-label">Dashboard</span>
        </a>
        <span class="sidebar-group-label">PERANGKAT</span>
        @foreach (config('monitoring.nodes') as $menuNode)
            @php $expanded = $currentNode === $menuNode['id']; @endphp
            <div class="sidebar-node-group">
                <button
                    type="button"
                    class="sidebar-node-toggle"
                    data-node-menu-toggle
                    aria-label="Menu {{ $menuNode['name'] }}"
                    title="{{ $menuNode['name'] }}"
                    aria-controls="node-{{ $menuNode['id'] }}-menu"
                    aria-expanded="{{ $expanded ? 'true' : 'false' }}"
                >
                    <span class="sidebar-node-icon" aria-hidden="true"><x-nav-icon name="node" /><span class="sidebar-node-badge">{{ $menuNode['id'] }}</span></span>
                    <span class="sidebar-menu-label">{{ $menuNode['name'] }}</span>
                    <svg class="sidebar-chevron" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path d="m6 9 6 6 6-6" stroke-linecap="round" stroke-linejoin="round"/></svg>
                </button>
                <div id="node-{{ $menuNode['id'] }}-menu" class="sidebar-submenu" @if (!$expanded) hidden @endif>
                    <a href="{{ route('nodes.show', $menuNode['id']) }}" @if ($expanded && !$currentSensor) aria-current="page" @endif><x-nav-icon name="summary" /><span>Ringkasan {{ $menuNode['name'] }}</span></a>
                    @foreach (config('monitoring.sensors') as $sensor)
                        <a href="{{ route('nodes.show', ['node' => $menuNode['id'], 'sensor' => $sensor['id']]) }}#tren" @if ($expanded && $currentSensor === $sensor['id']) aria-current="page" @endif><x-nav-icon :name="$sensor['id']" /><span>{{ $sensor['name'] }}</span></a>
                    @endforeach
                    <a href="{{ route('nodes.show', $menuNode['id']) }}#riwayat"><x-nav-icon name="history" /><span>Riwayat pembacaan</span></a>
                </div>
            </div>
        @endforeach
        <a class="sidebar-link" href="{{ route('history.index') }}" aria-label="Riwayat data" title="Riwayat data" @if (request()->routeIs('history.*')) aria-current="page" @endif>
            <x-nav-icon name="history" /><span class="sidebar-menu-label">Riwayat data</span>
        </a>
        @can('manage-esp')
        <span class="sidebar-group-label">PENGATURAN</span>
        <a class="sidebar-link" href="{{ route('settings.esp') }}" aria-label="Setting ESP" title="Setting ESP" @if (request()->routeIs('settings.esp')) aria-current="page" @endif>
            <x-nav-icon name="esp" /><span class="sidebar-menu-label">Setting ESP</span>
        </a>
        @endcan
    </nav>
    <div class="sidebar-account" data-sidebar-account>
        <div class="sidebar-footer"><span class="sidebar-footer-dot" aria-hidden="true"></span><span>2 node · 3 sensor per node</span></div>
        <a class="sidebar-link" href="{{ route('settings.account') }}" aria-label="Setting Akun" title="Setting Akun" @if (request()->routeIs('settings.account')) aria-current="page" @endif>
            <x-nav-icon name="account" /><span class="sidebar-menu-label">Setting Akun</span>
        </a>
    </div>
</aside>
