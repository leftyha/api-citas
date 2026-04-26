<?php

declare(strict_types=1);

namespace Booking\Security;

final class Masking
{
    public static function document(string $value): string
    {
        if (strlen($value) <= 4) {
            return str_repeat('*', strlen($value));
        }

        return str_repeat('*', strlen($value) - 4) . substr($value, -4);
    }
}
