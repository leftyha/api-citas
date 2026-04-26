<?php

declare(strict_types=1);

namespace Booking\Http;

final class JsonResponse
{
    public static function success(array $data = [], string $message = 'Operación realizada correctamente.', int $status = 200): void
    {
        self::send([
            'ok' => true,
            'message' => $message,
            'data' => $data,
        ], $status);
    }

    public static function error(string $code, string $message, int $status, array $errors = []): void
    {
        self::send([
            'ok' => false,
            'code' => $code,
            'message' => $message,
            'errors' => $errors,
        ], $status);
    }

    public static function send(array $payload, int $status = 200): void
    {
        http_response_code($status);
        header('Content-Type: application/json');
        echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }
}
