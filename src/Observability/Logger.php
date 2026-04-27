<?php

declare(strict_types=1);

namespace Booking\Observability;

final class Logger
{
    public function __construct(private readonly array $config = [])
    {
    }

    public function info(string $event, array $context = []): void
    {
        $this->write('info', $event, $context);
    }

    public function warning(string $event, array $context = []): void
    {
        $this->write('warning', $event, $context);
    }

    public function error(string $event, array $context = []): void
    {
        $this->write('error', $event, $context);
    }

    private function write(string $level, string $event, array $context): void
    {
        $record = [
            'timestamp' => gmdate('c'),
            'level' => $level,
            'event' => $event,
            'requestId' => $context['requestId'] ?? '',
            'context' => $context,
        ];

        $line = (string) json_encode($record, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($line === '') {
            return;
        }

        $logPath = trim((string) ($this->config['path'] ?? ''));
        if ($logPath === '') {
            error_log($line);

            return;
        }

        $dir = dirname($logPath);
        if (!is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }

        @file_put_contents($logPath, $line . PHP_EOL, FILE_APPEND);
    }
}
