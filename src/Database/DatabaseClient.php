<?php

declare(strict_types=1);

namespace Booking\Database;

final class DatabaseClient
{
    public function __construct(private readonly array $config)
    {
    }

    public function query(string $sql, array $params = []): array
    {
        if (function_exists('ejecutarQueryAzureSQLServerV2')) {
            return (array) ejecutarQueryAzureSQLServerV2($sql, $params);
        }

        return [];
    }

    public function beginTransaction(): void
    {
        $this->query('BEGIN TRANSACTION');
    }

    public function commit(): void
    {
        $this->query('COMMIT');
    }

    public function rollback(): void
    {
        $this->query('ROLLBACK');
    }
}
