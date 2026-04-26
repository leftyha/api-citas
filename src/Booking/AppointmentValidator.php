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
}
