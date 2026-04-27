<?php

declare(strict_types=1);

namespace Booking\Repository;

use Booking\Database\DatabaseClient;
use Booking\Http\ApiException;
use Booking\Mapping\StatusMapper;

final class AppointmentRepository
{
    public function __construct(private readonly DatabaseClient $db)
    {
    }

    public function listAvailability(string $licenseUuid, string $date, int $durationMinutes): array
    {
        $occupiedRows = $this->db->query(
            'SELECT a.start_at, a.end_at, a.status
             FROM booking_appointments a
             INNER JOIN booking_licenses l ON l.license_id = a.license_id
             WHERE l.license_uuid = :licenseUuid
               AND CAST(a.start_at AS DATE) = :date',
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
        $durationMinutes = isset($input['durationMinutes']) ? (int) $input['durationMinutes'] : 30;
        $startAt = new \DateTimeImmutable((string) $input['startAt']);
        $endAt = $startAt->modify(sprintf('+%d minutes', $durationMinutes));
        $professionalId = isset($input['professionalId']) ? (int) $input['professionalId'] : null;
        $status = 'pending';

        $conflictRows = $this->db->query(
            'SELECT TOP 1 appointment_id, customer_document
             FROM booking_appointments WITH (UPDLOCK, HOLDLOCK)
             WHERE license_id = :licenseId
               AND start_at = :startAt
               AND status IN (\'pending\', \'confirmed\')
               AND (:professionalId IS NULL OR professional_id = :professionalId)',
            [
                'licenseId' => $licenseId,
                'startAt' => $startAt->format('Y-m-d H:i:sP'),
                'professionalId' => $professionalId,
            ]
        );

        if ($conflictRows !== []) {
            $row = (array) $conflictRows[0];
            $existingId = (int) ($row['appointmentId'] ?? $row['appointment_id'] ?? 0);
            $existingDocument = trim((string) ($row['customerDocument'] ?? $row['customer_document'] ?? ''));

            if (
                $existingId > 0
                && $existingDocument !== ''
                && hash_equals(strtolower($existingDocument), strtolower((string) $input['customerDocument']))
            ) {
                return $existingId;
            }

            throw new ApiException('El horario seleccionado ya no está disponible.', 'SLOT_CONFLICT', 409);
        }

        $insertRows = $this->db->query(
            'INSERT INTO booking_appointments
                (license_id, customer_document, customer_name, customer_phone, customer_email, start_at, end_at, duration_minutes, service_type, professional_id, notes, status)
             OUTPUT INSERTED.appointment_id
             VALUES
                (:licenseId, :customerDocument, :customerName, :customerPhone, :customerEmail, :startAt, :endAt, :durationMinutes, :serviceType, :professionalId, :notes, :status)',
            [
                'licenseId' => $licenseId,
                'customerDocument' => trim((string) $input['customerDocument']),
                'customerName' => trim((string) $input['customerName']),
                'customerPhone' => trim((string) $input['customerPhone']),
                'customerEmail' => trim((string) ($input['customerEmail'] ?? '')) ?: null,
                'startAt' => $startAt->format('Y-m-d H:i:sP'),
                'endAt' => $endAt->format('Y-m-d H:i:sP'),
                'durationMinutes' => $durationMinutes,
                'serviceType' => trim((string) ($input['serviceType'] ?? '')) ?: null,
                'professionalId' => $professionalId,
                'notes' => trim((string) ($input['notes'] ?? '')) ?: null,
                'status' => $status,
            ]
        );

        if ($insertRows === []) {
            throw new ApiException('No fue posible crear la cita.', 'APPOINTMENT_CREATE_FAILED', 500);
        }

        $row = (array) $insertRows[0];
        $appointmentId = (int) ($row['appointmentId'] ?? $row['appointment_id'] ?? 0);
        if ($appointmentId <= 0) {
            throw new ApiException('No fue posible crear la cita.', 'APPOINTMENT_CREATE_FAILED', 500);
        }

        return $appointmentId;
    }

    public function findPublicById(int $appointmentId): ?array
    {
        $rows = $this->db->query(
            'SELECT TOP 1
                a.appointment_id,
                l.license_uuid,
                a.status,
                a.start_at,
                a.end_at,
                a.duration_minutes,
                a.service_type,
                a.customer_document,
                a.customer_phone
             FROM booking_appointments a
             INNER JOIN booking_licenses l ON l.license_id = a.license_id
             WHERE a.appointment_id = :appointmentId',
            ['appointmentId' => $appointmentId]
        );

        if ($rows === []) {
            return null;
        }

        return $this->normalizePublicAppointmentRow((array) $rows[0]);
    }

    public function listAdmin(array $filters): array
    {
        $clauses = [];
        $params = [];

        if (!empty($filters['date'])) {
            $clauses[] = 'CAST(a.start_at AS DATE) = :date';
            $params['date'] = (string) $filters['date'];
        }

        if (!empty($filters['status'])) {
            $clauses[] = 'a.status = :status';
            $params['status'] = StatusMapper::normalizeInternal((string) $filters['status']);
        }

        if (!empty($filters['professionalId'])) {
            $clauses[] = 'a.professional_id = :professionalId';
            $params['professionalId'] = (int) $filters['professionalId'];
        }

        if (!empty($filters['customerDocument'])) {
            $clauses[] = 'a.customer_document = :customerDocument';
            $params['customerDocument'] = trim((string) $filters['customerDocument']);
        }

        $where = $clauses === [] ? '' : ('WHERE ' . implode(' AND ', $clauses));

        $rows = $this->db->query(
            'SELECT
                a.appointment_id,
                l.license_uuid,
                a.status,
                a.start_at,
                a.end_at,
                a.duration_minutes,
                a.service_type,
                a.professional_id,
                a.customer_document,
                a.customer_name,
                a.customer_phone,
                a.customer_email,
                a.notes
             FROM booking_appointments a
             INNER JOIN booking_licenses l ON l.license_id = a.license_id
             ' . $where . '
             ORDER BY a.start_at ASC',
            $params
        );

        return array_values(array_filter(array_map(fn ($row) => $this->normalizeAdminRow((array) $row), $rows)));
    }

    public function findAdminById(int $appointmentId): ?array
    {
        $rows = $this->db->query(
            'SELECT TOP 1
                a.appointment_id,
                l.license_uuid,
                a.status,
                a.start_at,
                a.end_at,
                a.duration_minutes,
                a.service_type,
                a.professional_id,
                a.customer_document,
                a.customer_name,
                a.customer_phone,
                a.customer_email,
                a.notes
             FROM booking_appointments a
             INNER JOIN booking_licenses l ON l.license_id = a.license_id
             WHERE a.appointment_id = :appointmentId',
            ['appointmentId' => $appointmentId]
        );

        if ($rows === []) {
            return null;
        }

        return $this->normalizeAdminRow((array) $rows[0]);
    }

    public function updateAdmin(int $appointmentId, array $input): array
    {
        $current = $this->findAdminById($appointmentId);
        if ($current === null) {
            throw new ApiException('Cita no encontrada.', 'APPOINTMENT_NOT_FOUND', 404);
        }

        $fields = [];
        $params = ['appointmentId' => $appointmentId];

        if (array_key_exists('startAt', $input) && is_string($input['startAt']) && trim($input['startAt']) !== '') {
            $startAt = new \DateTimeImmutable((string) $input['startAt']);
            $duration = isset($input['durationMinutes']) ? (int) $input['durationMinutes'] : (int) $current['durationMinutes'];
            $fields[] = 'start_at = :startAt';
            $fields[] = 'end_at = :endAt';
            $params['startAt'] = $startAt->format('Y-m-d H:i:sP');
            $params['endAt'] = $startAt->modify(sprintf('+%d minutes', $duration))->format('Y-m-d H:i:sP');
        }

        $scalarMap = [
            'durationMinutes' => 'duration_minutes',
            'serviceType' => 'service_type',
            'professionalId' => 'professional_id',
            'notes' => 'notes',
            'customerName' => 'customer_name',
            'customerPhone' => 'customer_phone',
            'customerEmail' => 'customer_email',
        ];

        foreach ($scalarMap as $key => $column) {
            if (!array_key_exists($key, $input)) {
                continue;
            }

            $fields[] = $column . ' = :' . $key;
            $value = $input[$key];
            if (is_string($value)) {
                $value = trim($value);
            }
            $params[$key] = $value;
        }

        if ($fields !== []) {
            $this->db->query(
                'UPDATE booking_appointments
                 SET ' . implode(', ', $fields) . '
                 WHERE appointment_id = :appointmentId',
                $params
            );
        }

        return $this->findAdminById($appointmentId) ?? $current;
    }

    public function transitionStatus(int $appointmentId, string $status): array
    {
        $current = $this->findAdminById($appointmentId);
        if ($current === null) {
            throw new ApiException('Cita no encontrada.', 'APPOINTMENT_NOT_FOUND', 404);
        }

        $next = StatusMapper::normalizeInternal($status);
        $from = (string) ($current['status'] ?? 'pending');

        if (!StatusMapper::canTransition($from, $next)) {
            throw new ApiException('Transición de estado inválida.', 'INVALID_STATUS_TRANSITION', 422);
        }

        $this->db->query(
            'UPDATE booking_appointments
             SET status = :status
             WHERE appointment_id = :appointmentId',
            [
                'status' => $next,
                'appointmentId' => $appointmentId,
            ]
        );

        return $this->findAdminById($appointmentId) ?? ['appointmentId' => $appointmentId, 'status' => $next];
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

        $startTime = '09:00:00';
        $endTime = $weekday === 6 ? '13:00:00' : '17:00:00';

        $start = new \DateTimeImmutable($date . 'T' . $startTime . '-04:00');
        $end = new \DateTimeImmutable($date . 'T' . $endTime . '-04:00');

        return [$start, $end];
    }

    private function normalizePublicAppointmentRow(array $row): ?array
    {
        $licenseUuid = trim((string) ($row['licenseUuid'] ?? $row['license_uuid'] ?? ''));
        $status = StatusMapper::toPublic((string) ($row['status'] ?? 'pending'));
        $startRaw = $row['startAt'] ?? $row['start_at'] ?? null;
        $endRaw = $row['endAt'] ?? $row['end_at'] ?? null;
        $durationMinutes = (int) ($row['durationMinutes'] ?? $row['duration_minutes'] ?? 0);

        if ($licenseUuid === '' || !is_string($startRaw) || !is_string($endRaw) || $durationMinutes <= 0) {
            return null;
        }

        $startAt = date_create_immutable($startRaw);
        $endAt = date_create_immutable($endRaw);
        if (!$startAt instanceof \DateTimeImmutable || !$endAt instanceof \DateTimeImmutable || $startAt >= $endAt) {
            return null;
        }

        return [
            'licenseUuid' => $licenseUuid,
            'status' => $status,
            'startAt' => $startAt->format('c'),
            'endAt' => $endAt->format('c'),
            'durationMinutes' => $durationMinutes,
            'serviceType' => ($row['serviceType'] ?? $row['service_type'] ?? null) ?: null,
            'customerDocument' => trim((string) ($row['customerDocument'] ?? $row['customer_document'] ?? '')),
            'customerPhone' => trim((string) ($row['customerPhone'] ?? $row['customer_phone'] ?? '')),
        ];
    }

    private function normalizeAdminRow(array $row): ?array
    {
        $appointmentId = (int) ($row['appointmentId'] ?? $row['appointment_id'] ?? 0);
        $licenseUuid = trim((string) ($row['licenseUuid'] ?? $row['license_uuid'] ?? ''));
        $startRaw = $row['startAt'] ?? $row['start_at'] ?? null;
        $endRaw = $row['endAt'] ?? $row['end_at'] ?? null;

        if ($appointmentId <= 0 || $licenseUuid === '' || !is_string($startRaw) || !is_string($endRaw)) {
            return null;
        }

        $startAt = date_create_immutable($startRaw);
        $endAt = date_create_immutable($endRaw);
        if (!$startAt instanceof \DateTimeImmutable || !$endAt instanceof \DateTimeImmutable) {
            return null;
        }

        return [
            'appointmentId' => $appointmentId,
            'licenseUuid' => $licenseUuid,
            'status' => StatusMapper::toPublic((string) ($row['status'] ?? 'pending')),
            'startAt' => $startAt->format('c'),
            'endAt' => $endAt->format('c'),
            'durationMinutes' => (int) ($row['durationMinutes'] ?? $row['duration_minutes'] ?? 0),
            'serviceType' => ($row['serviceType'] ?? $row['service_type'] ?? null) ?: null,
            'professionalId' => ($row['professionalId'] ?? $row['professional_id'] ?? null),
            'customer' => [
                'document' => trim((string) ($row['customerDocument'] ?? $row['customer_document'] ?? '')),
                'name' => trim((string) ($row['customerName'] ?? $row['customer_name'] ?? '')),
                'phone' => trim((string) ($row['customerPhone'] ?? $row['customer_phone'] ?? '')),
                'email' => trim((string) ($row['customerEmail'] ?? $row['customer_email'] ?? '')),
            ],
            'notes' => ($row['notes'] ?? null) ?: null,
        ];
    }
}
