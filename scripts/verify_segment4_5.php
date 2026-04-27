<?php

declare(strict_types=1);

use Booking\Http\ApiException;

$container = require dirname(__DIR__) . '/bootstrap.php';

$checks = [];
$errors = [];

function check45(callable $fn, string $label, array &$checks, array &$errors): void
{
    try {
        $fn();
        $checks[] = "OK: {$label}";
    } catch (Throwable $e) {
        $errors[] = "FALTA: {$label} ({$e->getMessage()})";
    }
}

$validInput = [
    'licenseUuid' => 'abc123',
    'customerDocument' => '12345678',
    'customerName' => 'Ana Pérez',
    'customerPhone' => '+584121234567',
    'startAt' => '2026-05-04T10:30:00-04:00',
];

check45(function () use ($container, $validInput): void {
    $result = $container['appointmentService']->create($validInput, 3600);
    $appointment = $result['appointment'] ?? null;

    if (!is_array($appointment)) {
        throw new RuntimeException('No se devolvió bloque appointment en creación.');
    }

    foreach (['appointmentToken', 'startAt', 'endAt', 'durationMinutes', 'status'] as $required) {
        if (!array_key_exists($required, $appointment)) {
            throw new RuntimeException("Falta {$required} en respuesta de creación.");
        }
    }

    if (($appointment['status'] ?? null) !== 'pending') {
        throw new RuntimeException('Estado de creación inesperado.');
    }
}, 'Segmento 4: creación pública devuelve contrato mínimo esperado', $checks, $errors);

check45(function () use ($container, $validInput): void {
    $invalid = $validInput;
    $invalid['startAt'] = '2026-05-04 10:30:00';

    try {
        $container['appointmentService']->create($invalid, 3600);
    } catch (ApiException $e) {
        if ($e->getErrorCode() !== 'VALIDATION_ERROR') {
            throw new RuntimeException('Código inesperado para startAt sin zona horaria.');
        }

        return;
    }

    throw new RuntimeException('No falló con startAt sin zona horaria.');
}, 'Segmento 4: validación fuerte de startAt con zona horaria', $checks, $errors);

check45(function () use ($container, $validInput): void {
    $created = $container['appointmentService']->create($validInput, 3600);
    $token = (string) (($created['appointment']['appointmentToken'] ?? ''));
    $public = $container['appointmentService']->getPublicByToken($token);
    $appointment = $public['appointment'] ?? null;
    $customer = is_array($appointment) ? ($appointment['customer'] ?? null) : null;

    if (!is_array($appointment) || !is_array($customer)) {
        throw new RuntimeException('No se devolvió estructura pública esperada.');
    }

    if (($customer['documentMasked'] ?? '') === '12345678') {
        throw new RuntimeException('documentMasked no está enmascarado.');
    }

    if (($customer['phoneMasked'] ?? '') === '+584121234567') {
        throw new RuntimeException('phoneMasked no está enmascarado.');
    }
}, 'Segmento 5: consulta pública por token devuelve datos mínimos enmascarados', $checks, $errors);

check45(function () use ($container): void {
    try {
        $container['appointmentService']->getPublicByToken('apt_token_invalido');
    } catch (ApiException $e) {
        if ($e->getErrorCode() !== 'APPOINTMENT_NOT_FOUND') {
            throw new RuntimeException('Código inesperado para token inválido.');
        }

        return;
    }

    throw new RuntimeException('No falló con token inválido.');
}, 'Segmento 5: token inválido falla controladamente', $checks, $errors);

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

echo PHP_EOL . 'Segmentos 4 y 5 verificados: TODO OK.' . PHP_EOL;
