<?php

declare(strict_types=1);

$root = dirname(__DIR__);
$errors = [];
$checks = [];

function hardeningCheck(bool $condition, string $label, array &$checks, array &$errors): void
{
    if ($condition) {
        $checks[] = "OK: {$label}";
        return;
    }

    $errors[] = "FALTA: {$label}";
}

$request = (string) file_get_contents($root . '/src/Http/Request.php');
$bootstrap = (string) file_get_contents($root . '/bootstrap.php');
$rateLimiter = (string) file_get_contents($root . '/src/Security/RateLimiter.php');
$config = (string) file_get_contents($root . '/src/Config/config.php');

$methodFiles = [
    '/public/appointments_create.php' => "Request::assertMethod('POST')",
    '/public/appointments_public_get.php' => "Request::assertMethod('GET', 'POST')",
    '/admin/appointments_list.php' => "Request::assertMethod('GET')",
    '/admin/appointments_get.php' => "Request::assertMethod('GET')",
    '/admin/appointments_update.php' => "Request::assertMethod('PATCH')",
    '/admin/appointments_confirm.php' => "Request::assertMethod('POST')",
    '/admin/appointments_cancel.php' => "Request::assertMethod('POST')",
];

hardeningCheck(
    str_contains($request, 'public static function assertMethod(string ...$allowedMethods): void')
    && str_contains($request, 'METHOD_NOT_ALLOWED'),
    'Validación global de método HTTP',
    $checks,
    $errors
);

foreach ($methodFiles as $file => $needle) {
    $content = (string) file_get_contents($root . $file);
    hardeningCheck(str_contains($content, $needle), "Endpoint {$file} con método cerrado", $checks, $errors);
}

hardeningCheck(
    str_contains($bootstrap, 'DeploymentGuard::assertSecure($config);'),
    'Bloqueo de arranque con secretos inseguros',
    $checks,
    $errors
);

hardeningCheck(
    str_contains($rateLimiter, 'allowDatabase(')
    && str_contains($config, "'backend' => getenv('BOOKING_RATE_LIMIT_BACKEND') ?: 'database'"),
    'Rate limiter distribuido por backend compartido',
    $checks,
    $errors
);

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

echo PHP_EOL . "Hardening 6-7 verificado: TODO OK." . PHP_EOL;
