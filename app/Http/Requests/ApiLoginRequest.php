<?php

namespace App\Http\Requests;

use App\Rules\SafePassword;

class ApiLoginRequest extends AuthenticationRequest
{
    public function rules(): array
    {
        return [
            'email' => ['required', 'string', 'email', 'max:254'],
            'password' => ['bail', 'required', 'string', new SafePassword],
            'device_name' => ['required', 'string', 'max:100'],
        ];
    }

    public function messages(): array
    {
        return [...parent::messages(), 'device_name.*' => 'Nama perangkat wajib diisi, maksimal 100 karakter.'];
    }
}
