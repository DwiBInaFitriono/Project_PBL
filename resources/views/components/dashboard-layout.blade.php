@props(['title' => 'Dashboard'])

<!DOCTYPE html>
<html lang="id" data-theme="light">
<head>
    <x-page-head :title="$title" />
</head>
<body data-page="dashboard">
    <a class="skip-link" href="#main-content">Lewati ke konten</a>
    <x-sidebar />
    <div class="sidebar-backdrop" data-sidebar-backdrop aria-hidden="true"></div>
    <div class="workspace-content" data-workspace>
    <header class="dashboard-header">
        <div class="dashboard-header-inner">
            <div class="workspace-heading">
                <button type="button" class="sidebar-collapse" data-sidebar-collapse aria-label="Ringkas sidebar" title="Ringkas sidebar" aria-controls="app-sidebar" aria-expanded="true">
                    <x-nav-icon name="collapse" />
                </button>
                <button type="button" class="sidebar-open" data-sidebar-trigger aria-label="Buka navigasi" aria-controls="app-sidebar" aria-expanded="false">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path d="M4 6h16M4 12h16M4 18h16" stroke-linecap="round"/></svg>
                </button>
                <span class="workspace-label">{{ $title }}</span>
            </div>
            <div class="dashboard-header-actions">
                <div class="account-identity">
                    <span class="account-avatar" aria-hidden="true">{{ mb_strtoupper(mb_substr(auth()->user()->name, 0, 1)) }}</span>
                    <div>
                        <strong>{{ auth()->user()->name }}</strong>
                        <span>{{ auth()->user()->email }}</span>
                    </div>
                </div>
                <x-theme-toggle />
                <form action="{{ route('logout') }}" method="POST">
                    @csrf
                    <button type="submit" class="dashboard-logout">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" aria-hidden="true">
                            <path d="M9 5H5v14h4M14 8l4 4-4 4M8 12h10" stroke-linecap="round" stroke-linejoin="round"/>
                        </svg>
                        <span>Keluar</span>
                    </button>
                </form>
            </div>
        </div>
    </header>
    <main id="main-content" class="dashboard-main">
        <x-access-warning />
        {{ $slot }}
    </main>
    </div>
</body>
</html>
