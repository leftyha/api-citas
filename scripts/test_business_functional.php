<?php

declare(strict_types=1);

use Booking\Booking\AppointmentService;
use Booking\Booking\AppointmentValidator;
use Booking\Booking\AvailabilityService;
use Booking\Booking\LicenseService;
use Booking\Database\DatabaseClient;
use Booking\Http\ApiException;
use Booking\Repository\AppointmentRepository;
use Booking\Repository\LicenseRepository;
use Booking\Security\AppointmentTokenService;

require dirname(__DIR__) . '/bootstrap.php';

final class ScenarioDb
{
    /** @var array<int, array{contains:string,result:array}> */
    private array $expectations = [];

    /** @var array<int, array{sql:string,params:array}> */
    private array $calls = [];

    public function push(string $contains, array $result): void
    {
        $this->expectations[] = ['contains' => $contains, 'result' => $result];
    }

    public function handler(string $sql, array $params): array
    {
        $this->calls[] = ['sql' => $sql, 'params' => $params];

        if ($this->expectations === []) {
            throw new RuntimeException('SQL inesperado sin expectativas: ' . $sql);
        }

        $expected = array_shift($this->expectations);
        if (!str_contains($sql, $expected['contains'])) {
            throw new RuntimeException('SQL no esperado. Se esperaba: ' . $expected['contains'] . ' | Recibido: ' . $sql);
        }

        return $expected['result'];
    }

    public function assertDone(): void
    {
        if ($this->expectations !== []) {
            throw new RuntimeException('Quedaron expectativas SQL pendientes: ' . count($this->expectations));
        }
    }

    /** @return array<int, array{sql:string,params:array}> */
    public function calls(): array
    {
        return $this->calls;
    }
}

$GLOBALS['__booking_test_db_handler'] = static fn (string $sql, array $params): array => [];

if (!function_exists('ejecutarQueryAzureSQLServerV2')) {
    function ejecutarQueryAzureSQLServerV2(string $sql, array $params = []): array
    {
        $handler = $GLOBALS['__booking_test_db_handler'] ?? null;
        if (!is_callable($handler)) {
            throw new RuntimeException('DB handler de pruebas no configurado.');
        }

        return $handler($sql, $params);
    }
}

/** @var array<int, string> $ok */
$ok = [];
/** @var array<int, string> $fail */
$fail = [];

$run = static function (string $group, string $label, callable $fn) use (&$ok, &$fail): void {
    try {
        $fn();
        $ok[] = "OK [{$group}] {$label}";
    } catch (Throwable $e) {
        $fail[] = "FAIL [{$group}] {$label}: {$e->getMessage()}";
    }
};

$makeServices = static function (ScenarioDb $scenario): array {
    $GLOBALS['__booking_test_db_handler'] = static fn (string $sql, array $params): array => $scenario->handler($sql, $params);

    $db = new DatabaseClient(['driver' => 'sqlsrv']);
    $licenseRepository = new LicenseRepository($db);
    $appointmentRepository = new AppointmentRepository($db);
    $validator = new AppointmentValidator(['default_duration_minutes' => 30]);
    $tokenService = new AppointmentTokenService([
        'secret' => 'test-secret',
        'key_id' => 'test-kid',
        'prefix' => 'apt_',
    ]);

    return [
        new LicenseService($licenseRepository),
        new AvailabilityService($appointmentRepository, $licenseRepository, $validator),
        new AppointmentService($appointmentRepository, $licenseRepository, $tokenService, $validator, $db),
        $scenario,
    ];
};

$run('Licencias', 'resuelve licencia activa con contrato público', function () use ($makeServices): void {
    $scenario = new ScenarioDb();
    $scenario->push('FROM booking_licenses', [[
        'license_id' => 10,
        'license_uuid' => 'abc123',
        'license_name' => 'Clínica Norte',
        'logo_url' => 'https://cdn/logo.png',
        'booking_enabled' => true,
    ]]);

    [$licenseService] = $makeServices($scenario);
    $result = $licenseService->resolve('abc123');
    $license = $result['license'] ?? null;

    if (!is_array($license) || ($license['bookingEnabled'] ?? null) !== true) {
        throw new RuntimeException('Contrato de licencia inválido.');
    }

    $scenario->assertDone();
});

$run('Licencias', 'valida formato de UUID', function () use ($makeServices): void {
    [$licenseService] = $makeServices(new ScenarioDb());

    try {
        $licenseService->resolve('bad uuid');
    } catch (ApiException $e) {
        if ($e->getErrorCode() === 'VALIDATION_ERROR') {
            return;
        }
    }

    throw new RuntimeException('No devolvió VALIDATION_ERROR.');
});

$run('Disponibilidad', 'bloquea licencia inactiva', function () use ($makeServices): void {
    $scenario = new ScenarioDb();
    $scenario->push('SELECT TOP 1 license_id', [[
        'license_id' => 10,
        'license_uuid' => 'abc123',
        'booking_enabled' => false,
    ]]);

    [, $availabilityService] = $makeServices($scenario);

    try {
        $availabilityService->getSlots('abc123', (new DateTimeImmutable('tomorrow'))->format('Y-m-d'), 30);
    } catch (ApiException $e) {
        if ($e->getErrorCode() === 'LICENSE_INACTIVE') {
            return;
        }
    }

    throw new RuntimeException('No devolvió LICENSE_INACTIVE.');
});

$run('Disponibilidad', 'devuelve slots sin datos sensibles', function () use ($makeServices): void {
    $targetDate = new DateTimeImmutable('next monday');
    $scenario = new ScenarioDb();
    $scenario->push('SELECT TOP 1 license_id', [[
        'license_id' => 10,
        'license_uuid' => 'abc123',
        'booking_enabled' => true,
    ]]);
    $scenario->push('FROM booking_appointments a', [[
        'start_at' => $targetDate->format('Y-m-d') . 'T10:00:00-04:00',
        'end_at' => $targetDate->format('Y-m-d') . 'T10:30:00-04:00',
        'status' => 'confirmed',
    ]]);

    [, $availabilityService] = $makeServices($scenario);
    $result = $availabilityService->getSlots('abc123', $targetDate->format('Y-m-d'), 30);

    foreach ($result['slots'] as $slot) {
        if (array_diff(array_keys($slot), ['startAt', 'endAt']) !== []) {
            throw new RuntimeException('Slot expone campos sensibles.');
        }
    }

    $scenario->assertDone();
});

$run('Citas públicas', 'crea cita y genera token con transacción', function () use ($makeServices): void {
    $startAt = (new DateTimeImmutable('tomorrow 10:00:00'))->format('Y-m-d\\TH:i:sP');

    $scenario = new ScenarioDb();
    $scenario->push('SELECT TOP 1 license_id', [[
        'license_id' => 10,
        'license_uuid' => 'abc123',
        'booking_enabled' => true,
    ]]);
    $scenario->push('BEGIN TRANSACTION', []);
    $scenario->push('WITH (UPDLOCK, HOLDLOCK)', []);
    $scenario->push('OUTPUT INSERTED.appointment_id', [['appointment_id' => 77]]);
    $scenario->push('COMMIT;', []);

    [, , $appointmentService, $dbScenario] = $makeServices($scenario);

    $result = $appointmentService->create([
        'licenseUuid' => 'abc123',
        'customerDocument' => 'V12345678',
        'customerName' => 'Paciente Demo',
        'customerPhone' => '+584121234567',
        'customerEmail' => 'demo@example.com',
        'startAt' => $startAt,
    ], 3600);

    $appointment = $result['appointment'] ?? [];
    if (!is_array($appointment) || !str_starts_with((string) ($appointment['appointmentToken'] ?? ''), 'apt_')) {
        throw new RuntimeException('No se generó token público.');
    }

    $calls = $dbScenario->calls();
    $sqlChain = implode(' | ', array_map(static fn (array $c): string => $c['sql'], $calls));
    if (!str_contains($sqlChain, 'BEGIN TRANSACTION') || !str_contains($sqlChain, 'COMMIT;')) {
        throw new RuntimeException('No se detectó flujo transaccional completo.');
    }

    $scenario->assertDone();
});

$run('Citas públicas', 'rechaza conflicto de agenda', function () use ($makeServices): void {
    $startAt = (new DateTimeImmutable('tomorrow 11:00:00'))->format('Y-m-d\\TH:i:sP');

    $scenario = new ScenarioDb();
    $scenario->push('SELECT TOP 1 license_id', [[
        'license_id' => 10,
        'license_uuid' => 'abc123',
        'booking_enabled' => true,
    ]]);
    $scenario->push('BEGIN TRANSACTION', []);
    $scenario->push('WITH (UPDLOCK, HOLDLOCK)', [[
        'appointment_id' => 999,
        'customer_document' => 'OTRODOC',
    ]]);
    $scenario->push('ROLLBACK;', []);

    [, , $appointmentService] = $makeServices($scenario);

    try {
        $appointmentService->create([
            'licenseUuid' => 'abc123',
            'customerDocument' => 'V12345678',
            'customerName' => 'Paciente Demo',
            'customerPhone' => '+584121234567',
            'startAt' => $startAt,
        ], 3600);
    } catch (ApiException $e) {
        if ($e->getErrorCode() === 'SLOT_CONFLICT') {
            return;
        }
    }

    throw new RuntimeException('No devolvió SLOT_CONFLICT.');
});

$run('Consulta pública', 'lee cita por token con datos enmascarados', function () use ($makeServices): void {
    $tokenService = new AppointmentTokenService(['secret' => 'test-secret', 'key_id' => 'test-kid', 'prefix' => 'apt_']);
    $token = $tokenService->issue(77, 'abc123', 3600);

    $scenario = new ScenarioDb();
    $scenario->push('WHERE a.appointment_id = :appointmentId', [[
        'appointment_id' => 77,
        'license_uuid' => 'abc123',
        'status' => 'pending',
        'start_at' => '2030-05-01T10:00:00-04:00',
        'end_at' => '2030-05-01T10:30:00-04:00',
        'duration_minutes' => 30,
        'service_type' => 'consulta',
        'customer_document' => 'V12345678',
        'customer_phone' => '+584121234567',
    ]]);

    [, , $appointmentService] = $makeServices($scenario);
    $result = $appointmentService->getPublicByToken($token);

    $customer = $result['appointment']['customer'] ?? [];
    if (($customer['documentMasked'] ?? '') === 'V12345678' || ($customer['phoneMasked'] ?? '') === '+584121234567') {
        throw new RuntimeException('No se aplicó masking al cliente.');
    }

    $scenario->assertDone();
});

$run('Consulta pública', 'falla con token manipulado', function (): void {
    $service = new AppointmentTokenService(['secret' => 'test-secret', 'key_id' => 'test-kid', 'prefix' => 'apt_']);
    $token = $service->issue(11, 'abc123', 3600);
    $tampered = substr($token, 0, -1) . (substr($token, -1) === 'a' ? 'b' : 'a');

    try {
        $service->parse($tampered);
    } catch (ApiException $e) {
        if ($e->getErrorCode() === 'APPOINTMENT_NOT_FOUND') {
            return;
        }
    }

    throw new RuntimeException('No devolvió APPOINTMENT_NOT_FOUND.');
});

$run('Admin', 'obtiene cita admin validando appointmentId', function () use ($makeServices): void {
    $scenario = new ScenarioDb();
    [, , $appointmentService] = $makeServices($scenario);

    try {
        $appointmentService->getAdmin(0);
    } catch (ApiException $e) {
        if ($e->getErrorCode() === 'VALIDATION_ERROR') {
            return;
        }
    }

    throw new RuntimeException('No devolvió VALIDATION_ERROR para appointmentId inválido.');
});

$run('Admin', 'bloquea transición inválida de estado', function () use ($makeServices): void {
    $scenario = new ScenarioDb();
    $scenario->push('BEGIN TRANSACTION', []);
    $scenario->push('WHERE a.appointment_id = :appointmentId', [[
        'appointment_id' => 50,
        'license_uuid' => 'abc123',
        'status' => 'cancelled',
        'start_at' => '2030-05-01T10:00:00-04:00',
        'end_at' => '2030-05-01T10:30:00-04:00',
        'duration_minutes' => 30,
        'customer_document' => '123',
        'customer_name' => 'x',
        'customer_phone' => '1',
        'customer_email' => '',
    ]]);
    $scenario->push('ROLLBACK;', []);

    [, , $appointmentService] = $makeServices($scenario);

    try {
        $appointmentService->transitionAdmin(50, 'confirmed');
    } catch (ApiException $e) {
        if ($e->getErrorCode() === 'INVALID_STATUS_TRANSITION') {
            return;
        }
    }

    throw new RuntimeException('No devolvió INVALID_STATUS_TRANSITION.');
});

$run('Hardening', 'endpoints y bootstrap mantienen controles de seguridad', function (): void {
    $root = dirname(__DIR__);

    $request = (string) file_get_contents($root . '/src/Http/Request.php');
    $bootstrap = (string) file_get_contents($root . '/bootstrap.php');
    $publicCreate = (string) file_get_contents($root . '/public/appointments_create.php');
    $adminConfirm = (string) file_get_contents($root . '/admin/appointments_confirm.php');

    if (!str_contains($request, 'public static function assertMethod(string ...$allowedMethods): void')) {
        throw new RuntimeException('Request::assertMethod no está disponible.');
    }

    if (!str_contains($bootstrap, 'DeploymentGuard::assertSecure($config);')) {
        throw new RuntimeException('Falta bloqueo de despliegue seguro.');
    }

    if (!str_contains($publicCreate, "Request::assertMethod('POST')")) {
        throw new RuntimeException('appointments_create no restringe método POST.');
    }

    if (!str_contains($adminConfirm, "adminAuth']->assert")) {
        throw new RuntimeException('appointments_confirm no valida token admin.');
    }
});

foreach ($ok as $line) {
    echo $line . PHP_EOL;
}

if ($fail !== []) {
    fwrite(STDERR, PHP_EOL . 'Errores detectados:' . PHP_EOL);
    foreach ($fail as $line) {
        fwrite(STDERR, '- ' . $line . PHP_EOL);
    }

    exit(1);
}

echo PHP_EOL . 'Suite negocio-funcional: TODO OK (' . count($ok) . ' pruebas).' . PHP_EOL;
