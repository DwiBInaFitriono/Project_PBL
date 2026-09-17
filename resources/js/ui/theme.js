const STORAGE_KEY = 'rebung-pintar-theme';

export function initTheme() {
    const button = document.querySelector('[data-theme-toggle]');
    const preference = window.matchMedia('(prefers-color-scheme: dark)');
    let explicitTheme = null;

    try {
        const saved = localStorage.getItem(STORAGE_KEY);
        if (saved === 'dark' || saved === 'light') explicitTheme = saved;
    } catch {
        // Storage can be blocked; changing the theme must still work.
    }

    function apply(theme) {
        const dark = theme === 'dark';
        const label = dark ? 'Aktifkan mode terang' : 'Aktifkan mode gelap';
        document.documentElement.dataset.theme = theme;
        document.querySelector('meta[name="theme-color"]')?.setAttribute('content', dark ? '#0A1530' : '#F2F1EF');
        button?.setAttribute('aria-label', label);
        button?.setAttribute('title', label);
        button?.setAttribute('aria-pressed', String(dark));
    }

    apply(explicitTheme || (preference.matches ? 'dark' : 'light'));
    button?.addEventListener('click', () => {
        explicitTheme = document.documentElement.dataset.theme === 'dark' ? 'light' : 'dark';
        apply(explicitTheme);
        try {
            localStorage.setItem(STORAGE_KEY, explicitTheme);
        } catch {
            // Persistence is optional when browser storage is unavailable.
        }
    });
    preference.addEventListener('change', (event) => {
        if (!explicitTheme) apply(event.matches ? 'dark' : 'light');
    });
}
