<?php

declare(strict_types=1);

namespace Booking\Mapping;

final class LicenseMapper
{
    public static function toPublic(array $row): array
    {
        $bookingEnabled = self::booleanValue(
            $row['bookingEnabled']
            ?? $row['booking_enabled']
            ?? $row['isBookingEnabled']
            ?? $row['is_active']
            ?? true
        );

        return [
            'licenseUuid' => self::stringOrNull($row['licenseUuid'] ?? $row['license_uuid'] ?? null),
            'licenseName' => self::stringOrNull($row['licenseName'] ?? $row['license_name'] ?? $row['name'] ?? null),
            'logoUrl' => self::stringOrNull($row['logoUrl'] ?? $row['logo_url'] ?? null),
            'bookingEnabled' => $bookingEnabled,
        ];
    }

    private static function stringOrNull(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $value = trim((string) $value);
        return $value === '' ? null : $value;
    }

    private static function booleanValue(mixed $value): bool
    {
        if (is_bool($value)) {
            return $value;
        }

        if (is_numeric($value)) {
            return (int) $value === 1;
        }

        $normalized = strtolower(trim((string) $value));
        return in_array($normalized, ['1', 'true', 'yes', 'si', 'enabled', 'active'], true);
    }
}
