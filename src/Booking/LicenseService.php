<?php

declare(strict_types=1);

namespace Booking\Booking;

use Booking\Http\ApiException;
use Booking\Repository\LicenseRepository;

final class LicenseService
{
    public function __construct(private readonly LicenseRepository $repository)
    {
    }

    public function resolve(string $licenseUuid): array
    {
        $normalizedUuid = trim($licenseUuid);
        if ($normalizedUuid === '' || !preg_match('/^[A-Za-z0-9][A-Za-z0-9_-]{2,119}$/', $normalizedUuid)) {
            throw new ApiException('licenseUuid inválido.', 'VALIDATION_ERROR', 400, ['field' => 'licenseUuid']);
        }

        $license = $this->repository->findPublicByUuid($normalizedUuid);

        if ($license === null) {
            throw new ApiException('Licencia no encontrada.', 'LICENSE_NOT_FOUND', 404);
        }

        if (($license['bookingEnabled'] ?? false) !== true) {
            throw new ApiException('La licencia no está habilitada para reservas.', 'LICENSE_INACTIVE', 422);
        }

        return ['license' => $license];
    }
}
