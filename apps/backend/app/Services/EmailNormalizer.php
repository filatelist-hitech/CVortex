<?php

namespace App\Services;

use Illuminate\Validation\ValidationException;

class EmailNormalizer
{
    public function normalize(string $email): string
    {
        $normalized = mb_strtolower(trim($email));

        if ($normalized === '' || mb_strlen($normalized) > 254) {
            throw ValidationException::withMessages(['email' => 'Enter a valid email address.']);
        }

        return $normalized;
    }
}
