<?php

declare(strict_types=1);

use Booking\Booking\AppointmentValidator;
use Booking\Config\DeploymentGuard;
use Booking\Database\DatabaseClient;
use Booking\Http\ApiException;
use Booking\Http\Request;
use Booking\Mapping\LicenseMapper;
use Booking\Mapping\StatusMapper;
use Booking\Security\AdminAuth;
use Booking\Security\AppointmentTokenService;
use Booking\Security\Masking;
use Booking\Security\RateLimiter;

require dirname(__DIR__) . '/bootstrap.php';

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

$assertApiCode = static function (callable $fn, string $expectedCode): void {
    try {
        $fn();
    } catch (ApiException $e) {
        if ($e->getErrorCode() === $expectedCode) {
            return;
        }

        throw new RuntimeException(sprintf('Código esperado %s, recibido %s', $expectedCode, $e->getErrorCode()));
    }

    throw new RuntimeException('No se lanzó ApiException.');
};

$validator = new AppointmentValidator(['default_duration_minutes' => 30]);

$run('Validator', 'acepta payload público válido', static function () use ($validator): void {
    $validator->validateCreateInput([
        'licenseUuid' => 'abc123_license',
        'customerDocument' => 'V12345678',
        'customerName' => 'Paciente Demo',
        'customerPhone' => '+584121234567',
        'customerEmail' => 'demo@example.com',
        'startAt' => (new DateTimeImmutable('+2 days'))->format('Y-m-d\\TH:i:sP'),
        'durationMinutes' => 30,
        'serviceType' => 'consulta',
        'professionalId' => 7,
    ]);
});

$run('Validator', 'rechaza startAt sin timezone', static function () use ($validator, $assertApiCode): void {
    $assertApiCode(static function () use ($validator): void {
        $validator->parseStartAt('2030-01-01T10:30:00');
    }, 'VALIDATION_ERROR');
});

$run('Validator', 'rechaza fecha de disponibilidad inválida', static function () use ($validator, $assertApiCode): void {
    $assertApiCode(static function () use ($validator): void {
        $validator->validateAvailabilityInput('abc123', '2026-02-30', 30);
    }, 'VALIDATION_ERROR');
});

$run('Mapper', 'normaliza estados públicos e internos', static function (): void {
    if (StatusMapper::toPublic('c') !== 'confirmed') {
        throw new RuntimeException('toPublic no normaliza status corto.');
    }

    if (StatusMapper::normalizeInternal('canceled') !== 'cancelled') {
        throw new RuntimeException('normalizeInternal no normaliza canceled.');
    }

    if (!StatusMapper::canTransition('pending', 'confirmed')) {
        throw new RuntimeException('Transición pending -> confirmed debería ser válida.');
    }

    if (StatusMapper::canTransition('cancelled', 'confirmed')) {
        throw new RuntimeException('Transición cancelled -> confirmed debería ser inválida.');
    }
});

$run('Mapper', 'mapea licencia pública y booleans heterogéneos', static function (): void {
    $mapped = LicenseMapper::toPublic([
        'license_uuid' => 'abc123',
        'license_name' => 'Centro Médico',
        'logo_url' => 'https://cdn/logo.png',
        'booking_enabled' => 'yes',
    ]);

    if (($mapped['bookingEnabled'] ?? false) !== true || ($mapped['licenseName'] ?? null) !== 'Centro Médico') {
        throw new RuntimeException('Mapping de licencia no coincide con contrato público.');
    }
});

$run('Token', 'genera token y aplica TTL mínimo de 60s', static function (): void {
    $service = new AppointmentTokenService(['secret' => 'secret-x', 'key_id' => 'kid-x', 'prefix' => 'apt_']);
    $token = $service->issue(10, 'abc123', 1);
    $payload = $service->parse($token);

    if (($payload['exp'] - $payload['iat']) < 60) {
        throw new RuntimeException('TTL mínimo no aplicado.');
    }
});

$run('Token', 'rechaza token expirado correctamente firmado', static function () use ($assertApiCode): void {
    $secret = 'secret-x';
    $payload = [
        'v' => 1,
        'kid' => 'kid-x',
        'appointmentId' => 77,
        'licenseUuid' => 'abc123',
        'iat' => time() - 3600,
        'exp' => time() - 60,
    ];

    $encoded = rtrim(strtr(base64_encode((string) json_encode($payload)), '+/', '-_'), '=');
    $signature = hash_hmac('sha256', $encoded, $secret);
    $token = 'apt_' . $encoded . '.' . $signature;

    $service = new AppointmentTokenService(['secret' => $secret, 'key_id' => 'kid-x', 'prefix' => 'apt_']);
    $assertApiCode(static function () use ($service, $token): void {
        $service->parse($token);
    }, 'APPOINTMENT_NOT_FOUND');
});

$run('Security', 'valida AdminAuth con bearer token', static function () use ($assertApiCode): void {
    $auth = new AdminAuth(['token' => 'my-admin-token']);
    $auth->assert('Bearer my-admin-token');

    $assertApiCode(static function () use ($auth): void {
        $auth->assert('Bearer invalid-token');
    }, 'UNAUTHORIZED');
});

$run('Security', 'enmascara documento y aplica rate limit por archivo', static function () use ($assertApiCode): void {
    $masked = Masking::document('V12345678');
    if ($masked === 'V12345678' || !str_ends_with($masked, '5678')) {
        throw new RuntimeException('Masking no ocultó documento correctamente.');
    }

    $key = 'full-regression-' . bin2hex(random_bytes(6));
    $limiter = new RateLimiter([
        'backend' => 'file',
        'max_attempts' => 2,
        'window_seconds' => 60,
    ]);

    if (!$limiter->allow($key) || !$limiter->allow($key)) {
        throw new RuntimeException('RateLimiter bloqueó antes de alcanzar límite.');
    }

    if ($limiter->allow($key)) {
        throw new RuntimeException('RateLimiter no bloqueó al superar límite.');
    }

    $assertApiCode(static function () use ($limiter, $key): void {
        $limiter->assertAllowed($key);
    }, 'RATE_LIMIT_EXCEEDED');
});

$run('HTTP', 'resuelve headers, IP y método permitido', static function () use ($assertApiCode): void {
    $originalServer = $_SERVER;
    $_SERVER['REQUEST_METHOD'] = 'post';
    $_SERVER['HTTP_AUTHORIZATION'] = 'Bearer abc';
    $_SERVER['HTTP_X_FORWARDED_FOR'] = '203.0.113.55, 10.0.0.1';

    if (Request::method() !== 'POST') {
        throw new RuntimeException('Request::method no normaliza a mayúsculas.');
    }

    if (Request::header('Authorization') !== 'Bearer abc') {
        throw new RuntimeException('Request::header Authorization no coincide.');
    }

    if (Request::ip() !== '203.0.113.55') {
        throw new RuntimeException('Request::ip no prioriza X-Forwarded-For.');
    }

    Request::assertMethod('POST');

    $assertApiCode(static function (): void {
        Request::assertMethod('GET');
    }, 'METHOD_NOT_ALLOWED');

    $_SERVER = $originalServer;
});

$run('Config', 'bloquea configuración insegura en strict deploy', static function (): void {
    DeploymentGuard::assertSecure([
        'app' => ['strict_deploy' => false],
    ]);

    try {
        DeploymentGuard::assertSecure([
            'app' => ['strict_deploy' => true],
            'security' => [
                'appointment_token' => ['secret' => 'change-me-in-production'],
                'admin' => ['token' => 'safe-token'],
            ],
        ]);
    } catch (RuntimeException) {
        return;
    }

    throw new RuntimeException('DeploymentGuard no detectó secret inseguro.');
});

$run('Database', 'ejecuta begin/commit una sola vez por ciclo', static function (): void {
    $calls = [];
    $GLOBALS['__booking_test_db_handler'] = static function (string $sql, array $params) use (&$calls): array {
        $calls[] = [$sql, $params];
        return [];
    };

    if (!function_exists('ejecutarQueryAzureSQLServerV2')) {
        function ejecutarQueryAzureSQLServerV2(string $sql, array $params = []): array
        {
            $handler = $GLOBALS['__booking_test_db_handler'] ?? null;
            if (!is_callable($handler)) {
                return [];
            }

            return $handler($sql, $params);
        }
    }

    $db = new DatabaseClient(['driver' => 'sqlsrv']);
    $db->beginTransaction();
    $db->beginTransaction();
    $db->commit();
    $db->commit();

    $sqlCalls = array_map(static fn (array $call): string => $call[0], $calls);
    $all = implode(' | ', $sqlCalls);

    if (substr_count($all, 'BEGIN TRANSACTION') !== 1 || substr_count($all, 'COMMIT;') !== 1) {
        throw new RuntimeException('DatabaseClient no respetó semántica idempotente de transacciones.');
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

echo PHP_EOL . 'Suite full regression: TODO OK (' . count($ok) . ' pruebas).' . PHP_EOL;
