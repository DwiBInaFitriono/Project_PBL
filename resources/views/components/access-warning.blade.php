@php
    $warning = session('access_warning');
    $reason = request()->query('access');
    if (!$warning && is_string($reason)) {
        $warning = match ($reason) {
            'login' => 'Silakan masuk terlebih dahulu untuk mengakses halaman ini. Setelah masuk, Anda diarahkan ke Dashboard.',
            'denied' => 'Anda tidak memiliki izin untuk mengakses halaman atau tindakan ini. Anda telah diarahkan ke Dashboard.',
            'expired' => 'Sesi formulir telah berakhir. Silakan buka kembali halaman dan coba lagi.',
            default => null,
        };
    }
@endphp
@if ($warning)
    <div class="access-warning" role="alert" data-access-warning>
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" aria-hidden="true"><circle cx="12" cy="12" r="9"/><path d="M12 7v6m0 3v1"/></svg>
        <p>{{ $warning }}</p>
    </div>
@endif
