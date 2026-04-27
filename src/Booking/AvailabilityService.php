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
        $normalizedUuid = trim($licenseUuid);
        $normalizedDate = trim($date);

        $this->validator->validateAvailabilityInput($normalizedUuid, $normalizedDate, $durationMinutes);
        $this->licenseRepository->findInternalByUuidOrFail($normalizedUuid);

        return [
            'date' => $normalizedDate,
            'durationMinutes' => $durationMinutes,
            'slots' => $this->appointmentRepository->listAvailability($normalizedUuid, $normalizedDate, $durationMinutes),
        ];
    }
}
