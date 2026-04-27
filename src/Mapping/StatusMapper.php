<?php

declare(strict_types=1);

namespace Booking\Mapping;

final class StatusMapper
{
    public static function toPublic(string $internalStatus): string
    {
        return match (strtolower(trim($internalStatus))) {
            'p', 'pending' => 'pending',
            'c', 'confirmed' => 'confirmed',
            'x', 'cancelled', 'canceled' => 'cancelled',
            default => 'unknown',
        };
    }

    public static function normalizeInternal(string $status): string
    {
        return match (strtolower(trim($status))) {
            'p', 'pending' => 'pending',
            'c', 'confirmed' => 'confirmed',
            'x', 'cancelled', 'canceled' => 'cancelled',
            default => throw new \InvalidArgumentException('Estado inválido: ' . $status),
        };
    }

    public static function canTransition(string $from, string $to): bool
    {
        $normalizedFrom = self::normalizeInternal($from);
        $normalizedTo = self::normalizeInternal($to);

        if ($normalizedFrom === $normalizedTo) {
            return true;
        }

        return match ($normalizedFrom) {
            'pending' => in_array($normalizedTo, ['confirmed', 'cancelled'], true),
            'confirmed' => $normalizedTo === 'cancelled',
            'cancelled' => false,
            default => false,
        };
    }
}
