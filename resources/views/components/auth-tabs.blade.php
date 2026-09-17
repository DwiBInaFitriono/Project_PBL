@props(['active'])

<nav class="auth-tabs" aria-label="Pilihan akun">
    <a href="{{ route('login') }}" @if ($active === 'login') aria-current="page" @endif>Masuk</a>
    <a href="{{ route('register') }}" @if ($active === 'register') aria-current="page" @endif>Buat akun</a>
</nav>
