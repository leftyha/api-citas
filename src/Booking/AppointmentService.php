<?php

declare(strict_types=1);

namespace Booking\Booking;

use Booking\Http\ApiException;
use Booking\Repository\AppointmentRepository;
use Booking\Repository\LicenseRepository;
use Booking\Security\Masking;
use Booking\Security\AppointmentTokenService;
use Booking\Database\DatabaseClient;
use Booking\Mapping\StatusMapper;

final class AppointmentService
{
    public function __construct(
        private readonly AppointmentRepository $appointmentRepository,
        private readonly LicenseRepository $licenseRepository,
        private readonly AppointmentTokenService $tokenService,
        private readonly AppointmentValidator $validator,
        private readonly DatabaseClient $db
    ) {
    }

    public function create(array $input, int $tokenTtl): array
    {
        $this->validator->validateCreateInput($input);
        $license = $this->licenseRepository->findInternalByUuidOrFail((string) $input['licenseUuid']);

        $durationMinutes = isset($input['durationMinutes'])
            ? (int) $input['durationMinutes']
            : 30;
        $startAt = $this->validator->parseStartAt((string) $input['startAt']);
        $endAt = $startAt->modify(sprintf('+%d minutes', $durationMinutes));

        $appointmentInput = $input;
        $appointmentInput['durationMinutes'] = $durationMinutes;
        $appointmentInput['startAt'] = $startAt->format('c');
        $appointmentInput['endAt'] = $endAt->format('c');

        $this->db->beginTransaction();
        try {
            $appointmentId = $this->appointmentRepository->create((int) $license['licenseId'], $appointmentInput);
            $this->db->commit();
        } catch (\Throwable $e) {
            $this->db->rollback();
            throw $e;
        }

        $token = $this->tokenService->issue($appointmentId, (string) $input['licenseUuid'], $tokenTtl);

        return [
            'appointment' => [
                'appointmentToken' => $token,
                'startAt' => $startAt->format('c'),
                'endAt' => $endAt->format('c'),
                'durationMinutes' => $durationMinutes,
                'serviceType' => ($input['serviceType'] ?? null) ?: null,
                'status' => 'pending',
            ],
        ];
    }

    public function getPublicByToken(string $token): array
    {
        $payload = $this->tokenService->parse($token);
        $appointment = $this->appointmentRepository->findPublicById((int) $payload['appointmentId']);

        if ($appointment === null || $appointment['licenseUuid'] !== $payload['licenseUuid']) {
            throw new ApiException('No se encontró una cita válida.', 'APPOINTMENT_NOT_FOUND', 404);
        }

        return [
            'appointment' => [
                'appointmentToken' => $token,
                'startAt' => $appointment['startAt'],
                'endAt' => $appointment['endAt'],
                'durationMinutes' => (int) ($appointment['durationMinutes'] ?? 0),
                'serviceType' => $appointment['serviceType'] ?? null,
                'status' => (string) $appointment['status'],
                'customer' => [
                    'documentMasked' => Masking::document((string) ($appointment['customerDocument'] ?? '')),
                    'phoneMasked' => $this->maskPhone((string) ($appointment['customerPhone'] ?? '')),
                ],
            ],
        ];
    }

    public function listAdmin(array $filters): array
    {
        return $this->appointmentRepository->listAdmin($filters);
    }

    public function getAdmin(int $appointmentId): array
    {
        if ($appointmentId <= 0) {
            throw new ApiException('appointmentId inválido.', 'VALIDATION_ERROR', 400, ['field' => 'appointmentId']);
        }

        $appointment = $this->appointmentRepository->findAdminById($appointmentId);
        if ($appointment === null) {
            throw new ApiException('Cita no encontrada.', 'APPOINTMENT_NOT_FOUND', 404);
        }

        return $appointment;
    }

    public function updateAdmin(int $appointmentId, array $input): array
    {
        if ($appointmentId <= 0) {
            throw new ApiException('appointmentId inválido.', 'VALIDATION_ERROR', 400, ['field' => 'appointmentId']);
        }

        if (isset($input['status'])) {
            throw new ApiException('status no se actualiza por este endpoint.', 'VALIDATION_ERROR', 400, ['field' => 'status']);
        }

        $this->db->beginTransaction();
        try {
            $updated = $this->appointmentRepository->updateAdmin($appointmentId, $input);
            $this->db->commit();
        } catch (\Throwable $e) {
            $this->db->rollback();
            throw $e;
        }

        return $updated;
    }

    public function transitionAdmin(int $appointmentId, string $status): array
    {
        if ($appointmentId <= 0) {
            throw new ApiException('appointmentId inválido.', 'VALIDATION_ERROR', 400, ['field' => 'appointmentId']);
        }

        $normalizedStatus = StatusMapper::normalizeInternal($status);

        $this->db->beginTransaction();
        try {
            $transitioned = $this->appointmentRepository->transitionStatus($appointmentId, $normalizedStatus);
            $this->db->commit();
        } catch (\Throwable $e) {
            $this->db->rollback();
            throw $e;
        }

        return $transitioned;
    }

    private function maskPhone(string $phone): string
    {
        $digits = preg_replace('/\D+/', '', $phone) ?? '';
        if ($digits === '') {
            return '';
        }

        if (strlen($digits) <= 3) {
            return str_repeat('*', strlen($digits));
        }

        return str_repeat('*', strlen($digits) - 3) . substr($digits, -3);
    }
}
