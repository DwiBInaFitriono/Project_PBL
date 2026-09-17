export function initPasswordVisibility() {
    for (const button of document.querySelectorAll('[data-password-toggle]')) {
        button.addEventListener('click', () => {
            const input = document.getElementById(button.dataset.passwordToggle);
            if (!input) return;
            const visible = input.type === 'password';
            const field = input.name === 'password_confirmation' ? 'konfirmasi kata sandi' : 'kata sandi';
            input.type = visible ? 'text' : 'password';
            button.setAttribute('aria-pressed', String(visible));
            button.setAttribute('aria-label', `${visible ? 'Sembunyikan' : 'Tampilkan'} ${field}`);
        });
    }
}
