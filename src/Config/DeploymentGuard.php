<?php

declare(strict_types=1);

namespace Booking\Config;

final class DeploymentGuard
{
    public static function assertSecure(array $config): void
    {
        $strict = (bool) ($config['app']['strict_deploy'] ?? true);
        if (!$strict) {
            return;
        }

        $defaults = [
            'security.appointment_token.secret' => 'change-me-in-production',
            'security.admin.token' => 'change-me-in-production',
        ];

        foreach ($defaults as $path => $forbidden) {
            $value = self::read($config, $path);
            if (!is_string($value) || trim($value) === '' || hash_equals($forbidden, $value)) {
                throw new \RuntimeException(sprintf('Valor inseguro para configuración crítica: %s', $path));
            }
        }

        $required = [
            'database.host',
            'database.name',
            'database.user',
            'database.password',
        ];

        foreach ($required as $path) {
            $value = self::read($config, $path);
            if (!is_string($value) || trim($value) === '') {
                throw new \RuntimeException(sprintf('Falta configuración obligatoria: %s', $path));
            }
        }
    }

    private static function read(array $config, string $path): mixed
    {
        $cursor = $config;
        foreach (explode('.', $path) as $segment) {
            if (!is_array($cursor) || !array_key_exists($segment, $cursor)) {
                return null;
            }

            $cursor = $cursor[$segment];
        }

        return $cursor;
    }
}
