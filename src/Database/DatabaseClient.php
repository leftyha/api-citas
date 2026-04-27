<?php

declare(strict_types=1);

namespace Booking\Database;

final class DatabaseClient
{
    private bool $inTransaction = false;

    public function __construct(private readonly array $config)
    {
    }

    public function query(string $sql, array $params = []): array
    {
        if (!function_exists('ejecutarQueryAzureSQLServerV2')) {
            return [];
        }

        try {
            return (array) ejecutarQueryAzureSQLServerV2($sql, $params);
        } catch (\Throwable $e) {
            throw new \RuntimeException('Database query failed.', 0, $e);
        }
    }

    public function beginTransaction(): void
    {
        if ($this->inTransaction) {
            return;
        }

        $this->query('SET XACT_ABORT ON; BEGIN TRANSACTION;');
        $this->inTransaction = true;
    }

    public function commit(): void
    {
        if (!$this->inTransaction) {
            return;
        }

        $this->query('COMMIT;');
        $this->inTransaction = false;
    }

    public function rollback(): void
    {
        if (!$this->inTransaction) {
            return;
        }

        $this->query('ROLLBACK;');
        $this->inTransaction = false;
    }
}
