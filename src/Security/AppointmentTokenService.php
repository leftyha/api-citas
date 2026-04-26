<?php

declare(strict_types=1);

namespace Booking\Security;

use Booking\Http\ApiException;

final class AppointmentTokenService
{
    public function __construct(private readonly array $config)
    {
    }

    public function issue(int $appointmentId, string $licenseUuid, int $ttlSeconds): string
    {
        $payload = [
            'v' => 1,
            'kid' => $this->config['key_id'],
            'appointmentId' => $appointmentId,
            'licenseUuid' => $licenseUuid,
            'iat' => time(),
            'exp' => time() + $ttlSeconds,
        ];

        $secret = (string) ($this->config['secret'] ?? '');
        $encoded = base64_encode(json_encode($payload, JSON_UNESCAPED_UNICODE));
        $signature = hash_hmac('sha256', $encoded, $secret);

        return ($this->config['prefix'] ?? 'apt_') . $encoded . '.' . $signature;
    }

    public function parse(string $token): array
    {
        $prefix = (string) ($this->config['prefix'] ?? 'apt_');

        if (!str_starts_with($token, $prefix)) {
            throw new ApiException('No se encontró una cita válida.', 'APPOINTMENT_NOT_FOUND', 404);
        }

        $raw = substr($token, strlen($prefix));
        [$encoded, $signature] = array_pad(explode('.', $raw, 2), 2, null);

        if (!$encoded || !$signature) {
            throw new ApiException('No se encontró una cita válida.', 'APPOINTMENT_NOT_FOUND', 404);
        }

        $secret = (string) ($this->config['secret'] ?? '');
        $expected = hash_hmac('sha256', $encoded, $secret);

        if (!hash_equals($expected, $signature)) {
            throw new ApiException('No se encontró una cita válida.', 'APPOINTMENT_NOT_FOUND', 404);
        }

        $payload = json_decode(base64_decode($encoded, true) ?: '', true);

        if (!is_array($payload) || ($payload['exp'] ?? 0) < time()) {
            throw new ApiException('No se encontró una cita válida.', 'APPOINTMENT_NOT_FOUND', 404);
        }

        return $payload;
    }
}
