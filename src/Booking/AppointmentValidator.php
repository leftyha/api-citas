<?php

declare(strict_types=1);

namespace Booking\Booking;

use Booking\Http\ApiException;

final class AppointmentValidator
{
    public function __construct(private readonly array $config)
    {
    }

    public function validateCreateInput(array $input): void
    {
        $licenseUuid = trim((string) ($input['licenseUuid'] ?? ''));
        if (!preg_match('/^[A-Za-z0-9][A-Za-z0-9_-]{2,119}$/', $licenseUuid)) {
            throw new ApiException('licenseUuid inválido.', 'VALIDATION_ERROR', 400, ['field' => 'licenseUuid']);
        }

        $customerDocument = trim((string) ($input['customerDocument'] ?? ''));
        if (strlen($customerDocument) < 5 || strlen($customerDocument) > 30) {
            throw new ApiException('customerDocument inválido.', 'VALIDATION_ERROR', 400, ['field' => 'customerDocument']);
        }

        $customerName = trim((string) ($input['customerName'] ?? ''));
        if (strlen($customerName) < 2 || strlen($customerName) > 120) {
            throw new ApiException('customerName inválido.', 'VALIDATION_ERROR', 400, ['field' => 'customerName']);
        }

        $customerPhone = trim((string) ($input['customerPhone'] ?? ''));
        if (strlen($customerPhone) < 7 || strlen($customerPhone) > 25) {
            throw new ApiException('customerPhone inválido.', 'VALIDATION_ERROR', 400, ['field' => 'customerPhone']);
        }

        $customerEmail = trim((string) ($input['customerEmail'] ?? ''));
        if ($customerEmail !== '' && (!filter_var($customerEmail, FILTER_VALIDATE_EMAIL) || strlen($customerEmail) > 254)) {
            throw new ApiException('customerEmail inválido.', 'VALIDATION_ERROR', 400, ['field' => 'customerEmail']);
        }

        if (isset($input['notes']) && mb_strlen((string) $input['notes']) > 1000) {
            throw new ApiException('notes excede el límite permitido.', 'VALIDATION_ERROR', 400, ['field' => 'notes']);
        }

        if (isset($input['serviceType'])) {
            $serviceType = trim((string) $input['serviceType']);
            if ($serviceType !== '' && (strlen($serviceType) < 2 || strlen($serviceType) > 50)) {
                throw new ApiException('serviceType inválido.', 'VALIDATION_ERROR', 400, ['field' => 'serviceType']);
            }
        }

        if (isset($input['professionalId']) && (!is_numeric($input['professionalId']) || (int) $input['professionalId'] <= 0)) {
            throw new ApiException('professionalId inválido.', 'VALIDATION_ERROR', 400, ['field' => 'professionalId']);
        }

        $durationMinutes = isset($input['durationMinutes'])
            ? (int) $input['durationMinutes']
            : (int) ($this->config['default_duration_minutes'] ?? 30);
        $this->assertValidDuration($durationMinutes, 'durationMinutes');

        $startAt = $this->parseStartAt((string) ($input['startAt'] ?? ''));
        if ($startAt <= new \DateTimeImmutable('now')) {
            throw new ApiException('startAt no puede estar en el pasado.', 'VALIDATION_ERROR', 400, ['field' => 'startAt']);
        }
    }

    public function parseStartAt(string $startAt): \DateTimeImmutable
    {
        if ($startAt === '') {
            throw new ApiException('startAt es obligatorio.', 'VALIDATION_ERROR', 400, ['field' => 'startAt']);
        }

        if (!preg_match('/(Z|[+\-]\d{2}:\d{2})$/', $startAt)) {
            throw new ApiException('startAt debe incluir zona horaria u offset.', 'VALIDATION_ERROR', 400, ['field' => 'startAt']);
        }

        try {
            return new \DateTimeImmutable($startAt);
        } catch (\Throwable) {
            throw new ApiException('startAt inválido.', 'VALIDATION_ERROR', 400, ['field' => 'startAt']);
        }
    }

    public function validateAvailabilityInput(string $licenseUuid, string $date, int $durationMinutes): void
    {
        if (!preg_match('/^[A-Za-z0-9][A-Za-z0-9_-]{2,119}$/', trim($licenseUuid))) {
            throw new ApiException('licenseUuid inválido.', 'VALIDATION_ERROR', 400, ['field' => 'licenseUuid']);
        }

        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
            throw new ApiException('date debe tener formato YYYY-MM-DD.', 'VALIDATION_ERROR', 400, ['field' => 'date']);
        }

        [$year, $month, $day] = array_map('intval', explode('-', $date));
        if (!checkdate($month, $day, $year)) {
            throw new ApiException('date inválida.', 'VALIDATION_ERROR', 400, ['field' => 'date']);
        }

        $this->assertValidDuration($durationMinutes, 'durationMinutes');

        $today = (new \DateTimeImmutable('today'))->format('Y-m-d');
        if ($date < $today) {
            throw new ApiException('date no puede estar en el pasado.', 'VALIDATION_ERROR', 400, ['field' => 'date']);
        }
    }

    private function assertValidDuration(int $durationMinutes, string $field): void
    {
        $allowedDurations = [15, 30, 45, 60, 90, 120];
        if (!in_array($durationMinutes, $allowedDurations, true)) {
            throw new ApiException($field . ' inválido.', 'VALIDATION_ERROR', 400, ['field' => $field]);
        }
    }
}
