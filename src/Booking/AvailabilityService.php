<?php

declare(strict_types=1);

namespace Booking\Booking;

use Booking\Repository\AppointmentRepository;
use Booking\Repository\LicenseRepository;

final class AvailabilityService
{
    public function __construct(
        private readonly AppointmentRepository $appointmentRepository,
        private readonly LicenseRepository $licenseRepository,
        private readonly AppointmentValidator $validator
    ) {
    }

    public function getSlots(string $licenseUuid, string $date, int $durationMinutes = 30): array
    {
        $this->validator->validateAvailabilityInput($licenseUuid, $date, $durationMinutes);
        $this->licenseRepository->findInternalByUuidOrFail($licenseUuid);

        return [
            'date' => $date,
            'durationMinutes' => $durationMinutes,
            'slots' => $this->appointmentRepository->listAvailability($licenseUuid, $date, $durationMinutes),
        ];
    }
}
