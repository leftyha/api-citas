<?php

declare(strict_types=1);

namespace Booking\Mapping;

final class AppointmentMapper
{
    public static function toPublic(array $row): array
    {
        return [
            'status' => StatusMapper::toPublic((string) ($row['status'] ?? 'pending')),
            'startAt' => $row['startAt'] ?? null,
            'endAt' => $row['endAt'] ?? null,
        ];
    }
}
