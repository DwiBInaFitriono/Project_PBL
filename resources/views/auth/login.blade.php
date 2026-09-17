<x-auth-layout title="Masuk" page="login">
    <div class="eyebrow"><span></span> MONITORING REBUNG BAMBU</div>
    <h1>Selamat datang kembali.</h1>
    <p class="form-intro">Masuk ke sistem monitoring rebung bambu melalui node 1 dan node 2.</p>
    <x-auth-tabs active="login" />

    <form action="{{ route('login.store') }}" method="POST" class="auth-form">
        @csrf
        <x-form.input name="email" label="Alamat email" type="email"
            placeholder="nama@contoh.com" autocomplete="email" required maxlength="254" />
        <x-form.input name="password" label="Kata sandi" type="password"
            placeholder="Masukkan kata sandi" autocomplete="current-password" required />
        <label class="checkbox-label">
            <input type="checkbox" name="remember" value="1" @checked(old('remember'))>
            <span>Ingat saya di perangkat ini</span>
        </label>
        <button class="primary-button" type="submit">
            <span>Masuk</span><span aria-hidden="true">↗</span>
        </button>
    </form>

    <p class="form-note">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" aria-hidden="true">
            <rect x="5" y="10" width="14" height="11" rx="3"/>
            <path d="M8 10V7a4 4 0 0 1 8 0v3"/>
        </svg>
        Akses monitoring melalui akunmu.
    </p>
</x-auth-layout>
