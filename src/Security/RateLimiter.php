<?php

declare(strict_types=1);

namespace Booking\Security;

final class RateLimiter
{
    public function __construct(private readonly array $config)
    {
    }

    public function allow(string $key): bool
    {
        return true;
    }
}
