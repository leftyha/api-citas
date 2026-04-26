<?php

declare(strict_types=1);

namespace Booking\Repository;

use Booking\Database\DatabaseClient;
use Booking\Http\ApiException;

final class LicenseRepository
{
    public function __construct(private readonly DatabaseClient $db)
    {
    }

    public function findPublicByUuid(string $licenseUuid): ?array
    {
        if ($licenseUuid === '') {
            return null;
        }

        return [
            'licenseUuid' => $licenseUuid,
            'licenseName' => 'Óptica Demo',
            'logoUrl' => null,
            'bookingEnabled' => true,
        ];
    }

    public function findInternalByUuidOrFail(string $licenseUuid): array
    {
        $license = $this->findPublicByUuid($licenseUuid);

        if ($license === null) {
            throw new ApiException('Licencia no encontrada.', 'LICENSE_NOT_FOUND', 404);
        }

        return ['licenseId' => 1, 'licenseUuid' => $licenseUuid];
    }
}
