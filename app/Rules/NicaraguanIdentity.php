<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class NicaraguanIdentity implements ValidationRule
{
    private const LETTERS = 'ABCDEFGHJKLMNPQRSTUVWXY';

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $identity = strtoupper(trim((string) $value));
        if (! preg_match('/^(\d{3})-(\d{6})-(\d{4})([A-Z])$/', $identity, $parts)) {
            $fail('La cédula debe tener el formato 000-000000-0000A.');

            return;
        }
        $numeric = $parts[1].$parts[2].$parts[3];
        $expected = self::LETTERS[((int) $numeric) % 23];
        if ($parts[4] !== $expected) {
            $fail('La letra de control de la cédula no es válida.');
        }
    }
}
