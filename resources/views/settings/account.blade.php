<x-dashboard-layout title="Setting Akun">
    <div class="settings-page">
        <section class="settings-intro" aria-labelledby="settings-title">
            <p class="eyebrow">PROFIL PENGGUNA</p>
            <h1 id="settings-title">Setting Akun</h1>
            <p>Perbarui nama dan alamat email akun yang sedang masuk.</p>
        </section>

        <section class="settings-panel" aria-labelledby="profile-title">
            <h2 id="profile-title">Informasi akun</h2>
            <dl class="settings-meta">
                <div>
                    <dt>Peran akun</dt>
                    <dd>{{ $user->isOperator() ? 'Operator' : 'Pemantau' }}</dd>
                </div>
            </dl>
            <p>Peran hanya dapat diubah oleh administrator melalui CLI, bukan melalui formulir profil.</p>
            <p>Masukkan kata sandi saat ini untuk mengonfirmasi perubahan profil. Kata sandi tidak akan diubah.</p>

            @if (session('status'))
                <p class="settings-status" role="status">{{ session('status') }}</p>
            @endif

            <form action="{{ route('settings.account.update') }}" method="POST" class="settings-form">
                @csrf
                @method('PATCH')

                <div class="settings-grid">
                    @foreach (['name' => ['Nama lengkap', 'text', 'name', 255], 'email' => ['Alamat email', 'email', 'email', 254]] as $field => [$label, $type, $autocomplete, $maxLength])
                        @php($value = old($field, $user->{$field}))
                        <div class="settings-field field">
                            <label for="{{ $field }}">{{ $label }}</label>
                            <input id="{{ $field }}" name="{{ $field }}" type="{{ $type }}"
                                value="{{ is_string($value) ? $value : '' }}" autocomplete="{{ $autocomplete }}"
                                required maxlength="{{ $maxLength }}"
                                @error($field) aria-invalid="true" aria-describedby="{{ $field }}-error" @enderror>
                            @error($field)
                                <p id="{{ $field }}-error" class="field-error" role="alert">{{ $message }}</p>
                            @enderror
                        </div>
                    @endforeach
                </div>

                <div class="settings-field">
                    <x-form.input name="current_password" label="Kata sandi saat ini" type="password"
                        autocomplete="current-password" required
                        hint="Diperlukan untuk memastikan perubahan dilakukan oleh pemilik akun." />
                </div>
                <button class="settings-save primary-button" type="submit">Simpan perubahan</button>
            </form>
        </section>
        @include('settings.password-form')
    </div>
</x-dashboard-layout>
