export function initSidebar() {
    const sidebar = document.querySelector('[data-sidebar]');
    if (!sidebar) return;

    const toggles = [...sidebar.querySelectorAll('[data-node-menu-toggle]')];
    const opener = document.querySelector('[data-sidebar-trigger]');
    const closer = sidebar.querySelector('[data-sidebar-close]');
    const backdrop = document.querySelector('[data-sidebar-backdrop]');
    const workspace = document.querySelector('[data-workspace]');
    const mobile = window.matchMedia('(max-width: 1024px)');
    const collapseControl = document.querySelector('[data-sidebar-collapse]');
    const storageKey = 'rebung-pintar-sidebar-collapsed';
    let drawerOpen = false;
    let collapsed = false;
    try { collapsed = localStorage.getItem(storageKey) === 'true'; } catch { /* Storage is optional. */ }

    function setCollapsed(value, persist = true) {
        collapsed = value;
        document.body.dataset.sidebarCollapsed = String(collapsed);
        collapseControl.setAttribute('aria-expanded', String(!collapsed));
        const label = collapsed ? 'Perluas sidebar' : 'Ringkas sidebar';
        collapseControl.setAttribute('aria-label', label);
        collapseControl.setAttribute('title', label);
        if (collapsed && !mobile.matches) {
            for (const button of toggles) setExpanded(button, false);
        }
        if (persist) {
            try { localStorage.setItem(storageKey, String(collapsed)); } catch { /* Storage is optional. */ }
        }
    }

    function setExpanded(button, expanded) {
        button.setAttribute('aria-expanded', String(expanded));
        document.getElementById(button.getAttribute('aria-controls')).hidden = !expanded;
    }

    function setDrawer(open, returnFocus = true) {
        drawerOpen = mobile.matches && open;
        document.body.dataset.sidebarOpen = String(drawerOpen);
        opener.setAttribute('aria-expanded', String(drawerOpen));
        sidebar.inert = mobile.matches && !drawerOpen;
        workspace.inert = drawerOpen;
        if (drawerOpen) {
            sidebar.setAttribute('role', 'dialog');
            sidebar.setAttribute('aria-modal', 'true');
            requestAnimationFrame(() => {
                if (drawerOpen) closer.focus({ preventScroll: true });
            });
        } else {
            sidebar.removeAttribute('role');
            sidebar.removeAttribute('aria-modal');
            if (returnFocus && mobile.matches) opener.focus();
        }
    }

    for (const button of toggles) {
        button.addEventListener('click', () => {
            if (collapsed && !mobile.matches) setCollapsed(false);
            const expanded = button.getAttribute('aria-expanded') !== 'true';
            for (const other of toggles) setExpanded(other, other === button && expanded);
        });
    }

    collapseControl.addEventListener('click', () => setCollapsed(!collapsed));
    opener.addEventListener('click', () => setDrawer(true));
    closer.addEventListener('click', () => setDrawer(false));
    backdrop.addEventListener('click', () => setDrawer(false));
    sidebar.addEventListener('click', (event) => {
        if (drawerOpen && event.target.closest('a')) setDrawer(false, false);
    });
    document.addEventListener('keydown', (event) => {
        if (!drawerOpen) return;
        if (event.key === 'Escape') {
            event.preventDefault();
            setDrawer(false);
        }
        if (event.key === 'Tab') {
            const focusable = [...sidebar.querySelectorAll('a[href], button:not(:disabled)')]
                .filter((element) => element.getClientRects().length > 0);
            const first = focusable[0];
            const last = focusable.at(-1);
            if (event.shiftKey && document.activeElement === first) {
                event.preventDefault();
                last.focus();
            } else if (!event.shiftKey && document.activeElement === last) {
                event.preventDefault();
                first.focus();
            }
        }
    });
    mobile.addEventListener('change', () => {
        setDrawer(false, false);
        setCollapsed(collapsed, false);
    });
    window.addEventListener('pageshow', () => setDrawer(false, false));
    setCollapsed(collapsed, false);
    setDrawer(false, false);
}
