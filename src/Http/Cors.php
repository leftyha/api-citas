<?php

declare(strict_types=1);

namespace Booking\Http;

final class Cors
{
    public static function apply(array $config = []): void
    {
        $origin = trim((string) ($_SERVER['HTTP_ORIGIN'] ?? ''));
        $allowedOrigins = $config['allowed_origins'] ?? ['*'];
        if (!is_array($allowedOrigins) || $allowedOrigins === []) {
            $allowedOrigins = ['*'];
        }

        $allowOrigin = '*';
        if ($allowedOrigins !== ['*']) {
            if ($origin !== '' && in_array($origin, $allowedOrigins, true)) {
                $allowOrigin = $origin;
            } else {
                $allowOrigin = (string) $allowedOrigins[0];
            }
            header('Vary: Origin');
        }

        header('Access-Control-Allow-Origin: ' . $allowOrigin);
        header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Request-Id, X-Channel, Idempotency-Key');
        header('Access-Control-Allow-Methods: GET, POST, PATCH, OPTIONS');
        header('Access-Control-Max-Age: 600');

        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'OPTIONS') {
            http_response_code(204);
            exit;
        }
    }
}
