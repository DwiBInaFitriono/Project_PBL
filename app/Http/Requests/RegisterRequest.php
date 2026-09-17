<?php

namespace App\Http\Requests;

use App\Rules\SafePassword;
use Illuminate\Contracts\Validation\ValidationRule;

class RegisterRequest extends AuthenticationRequest
{
    /** @return array<string, string> */
    public function messages(): array
    {
        return [...parent::messages(),
            'name.required' => 'Nama lengkap wajib diisi.',
            'name.string' => 'Nama lengkap harus berupa teks.',
            'name.max' => 'Nama lengkap maksimal 255 karakter.',
            'email.unique' => 'Alamat email sudah terdaftar. Silakan masuk.',
            'password.min' => 'Kata sandi minimal 8 karakter.',
            'password.confirmed' => 'Konfirmasi kata sandi belum cocok.',
        ];
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users,email'],
            'password' => ['bail', 'required', 'string', 'min:8', new SafePassword, 'confirmed'],
        ];
    }
}
