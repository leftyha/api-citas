<?php

declare(strict_types=1);

namespace Booking\Http;

final class Cors
{
    public static function apply(): void
    {
        header('Access-Control-Allow-Origin: *');
        header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Request-Id, X-Channel, Idempotency-Key');
        header('Access-Control-Allow-Methods: GET, POST, PATCH, OPTIONS');

        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'OPTIONS') {
            http_response_code(204);
            exit;
        }
    }
}
