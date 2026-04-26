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
        $license = $this->repository->findPublicByUuid($licenseUuid);

        if ($license === null) {
            throw new ApiException('Licencia no encontrada.', 'LICENSE_NOT_FOUND', 404);
        }

        return ['license' => $license];
    }
}
