<?php

declare(strict_types=1);

namespace Booking\Security;

use Booking\Http\ApiException;

final class AdminAuth
{
    public function __construct(private readonly array $config)
    {
    }

    public function assert(string $authorizationHeader): void
    {
        if (!preg_match('/^Bearer\s+(.+)$/i', trim($authorizationHeader), $matches)) {
            throw new ApiException('No autorizado.', 'UNAUTHORIZED', 401);
        }

        $receivedToken = $matches[1];
        $validToken = (string) ($this->config['token'] ?? '');

        if ($validToken === '' || !hash_equals($validToken, $receivedToken)) {
            throw new ApiException('No autorizado.', 'UNAUTHORIZED', 401);
        }
    }
}
