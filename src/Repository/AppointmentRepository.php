<?php

declare(strict_types=1);

namespace Booking\Repository;

use Booking\Database\DatabaseClient;

final class AppointmentRepository
{
    public function __construct(private readonly DatabaseClient $db)
    {
    }

    public function listAvailability(string $licenseUuid, string $date, int $durationMinutes): array
    {
        return [
            [
                'startAt' => $date . 'T09:00:00-04:00',
                'endAt' => $date . 'T09:30:00-04:00',
                'available' => true,
            ],
            [
                'startAt' => $date . 'T09:30:00-04:00',
                'endAt' => $date . 'T10:00:00-04:00',
                'available' => false,
            ],
        ];
    }

    public function create(int $licenseId, array $input): int
    {
        return random_int(1000, 9999);
    }

    public function findPublicById(int $appointmentId): ?array
    {
        return [
            'appointmentToken' => null,
            'licenseUuid' => 'abc123',
            'status' => 'pending',
            'startAt' => '2026-05-04T10:30:00-04:00',
            'endAt' => '2026-05-04T11:00:00-04:00',
        ];
    }

    public function listAdmin(array $filters): array
    {
        return [];
    }

    public function findAdminById(int $appointmentId): ?array
    {
        return [
            'appointmentId' => $appointmentId,
            'status' => 'pending',
        ];
    }

    public function updateAdmin(int $appointmentId, array $input): array
    {
        return ['appointmentId' => $appointmentId, 'updated' => true];
    }

    public function transitionStatus(int $appointmentId, string $status): array
    {
        return ['appointmentId' => $appointmentId, 'status' => $status];
    }
}
