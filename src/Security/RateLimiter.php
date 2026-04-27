<?php

declare(strict_types=1);

namespace Booking\Security;

use Booking\Database\DatabaseClient;
use Booking\Http\ApiException;

final class RateLimiter
{
    public function __construct(
        private readonly array $config,
        private readonly ?DatabaseClient $db = null
    )
    {
    }

    public function allow(string $key): bool
    {
        $backend = strtolower((string) ($this->config['backend'] ?? 'database'));
        if ($backend === 'database' && $this->db instanceof DatabaseClient) {
            return $this->allowDatabase($key);
        }

        return $this->allowFile($key);
    }

    private function allowFile(string $key): bool
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

    private function allowDatabase(string $key): bool
    {
        $maxAttempts = max(1, (int) ($this->config['max_attempts'] ?? 60));
        $windowSeconds = max(1, (int) ($this->config['window_seconds'] ?? 60));
        $bucket = (int) floor(time() / $windowSeconds);

        $rows = $this->db?->query(
            'IF OBJECT_ID(\'dbo.booking_rate_limit\', \'U\') IS NULL
                BEGIN
                    CREATE TABLE dbo.booking_rate_limit (
                        key_hash VARCHAR(64) NOT NULL,
                        bucket BIGINT NOT NULL,
                        attempts INT NOT NULL,
                        updated_at DATETIME2 NOT NULL DEFAULT SYSUTCDATETIME(),
                        CONSTRAINT PK_booking_rate_limit PRIMARY KEY (key_hash, bucket)
                    );
                END;
             MERGE dbo.booking_rate_limit AS target
             USING (SELECT :keyHash AS key_hash, :bucket AS bucket) AS src
             ON (target.key_hash = src.key_hash AND target.bucket = src.bucket)
             WHEN MATCHED THEN
                 UPDATE SET attempts = target.attempts + 1, updated_at = SYSUTCDATETIME()
             WHEN NOT MATCHED THEN
                 INSERT (key_hash, bucket, attempts, updated_at)
                 VALUES (src.key_hash, src.bucket, 1, SYSUTCDATETIME());
             SELECT attempts
             FROM dbo.booking_rate_limit
             WHERE key_hash = :keyHash AND bucket = :bucket;',
            [
                'keyHash' => sha1($key),
                'bucket' => $bucket,
            ]
        ) ?? [];

        if ($rows === []) {
            return true;
        }

        $row = (array) $rows[0];
        $attempts = (int) ($row['attempts'] ?? 0);

        return $attempts <= $maxAttempts;
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
