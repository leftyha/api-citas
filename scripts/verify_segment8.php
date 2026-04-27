<?php

declare(strict_types=1);

use Booking\Http\ApiException;
use Booking\Security\AppointmentTokenService;

require dirname(__DIR__) . '/bootstrap.php';

$root = dirname(__DIR__);
$checks = [];
$errors = [];

function check8(callable $fn, string $label, array &$checks, array &$errors): void
{
    try {
        $fn();
        $checks[] = "OK: {$label}";
    } catch (Throwable $e) {
        $errors[] = "FALTA: {$label} ({$e->getMessage()})";
    }
}

function assertAppointmentNotFound(ApiException $e): void
{
    if ($e->getErrorCode() !== 'APPOINTMENT_NOT_FOUND') {
        throw new RuntimeException('Código de error inesperado: ' . $e->getErrorCode());
    }
}

check8(function (): void {
    $service = new AppointmentTokenService([
        'secret' => 'seg8-secret',
        'key_id' => 'kid-seg8',
        'prefix' => 'apt_',
    ]);

    $token = $service->issue(101, 'abc123', 3600);
    $tampered = substr($token, 0, -1) . (substr($token, -1) === 'a' ? 'b' : 'a');

    try {
        $service->parse($tampered);
    } catch (ApiException $e) {
        assertAppointmentNotFound($e);

        return;
    }

    throw new RuntimeException('No falló token manipulado.');
}, 'Segmento 8: token manipulado falla de forma controlada', $checks, $errors);

check8(function (): void {
    $config = [
        'secret' => 'seg8-secret',
        'key_id' => 'kid-seg8',
        'prefix' => 'apt_',
    ];

    $payload = [
        'v' => 1,
        'kid' => 'kid-seg8',
        'appointmentId' => 33,
        'licenseUuid' => 'abc123',
        'iat' => time() - 3600,
        'exp' => time() - 60,
    ];

    $encoded = rtrim(strtr(base64_encode((string) json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)), '+/', '-_'), '=');
    $signature = hash_hmac('sha256', $encoded, $config['secret']);
    $token = $config['prefix'] . $encoded . '.' . $signature;

    $service = new AppointmentTokenService($config);

    try {
        $service->parse($token);
    } catch (ApiException $e) {
        assertAppointmentNotFound($e);

        return;
    }

    throw new RuntimeException('No falló token expirado.');
}, 'Segmento 8: token expirado falla de forma controlada', $checks, $errors);

check8(function () use ($root): void {
    $availability = (string) file_get_contents($root . '/src/Booking/AvailabilityService.php');
    $repository = (string) file_get_contents($root . '/src/Repository/AppointmentRepository.php');

    if (!str_contains($availability, "'slots' => \$this->appointmentRepository->listAvailability(")) {
        throw new RuntimeException('Servicio de disponibilidad no usa contrato de slots.');
    }

    if (!str_contains($repository, "'startAt' => \$cursor->format('Y-m-d\\TH:i:sP')")
        || !str_contains($repository, "'endAt' => \$slotEnd->format('Y-m-d\\TH:i:sP')")) {
        throw new RuntimeException('Slots no tienen formato esperado.');
    }

    if (str_contains($repository, "'customerDocument' =>") && str_contains($repository, 'listAvailability(')) {
        // Se permite en otros métodos. Validamos explícitamente la sección de slots.
        $slotSection = strstr($repository, '$slots[] = [');
        if ($slotSection === false || str_contains(substr($slotSection, 0, 260), 'customer')) {
            throw new RuntimeException('Se detectó posible exposición sensible en slots.');
        }
    }
}, 'Segmento 8: disponibilidad pública no expone citas ni IDs internos', $checks, $errors);

check8(function () use ($root): void {
    $service = (string) file_get_contents($root . '/src/Booking/AppointmentService.php');

    foreach ([
        "'appointmentToken' => \$token",
        "'customer' => [",
        "'documentMasked' => Masking::document",
        "'phoneMasked' => \$this->maskPhone",
    ] as $needle) {
        if (!str_contains($service, $needle)) {
            throw new RuntimeException('Contrato público incompleto: ' . $needle);
        }
    }

    foreach ([
        "'appointmentId' =>",
        "'licenseId' =>",
        "'licenseUuid' =>",
        "'customerDocument' =>",
        "'customerPhone' =>",
    ] as $forbidden) {
        if (str_contains($service, $forbidden)) {
            throw new RuntimeException('Servicio público expone campo sensible: ' . $forbidden);
        }
    }
}, 'Segmento 8: consulta pública entrega contrato mínimo sin IDs internos', $checks, $errors);

check8(function () use ($root): void {
    $config = (string) file_get_contents($root . '/src/Config/config.php');
    $cors = (string) file_get_contents($root . '/src/Http/Cors.php');

    if (!str_contains($config, "getenv('BOOKING_CORS_ALLOWED_ORIGINS')")) {
        throw new RuntimeException('CORS no depende de variable de entorno.');
    }

    if (!str_contains($config, "getenv('BOOKING_TOKEN_SECRET')") || !str_contains($config, "getenv('BOOKING_ADMIN_TOKEN')")) {
        throw new RuntimeException('Secretos no se leen desde entorno.');
    }

    if (!str_contains($cors, 'Access-Control-Allow-Origin')) {
        throw new RuntimeException('No se aplican headers CORS.');
    }
}, 'Segmento 8: revisión final de configuración (CORS y secretos por entorno)', $checks, $errors);

check8(function () use ($root): void {
    $adminFiles = [
        '/admin/appointments_list.php',
        '/admin/appointments_get.php',
        '/admin/appointments_update.php',
        '/admin/appointments_confirm.php',
        '/admin/appointments_cancel.php',
    ];

    foreach ($adminFiles as $file) {
        $content = (string) file_get_contents($root . $file);
        if (!str_contains($content, "adminAuth']->assert")) {
            throw new RuntimeException('Falta auth admin en ' . $file);
        }
    }
}, 'Segmento 8: endpoints admin protegidos con bearer token', $checks, $errors);

foreach ($checks as $line) {
    echo $line . PHP_EOL;
}

if ($errors !== []) {
    fwrite(STDERR, PHP_EOL . "Errores detectados:" . PHP_EOL);
    foreach ($errors as $line) {
        fwrite(STDERR, '- ' . $line . PHP_EOL);
    }

    exit(1);
}

echo PHP_EOL . 'Segmento 8 verificado: TODO OK.' . PHP_EOL;
