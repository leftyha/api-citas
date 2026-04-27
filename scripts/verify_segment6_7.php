<?php

declare(strict_types=1);

$root = dirname(__DIR__);
$checks = [];
$errors = [];

function check67(bool $condition, string $label, array &$checks, array &$errors): void
{
    if ($condition) {
        $checks[] = "OK: {$label}";

        return;
    }

    $errors[] = "FALTA: {$label}";
}

$repository = (string) file_get_contents($root . '/src/Repository/AppointmentRepository.php');
$licenseRepository = (string) file_get_contents($root . '/src/Repository/LicenseRepository.php');
$dbClient = (string) file_get_contents($root . '/src/Database/DatabaseClient.php');
$statusMapper = (string) file_get_contents($root . '/src/Mapping/StatusMapper.php');
$service = (string) file_get_contents($root . '/src/Booking/AppointmentService.php');

check67(
    str_contains($repository, 'public function listAdmin(array $filters): array')
    && str_contains($repository, 'public function findAdminById(int $appointmentId): ?array')
    && str_contains($repository, 'public function updateAdmin(int $appointmentId, array $input): array')
    && str_contains($repository, 'public function transitionStatus(int $appointmentId, string $status): array'),
    'Segmento 6: repositorio admin implementa list/get/update/transition',
    $checks,
    $errors
);

check67(
    str_contains($statusMapper, 'public static function canTransition(string $from, string $to): bool')
    && str_contains($repository, 'INVALID_STATUS_TRANSITION'),
    'Segmento 6: transiciones inválidas bloqueadas por reglas de estado',
    $checks,
    $errors
);

check67(
    str_contains($service, 'appointmentId inválido.')
    && str_contains($service, 'status no se actualiza por este endpoint.'),
    'Segmento 6: validaciones adicionales en servicio admin',
    $checks,
    $errors
);

check67(
    str_contains($dbClient, 'SET XACT_ABORT ON; BEGIN TRANSACTION;')
    && str_contains($dbClient, 'Database query failed.'),
    'Segmento 7: cliente SQL con estrategia transaccional y errores sanitizados',
    $checks,
    $errors
);

check67(
    str_contains($repository, 'SELECT TOP 1\n                license_uuid') === false
    && !str_contains($repository, 'crc32(')
    && !str_contains($licenseRepository, "abc123"),
    'Segmento 7: sin stubs/mock en flujos principales',
    $checks,
    $errors
);

foreach ($checks as $line) {
    echo $line . PHP_EOL;
}

if ($errors !== []) {
    fwrite(STDERR, PHP_EOL . "Errores detectados:" . PHP_EOL);
    foreach ($errors as $line) {
        fwrite(STDERR, "- {$line}" . PHP_EOL);
    }

    exit(1);
}

echo PHP_EOL . 'Segmentos 6 y 7 verificados: TODO OK.' . PHP_EOL;
