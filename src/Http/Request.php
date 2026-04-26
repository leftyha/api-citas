<?php

declare(strict_types=1);

namespace Booking\Http;

final class Request
{
    public static function json(): array
    {
        $contentType = $_SERVER['CONTENT_TYPE'] ?? '';
        if ($contentType !== '' && stripos($contentType, 'application/json') === false) {
            throw new ApiException('Se requiere contenido JSON.', 'UNSUPPORTED_MEDIA_TYPE', 415);
        }

        $raw = file_get_contents('php://input') ?: '';
        if ($raw === '') {
            return [];
        }

        $payload = json_decode($raw, true);
        if (!is_array($payload)) {
            throw new ApiException('JSON inválido.', 'VALIDATION_ERROR', 400);
        }

        return $payload;
    }

    public static function query(string $key, mixed $default = null): mixed
    {
        return $_GET[$key] ?? $default;
    }

    public static function header(string $name, ?string $default = null): ?string
    {
        $normalized = 'HTTP_' . strtoupper(str_replace('-', '_', $name));
        return $_SERVER[$normalized] ?? $default;
    }
}
