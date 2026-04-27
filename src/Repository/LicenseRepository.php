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
            'SELECT TOP 1
                license_id,
                license_uuid,
                license_name,
                logo_url,
                booking_enabled
             FROM booking_licenses
             WHERE license_uuid = :licenseUuid',
            ['licenseUuid' => $licenseUuid]
        );

        if ($rows === []) {
            return null;
        }

        $mapped = LicenseMapper::toPublic((array) $rows[0]);
        if ($mapped['licenseUuid'] === null) {
            $mapped['licenseUuid'] = $licenseUuid;
        }

        return $mapped;
    }

    public function findInternalByUuidOrFail(string $licenseUuid): array
    {
        $rows = $this->db->query(
            'SELECT TOP 1 license_id, license_uuid, booking_enabled
             FROM booking_licenses
             WHERE license_uuid = :licenseUuid',
            ['licenseUuid' => $licenseUuid]
        );

        if ($rows === []) {
            throw new ApiException('Licencia no encontrada.', 'LICENSE_NOT_FOUND', 404);
        }

        $row = (array) $rows[0];
        $bookingEnabled = (bool) ($row['bookingEnabled'] ?? $row['booking_enabled'] ?? false);
        if (!$bookingEnabled) {
            throw new ApiException('La licencia no está habilitada para reservas.', 'LICENSE_INACTIVE', 422);
        }

        $licenseId = (int) ($row['licenseId'] ?? $row['license_id'] ?? 0);
        if ($licenseId <= 0) {
            throw new ApiException('Licencia no encontrada.', 'LICENSE_NOT_FOUND', 404);
        }

        return [
            'licenseId' => $licenseId,
            'licenseUuid' => (string) ($row['licenseUuid'] ?? $row['license_uuid'] ?? $licenseUuid),
        ];
    }
}
