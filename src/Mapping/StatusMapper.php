<?php

declare(strict_types=1);

namespace Booking\Mapping;

final class StatusMapper
{
    public static function toPublic(string $internalStatus): string
    {
        return match ($internalStatus) {
            'P', 'pending' => 'pending',
            'C', 'confirmed' => 'confirmed',
            'X', 'cancelled' => 'cancelled',
            default => 'pending',
        };
    }
}
