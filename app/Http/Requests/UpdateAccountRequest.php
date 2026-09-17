<?php

namespace App\Http\Requests;

use App\Rules\SafePassword;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class UpdateAccountRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    protected function prepareForValidation(): void
    {
        if ($this->user() !== null) {
            $key = 'settings-account:'.$this->user()->getAuthIdentifier();

            if (RateLimiter::tooManyAttempts($key, 5)) {
                $seconds = RateLimiter::availableIn($key);

                throw ValidationException::withMessages([
                    'current_password' => "Terlalu banyak percobaan menyimpan. Coba lagi dalam {$seconds} detik.",
                ]);
            }

            RateLimiter::hit($key, 60);
        }

        if (is_string($this->input('email'))) {
            $this->merge(['email' => Str::lower(trim($this->input('email')))]);
        }
    }

    /** @return array<string, ValidationRule|array<mixed>|string> */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:254', Rule::unique('users', 'email')->ignore($this->user()->id)],
            'current_password' => ['bail', 'required', 'string', new SafePassword, 'current_password:web'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'name.required' => 'Nama lengkap wajib diisi.',
            'name.string' => 'Nama lengkap harus berupa teks.',
            'name.max' => 'Nama lengkap maksimal 255 karakter.',
            'email.required' => 'Alamat email wajib diisi.',
            'email.string' => 'Masukkan alamat email yang valid.',
            'email.email' => 'Masukkan alamat email yang valid.',
            'email.max' => 'Alamat email maksimal 254 karakter.',
            'email.unique' => 'Alamat email sudah digunakan oleh akun lain.',
            'current_password.required' => 'Kata sandi saat ini wajib diisi.',
            'current_password.string' => 'Kata sandi saat ini harus berupa teks.',
            'current_password.current_password' => 'Kata sandi saat ini tidak sesuai.',
        ];
    }
}
