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
        foreach (['licenseUuid', 'customerDocument', 'customerName', 'customerPhone', 'startAt'] as $required) {
            if (empty($input[$required])) {
                throw new ApiException('Datos incompletos para crear cita.', 'VALIDATION_ERROR', 400);
            }
        }

        if (strtotime((string) $input['startAt']) === false) {
            throw new ApiException('startAt inválido.', 'VALIDATION_ERROR', 400);
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

        $allowedDurations = [15, 30, 45, 60, 90, 120];
        if (!in_array($durationMinutes, $allowedDurations, true)) {
            throw new ApiException('durationMinutes inválido.', 'VALIDATION_ERROR', 400, ['field' => 'durationMinutes']);
        }

        $today = (new \DateTimeImmutable('today'))->format('Y-m-d');
        if ($date < $today) {
            throw new ApiException('date no puede estar en el pasado.', 'VALIDATION_ERROR', 400, ['field' => 'date']);
        }
    }
}
