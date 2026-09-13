<?php

namespace App\Services;

use Illuminate\Validation\ValidationException;

class EmailNormalizer
{
    /** @return array<int, string> */
    public function rules(): array
    {
        return ['required', 'string', 'email:rfc', 'max:254'];
    }

    public function validate(string $email): string
    {
        validator(['email' => trim($email)], ['email' => $this->rules()])->validate();

        return $this->normalize($email);
    }

    public function normalize(string $email): string
    {
        $normalized = mb_strtolower(trim($email));

        if ($normalized === '' || mb_strlen($normalized) > 254) {
            throw ValidationException::withMessages(['email' => 'Enter a valid email address.']);
        }

        return $normalized;
    }
}
