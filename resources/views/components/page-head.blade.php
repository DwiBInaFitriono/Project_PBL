@props(['title'])
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<meta name="theme-color" content="#F2F1EF">
<meta property="csp-nonce" nonce="{{ Vite::cspNonce() }}">
<title>{{ $title }} — Rebung Pintar</title>
<script nonce="{{ Vite::cspNonce() }}">
    (() => {
        let theme = window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light';
        try {
            const saved = localStorage.getItem('rebung-pintar-theme');
            if (saved === 'dark' || saved === 'light') theme = saved;
        } catch { /* Browser storage is optional. */ }
        document.documentElement.dataset.theme = theme;
        document.querySelector('meta[name="theme-color"]').content = theme === 'dark' ? '#0A1530' : '#F2F1EF';
    })();
</script>
@vite(['resources/css/app.css', 'resources/js/app.js'])
