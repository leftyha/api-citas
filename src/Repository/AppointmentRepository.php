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
        $occupiedRows = $this->db->query(
            'SELECT start_at, end_at, status FROM booking_appointments WHERE license_uuid = :licenseUuid AND CAST(start_at AS DATE) = :date',
            [
                'licenseUuid' => $licenseUuid,
                'date' => $date,
            ]
        );

        $occupiedSlots = $this->normalizeOccupiedIntervals($occupiedRows, $date);

        $dayWindow = $this->getDayWindow($date);
        if ($dayWindow === null) {
            return [];
        }

        [$dayStart, $dayEnd] = $dayWindow;
        $cursor = $dayStart;
        $slots = [];

        while ($cursor < $dayEnd) {
            $slotEnd = $cursor->modify(sprintf('+%d minutes', $durationMinutes));
            if ($slotEnd > $dayEnd) {
                break;
            }

            if (!$this->intersectsOccupied($cursor, $slotEnd, $occupiedSlots)) {
                $slots[] = [
                    'startAt' => $cursor->format('Y-m-d\TH:i:sP'),
                    'endAt' => $slotEnd->format('Y-m-d\TH:i:sP'),
                ];
            }

            $cursor = $slotEnd;
        }

        return $slots;
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

    private function normalizeOccupiedIntervals(array $rows, string $date): array
    {
        $intervals = [];

        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }

            $status = strtolower((string) ($row['status'] ?? $row['appointment_status'] ?? 'pending'));
            if (!in_array($status, ['pending', 'confirmed'], true)) {
                continue;
            }

            $startRaw = $row['startAt'] ?? $row['start_at'] ?? null;
            $endRaw = $row['endAt'] ?? $row['end_at'] ?? null;
            if (!is_string($startRaw) || !is_string($endRaw)) {
                continue;
            }

            $start = date_create_immutable($startRaw);
            $end = date_create_immutable($endRaw);
            if (!$start instanceof \DateTimeImmutable || !$end instanceof \DateTimeImmutable || $start >= $end) {
                continue;
            }

            if ($start->format('Y-m-d') !== $date) {
                continue;
            }

            $intervals[] = [$start, $end];
        }

        return $intervals;
    }

    private function intersectsOccupied(\DateTimeImmutable $start, \DateTimeImmutable $end, array $occupiedIntervals): bool
    {
        foreach ($occupiedIntervals as [$occupiedStart, $occupiedEnd]) {
            if ($start < $occupiedEnd && $end > $occupiedStart) {
                return true;
            }
        }

        return false;
    }

    private function getDayWindow(string $date): ?array
    {
        $weekday = (int) (new \DateTimeImmutable($date))->format('N');

        if ($weekday === 7) {
            return null;
        }

        $startTime = $weekday === 6 ? '09:00:00' : '09:00:00';
        $endTime = $weekday === 6 ? '13:00:00' : '17:00:00';

        $start = new \DateTimeImmutable($date . 'T' . $startTime . '-04:00');
        $end = new \DateTimeImmutable($date . 'T' . $endTime . '-04:00');

        return [$start, $end];
    }
}
