<?php

namespace App\Http\Requests;

use App\Rules\SafePassword;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;

class LoginRequest extends AuthenticationRequest
{
    public function throttleKey(): string
    {
        return 'login:'.$this->input('email').'|'.$this->ip();
    }

    public function ensureIsNotRateLimited(): void
    {
        if (RateLimiter::tooManyAttempts($this->throttleKey(), 5)) {
            $seconds = RateLimiter::availableIn($this->throttleKey());

            throw ValidationException::withMessages([
                'email' => "Terlalu banyak percobaan masuk. Coba lagi dalam {$seconds} detik.",
            ]);
        }
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [...parent::messages(),
            'remember.boolean' => 'Pilihan ingat saya tidak valid.',
        ];
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'email' => ['required', 'string', 'email'],
            'password' => ['bail', 'required', 'string', new SafePassword],
            'remember' => ['sometimes', 'boolean'],
        ];
    }
}
