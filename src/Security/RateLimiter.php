<?php

declare(strict_types=1);

namespace Booking\Security;

use Booking\Http\ApiException;

final class RateLimiter
{
    public function __construct(private readonly array $config)
    {
    }

    public function allow(string $key): bool
    {
        $maxAttempts = max(1, (int) ($this->config['max_attempts'] ?? 60));
        $windowSeconds = max(1, (int) ($this->config['window_seconds'] ?? 60));
        $storageFile = sys_get_temp_dir() . '/booking_rate_limit_' . sha1($key) . '.json';
        $now = time();
        $windowStart = $now - $windowSeconds;

        $attempts = [];
        if (is_file($storageFile)) {
            $content = file_get_contents($storageFile);
            $decoded = json_decode((string) $content, true);
            if (is_array($decoded)) {
                $attempts = array_values(array_filter($decoded, static fn ($item): bool => is_int($item) && $item >= $windowStart));
            }
        }

        if (count($attempts) >= $maxAttempts) {
            return false;
        }

        $attempts[] = $now;
        file_put_contents($storageFile, json_encode($attempts));
        return true;
    }

    public function assertAllowed(string $key): void
    {
        if (!$this->allow($key)) {
            throw new ApiException(
                'Demasiadas solicitudes. Intenta nuevamente en unos minutos.',
                'RATE_LIMIT_EXCEEDED',
                429
            );
        }
    }
}
