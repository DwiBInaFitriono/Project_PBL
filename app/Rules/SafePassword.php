<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class SafePassword implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value)) {
            return;
        }

        if (str_contains($value, "\0")) {
            $fail('Kata sandi mengandung karakter yang tidak valid.');
        }

        if (strlen($value) > 72) {
            $fail('Kata sandi maksimal 72 byte. Karakter khusus dapat memakai lebih dari satu byte.');
        }
    }
}
