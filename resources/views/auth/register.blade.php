<x-auth-layout title="Daftar" page="register">
    <div class="eyebrow"><span></span> AKUN HIDROPONIK DFT</div>
    <h1>Buat akun Hidroponik DFT.</h1>
    <p class="form-intro">Daftar untuk mengakses sistem monitoring hidroponik DFT melalui Node Nutrisi dan Node Iklim.</p>
    <x-auth-tabs active="register" />

    <form action="{{ route('register.store') }}" method="POST" class="auth-form">
        @csrf
        <x-form.input name="name" label="Nama lengkap" placeholder="Nama lengkapmu"
            autocomplete="name" required maxlength="255" />
        <x-form.input name="email" label="Alamat email" type="email" placeholder="nama@contoh.com"
            autocomplete="email" required maxlength="254" />
        <x-form.input name="password" label="Kata sandi" type="password" placeholder="Buat kata sandi"
            autocomplete="new-password" required minlength="8"
            hint="Minimal 8 karakter, maksimal 72 byte. Karakter khusus dapat memakai lebih dari satu byte." />
        <x-form.input name="password_confirmation" label="Konfirmasi kata sandi" type="password"
            placeholder="Ulangi kata sandi" autocomplete="new-password" required minlength="8" />
        <button class="primary-button" type="submit">
            <span>Buat akun</span><span aria-hidden="true">↗</span>
        </button>
    </form>

    <p class="form-note">Sudah punya akun? Pilih Masuk di atas.</p>
</x-auth-layout>
