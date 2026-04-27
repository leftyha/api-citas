<?php

declare(strict_types=1);

namespace Booking\Http;

final class Request
{
    public static function method(): string
    {
        return strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET'));
    }

    public static function json(): array
    {
        if (self::method() === 'GET') {
            return [];
        }

        $contentType = (string) ($_SERVER['CONTENT_TYPE'] ?? $_SERVER['HTTP_CONTENT_TYPE'] ?? '');
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
        if (strcasecmp($name, 'Authorization') === 0) {
            return $_SERVER['HTTP_AUTHORIZATION'] ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? $default;
        }

        if (strcasecmp($name, 'Content-Type') === 0) {
            return $_SERVER['CONTENT_TYPE'] ?? $_SERVER['HTTP_CONTENT_TYPE'] ?? $default;
        }

        $normalized = 'HTTP_' . strtoupper(str_replace('-', '_', $name));
        return $_SERVER[$normalized] ?? $default;
    }

    public static function ip(): string
    {
        $forwardedFor = trim((string) ($_SERVER['HTTP_X_FORWARDED_FOR'] ?? ''));
        if ($forwardedFor !== '') {
            $ip = trim(explode(',', $forwardedFor)[0]);
            if ($ip !== '') {
                return $ip;
            }
        }

        $remoteAddr = trim((string) ($_SERVER['REMOTE_ADDR'] ?? ''));
        return $remoteAddr !== '' ? $remoteAddr : '0.0.0.0';
    }

    public static function requestId(): string
    {
        $header = trim((string) self::header('X-Request-Id', ''));
        if ($header !== '') {
            return $header;
        }

        try {
            return bin2hex(random_bytes(16));
        } catch (\Throwable) {
            return uniqid('req_', true);
        }
    }
}
