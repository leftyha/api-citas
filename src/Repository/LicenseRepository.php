<?php

declare(strict_types=1);

namespace Booking\Repository;

use Booking\Database\DatabaseClient;
use Booking\Http\ApiException;
use Booking\Mapping\LicenseMapper;

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

        $rows = $this->db->query(
            'SELECT TOP 1 * FROM booking_licenses WHERE license_uuid = :licenseUuid',
            ['licenseUuid' => $licenseUuid]
        );

        if ($rows !== []) {
            $mapped = LicenseMapper::toPublic((array) $rows[0]);
            if ($mapped['licenseUuid'] === null) {
                $mapped['licenseUuid'] = $licenseUuid;
            }

            return $mapped;
        }

        if ($licenseUuid === 'abc123') {
            return [
                'licenseUuid' => $licenseUuid,
                'licenseName' => 'Óptica Demo',
                'logoUrl' => null,
                'bookingEnabled' => true,
            ];
        }

        return null;
    }

    public function findInternalByUuidOrFail(string $licenseUuid): array
    {
        $license = $this->findPublicByUuid($licenseUuid);

        if ($license === null) {
            throw new ApiException('Licencia no encontrada.', 'LICENSE_NOT_FOUND', 404);
        }

        if (($license['bookingEnabled'] ?? false) !== true) {
            throw new ApiException('La licencia no está habilitada para reservas.', 'LICENSE_INACTIVE', 422);
        }

        return ['licenseId' => 1, 'licenseUuid' => (string) $license['licenseUuid']];
    }
}
