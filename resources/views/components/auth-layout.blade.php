@props(['title', 'page' => null])

<!DOCTYPE html>
<html lang="id" data-theme="light">
<head>
    <x-page-head :title="$title" />
</head>
<body @if ($page) data-page="{{ $page }}" @endif>
    <div class="auth-shell">
        <section class="form-panel">
            <header class="page-header">
                <x-brand />
                <x-theme-toggle />
            </header>
            <main id="main-content" class="form-content {{ $page === 'register' ? 'form-content-register' : '' }}">
                <x-access-warning />
                {{ $slot }}
            </main>
            <footer class="page-footer">
                <span>Rebung Pintar</span>
                <span>Monitoring rebung bambu</span>
            </footer>
        </section>
    </div>
</body>
</html>
