<?php

declare(strict_types=1);

use Booking\Http\ApiException;

$container = require dirname(__DIR__) . '/bootstrap.php';

$checks = [];
$errors = [];

function check(callable $fn, string $label, array &$checks, array &$errors): void
{
    try {
        $fn();
        $checks[] = "OK: {$label}";
    } catch (Throwable $e) {
        $errors[] = "FALTA: {$label} ({$e->getMessage()})";
    }
}

check(function () use ($container): void {
    $result = $container['licenseService']->resolve('abc123');
    if (!isset($result['license']) || !is_array($result['license'])) {
        throw new RuntimeException('No se devolvió bloque license.');
    }

    $allowedKeys = ['licenseUuid', 'licenseName', 'logoUrl', 'bookingEnabled'];
    $diff = array_diff(array_keys($result['license']), $allowedKeys);
    if ($diff !== []) {
        throw new RuntimeException('Se están exponiendo campos no públicos.');
    }
}, 'Segmento 2: resolución pública devuelve solo campos públicos', $checks, $errors);

check(function () use ($container): void {
    try {
        $container['licenseService']->resolve('bad uuid');
    } catch (ApiException $e) {
        if ($e->getErrorCode() !== 'VALIDATION_ERROR') {
            throw new RuntimeException('Código de error inesperado para uuid inválido.');
        }

        return;
    }

    throw new RuntimeException('No falló con uuid inválido.');
}, 'Segmento 2: validación de licenseUuid inválido', $checks, $errors);

check(function () use ($container): void {
    try {
        $container['licenseService']->resolve('unknown-license-001');
    } catch (ApiException $e) {
        if ($e->getErrorCode() !== 'LICENSE_NOT_FOUND') {
            throw new RuntimeException('Código de error inesperado para licencia inexistente.');
        }

        return;
    }

    throw new RuntimeException('No falló con licencia inexistente.');
}, 'Segmento 2: licencia inexistente devuelve error controlado', $checks, $errors);

$cursor = new DateTimeImmutable('tomorrow');
while ((int) $cursor->format('N') === 7) {
    $cursor = $cursor->modify('+1 day');
}
$nextBusinessDate = $cursor->format('Y-m-d');

check(function () use ($container, $nextBusinessDate): void {
    $result = $container['availabilityService']->getSlots('abc123', $nextBusinessDate, 30);

    $allowedRootKeys = ['date', 'durationMinutes', 'slots'];
    $rootDiff = array_diff(array_keys($result), $allowedRootKeys);
    if ($rootDiff !== []) {
        throw new RuntimeException('Se están exponiendo campos no públicos en disponibilidad.');
    }

    if (!isset($result['slots']) || !is_array($result['slots'])) {
        throw new RuntimeException('No se devolvió el arreglo slots.');
    }

    foreach ($result['slots'] as $slot) {
        $allowedKeys = ['startAt', 'endAt'];
        $diff = array_diff(array_keys($slot), $allowedKeys);
        if ($diff !== []) {
            throw new RuntimeException('Se están exponiendo datos sensibles en slots.');
        }
    }
}, 'Segmento 3: disponibilidad pública sin datos sensibles', $checks, $errors);

check(function () use ($container): void {
    try {
        $container['availabilityService']->getSlots('abc123', '2026-02-30', 30);
    } catch (ApiException $e) {
        if ($e->getErrorCode() !== 'VALIDATION_ERROR') {
            throw new RuntimeException('Código de error inesperado para fecha inválida.');
        }

        return;
    }

    throw new RuntimeException('No falló con fecha inválida.');
}, 'Segmento 3: validación de fecha en disponibilidad', $checks, $errors);

check(function () use ($container, $nextBusinessDate): void {
    try {
        $container['availabilityService']->getSlots('abc123', $nextBusinessDate, 25);
    } catch (ApiException $e) {
        if ($e->getErrorCode() !== 'VALIDATION_ERROR') {
            throw new RuntimeException('Código de error inesperado para duración inválida.');
        }

        return;
    }

    throw new RuntimeException('No falló con duración inválida.');
}, 'Segmento 3: validación de durationMinutes', $checks, $errors);

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

echo PHP_EOL . 'Segmentos 2 y 3 verificados: TODO OK.' . PHP_EOL;
