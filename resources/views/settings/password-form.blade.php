<section class="settings-panel" aria-labelledby="password-title">
    <h2 id="password-title">Ganti kata sandi</h2>
    <p>Konfirmasi kata sandi saat ini sebelum menggantinya. Sesi lain yang memakai kata sandi lama perlu masuk kembali.</p>
    @if (session('password_status'))
        <p class="settings-status" role="status">{{ session('password_status') }}</p>
    @endif
    <form action="{{ route('settings.password.update') }}" method="POST" class="settings-form" data-password-form>
        @csrf
        @method('PATCH')
        <x-form.input id="change-current-password" name="current_password" label="Kata sandi saat ini" type="password" autocomplete="current-password" required error-bag="changePassword" />
        <x-form.input id="change-password" name="password" label="Kata sandi baru" type="password" autocomplete="new-password" required minlength="8" error-bag="changePassword" hint="Minimal 8 karakter, maksimal 72 byte. Gunakan kata sandi unik yang sulit ditebak." />
        <x-form.input id="change-password-confirmation" name="password_confirmation" label="Konfirmasi kata sandi baru" type="password" autocomplete="new-password" required minlength="8" error-bag="changePassword" />
        <button class="settings-save" type="submit">Ganti kata sandi</button>
    </form>
</section>
