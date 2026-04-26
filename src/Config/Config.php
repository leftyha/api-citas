<?php

declare(strict_types=1);

namespace Booking\Config;

final class Config
{
    public static function load(string $configFile): array
    {
        if (!is_file($configFile)) {
            throw new \RuntimeException('Configuration file not found.');
        }

        $config = require $configFile;

        if (!is_array($config)) {
            throw new \RuntimeException('Configuration file must return an array.');
        }

        return $config;
    }
}
