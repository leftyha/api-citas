<?php

declare(strict_types=1);

namespace Booking\Mapping;

final class LicenseMapper
{
    public static function toPublic(array $row): array
    {
        return [
            'licenseUuid' => $row['licenseUuid'] ?? null,
            'licenseName' => $row['licenseName'] ?? null,
            'logoUrl' => $row['logoUrl'] ?? null,
            'bookingEnabled' => (bool) ($row['bookingEnabled'] ?? false),
        ];
    }
}
