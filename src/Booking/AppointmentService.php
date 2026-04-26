<?php

declare(strict_types=1);

namespace Booking\Booking;

use Booking\Http\ApiException;
use Booking\Repository\AppointmentRepository;
use Booking\Repository\LicenseRepository;
use Booking\Security\AppointmentTokenService;

final class AppointmentService
{
    public function __construct(
        private readonly AppointmentRepository $appointmentRepository,
        private readonly LicenseRepository $licenseRepository,
        private readonly AppointmentTokenService $tokenService,
        private readonly AppointmentValidator $validator
    ) {
    }

    public function create(array $input, int $tokenTtl): array
    {
        $this->validator->validateCreateInput($input);
        $license = $this->licenseRepository->findInternalByUuidOrFail((string) $input['licenseUuid']);

        $appointmentId = $this->appointmentRepository->create($license['licenseId'], $input);
        $token = $this->tokenService->issue($appointmentId, (string) $input['licenseUuid'], $tokenTtl);

        return [
            'appointmentToken' => $token,
            'status' => 'pending',
            'startAt' => $input['startAt'],
        ];
    }

    public function getPublicByToken(string $token): array
    {
        $payload = $this->tokenService->parse($token);
        $appointment = $this->appointmentRepository->findPublicById((int) $payload['appointmentId']);

        if ($appointment === null || $appointment['licenseUuid'] !== $payload['licenseUuid']) {
            throw new ApiException('No se encontró una cita válida.', 'APPOINTMENT_NOT_FOUND', 404);
        }

        unset($appointment['licenseUuid']);
        return $appointment;
    }

    public function listAdmin(array $filters): array
    {
        return $this->appointmentRepository->listAdmin($filters);
    }

    public function getAdmin(int $appointmentId): array
    {
        $appointment = $this->appointmentRepository->findAdminById($appointmentId);
        if ($appointment === null) {
            throw new ApiException('Cita no encontrada.', 'APPOINTMENT_NOT_FOUND', 404);
        }

        return $appointment;
    }

    public function updateAdmin(int $appointmentId, array $input): array
    {
        return $this->appointmentRepository->updateAdmin($appointmentId, $input);
    }

    public function transitionAdmin(int $appointmentId, string $status): array
    {
        return $this->appointmentRepository->transitionStatus($appointmentId, $status);
    }
}
