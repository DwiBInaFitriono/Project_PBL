<?php

namespace App\Http\Requests;

use App\Rules\SafePassword;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;

class UpdatePasswordRequest extends FormRequest
{
    protected $errorBag = 'changePassword';

    protected $redirectRoute = 'settings.account';

    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    protected function prepareForValidation(): void
    {
        if ($this->user() === null) {
            return;
        }
        $key = 'change-password:'.$this->user()->getAuthIdentifier();
        if (RateLimiter::tooManyAttempts($key, 5)) {
            $seconds = RateLimiter::availableIn($key);
            throw ValidationException::withMessages([
                'current_password' => "Terlalu banyak percobaan mengganti kata sandi. Coba lagi dalam {$seconds} detik.",
            ])->errorBag('changePassword')->redirectTo(route('settings.account'));
        }
        RateLimiter::hit($key, 60);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'current_password' => ['bail', 'required', 'string', new SafePassword, 'current_password:web'],
            'password' => ['bail', 'required', 'string', 'min:8', new SafePassword, 'confirmed', 'different:current_password'],
            'password_confirmation' => ['required', 'string'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'current_password.required' => 'Kata sandi saat ini wajib diisi.',
            'current_password.string' => 'Kata sandi saat ini harus berupa teks.',
            'current_password.current_password' => 'Kata sandi saat ini tidak sesuai.',
            'password.required' => 'Kata sandi baru wajib diisi.',
            'password.string' => 'Kata sandi baru harus berupa teks.',
            'password.min' => 'Kata sandi baru minimal 8 karakter.',
            'password.confirmed' => 'Konfirmasi kata sandi baru tidak cocok.',
            'password.different' => 'Kata sandi baru harus berbeda dari kata sandi saat ini.',
            'password_confirmation.required' => 'Konfirmasi kata sandi baru wajib diisi.',
            'password_confirmation.string' => 'Konfirmasi kata sandi baru harus berupa teks.',
        ];
    }
}
