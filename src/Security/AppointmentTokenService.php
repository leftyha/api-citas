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
        if ($appointmentId <= 0 || $licenseUuid === '') {
            throw new ApiException('No se pudo generar token de cita.', 'INTERNAL_ERROR', 500);
        }

        $ttlSeconds = max(60, $ttlSeconds);
        $secret = (string) ($this->config['secret'] ?? '');
        if ($secret === '') {
            throw new ApiException('No se pudo generar token de cita.', 'INTERNAL_ERROR', 500);
        }

        $now = time();
        $payload = [
            'v' => 1,
            'kid' => $this->config['key_id'],
            'appointmentId' => $appointmentId,
            'licenseUuid' => $licenseUuid,
            'iat' => $now,
            'exp' => $now + $ttlSeconds,
        ];

        $encoded = $this->base64UrlEncode((string) json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
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

        $payload = json_decode($this->base64UrlDecode($encoded), true);

        if (!$this->isValidPayload($payload)) {
            throw new ApiException('No se encontró una cita válida.', 'APPOINTMENT_NOT_FOUND', 404);
        }

        return $payload;
    }

    private function isValidPayload(mixed $payload): bool
    {
        if (!is_array($payload)) {
            return false;
        }

        $expectedKid = (string) ($this->config['key_id'] ?? '');
        if (($payload['v'] ?? null) !== 1 || (string) ($payload['kid'] ?? '') !== $expectedKid) {
            return false;
        }

        if (!is_int($payload['appointmentId'] ?? null) || (int) $payload['appointmentId'] <= 0) {
            return false;
        }

        if (!is_string($payload['licenseUuid'] ?? null) || $payload['licenseUuid'] === '') {
            return false;
        }

        $iat = (int) ($payload['iat'] ?? 0);
        $exp = (int) ($payload['exp'] ?? 0);
        $now = time();

        if ($iat <= 0 || $exp <= 0 || $exp < $now || $exp <= $iat) {
            return false;
        }

        if ($iat > $now + 300) {
            return false;
        }

        return true;
    }

    private function base64UrlEncode(string $value): string
    {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }

    private function base64UrlDecode(string $value): string
    {
        $remainder = strlen($value) % 4;
        if ($remainder > 0) {
            $value .= str_repeat('=', 4 - $remainder);
        }

        return base64_decode(strtr($value, '-_', '+/'), true) ?: '';
    }
}
