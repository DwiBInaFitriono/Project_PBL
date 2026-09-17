@props(['name'])
<svg {{ $attributes->class(['nav-icon']) }} data-nav-icon="{{ $name }}" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">
    @switch($name)
        @case('dashboard')
            <rect x="3" y="3" width="7" height="7" rx="1.5"/><rect x="14" y="3" width="7" height="7" rx="1.5"/><rect x="3" y="14" width="7" height="7" rx="1.5"/><rect x="14" y="14" width="7" height="7" rx="1.5"/>
            @break
        @case('node')
        @case('esp')
            <rect x="6" y="6" width="12" height="12" rx="2"/><rect x="9" y="9" width="6" height="6" rx="1"/><path d="M9 3v3m6-3v3M9 18v3m6-3v3M3 9h3m-3 6h3m12-6h3m-3 6h3"/>
            @break
        @case('temperature')
            <path d="M9 14V5a3 3 0 0 1 6 0v9a5 5 0 1 1-6 0Z"/><path d="M12 8v9m6-10h3m-3 4h2"/><circle cx="12" cy="18" r="1"/>
            @break
        @case('air_humidity')
            <path d="M12 3C9 7 6 10 6 14a6 6 0 0 0 12 0c0-4-3-7-6-11Z"/><path d="m9 16 6-5"/><circle cx="9.5" cy="11.5" r=".5"/><circle cx="14.5" cy="16.5" r=".5"/>
            @break
        @case('soil_moisture')
            <path d="M12 17V8m0 4C6 12 5 8 5 5c5 0 7 3 7 7Zm0-2c0-4 3-6 7-6 0 4-2 7-7 7M3 18h18M5 21h14"/>
            @break
        @case('history')
            <path d="M3 11a9 9 0 1 1 2 7M3 5v6h6M12 7v5l3 2"/>
            @break
        @case('account')
            <circle cx="12" cy="8" r="4"/><path d="M4 21v-2a6 6 0 0 1 6-6h4a6 6 0 0 1 6 6v2"/>
            @break
        @case('collapse')
            <rect x="3" y="4" width="18" height="16" rx="2"/><path d="M9 4v16m7-12-3 4 3 4"/>
            @break
        @default
            <path d="M4 19V5m0 14h16M8 15l4-5 4 3 4-7"/>
    @endswitch
</svg>
