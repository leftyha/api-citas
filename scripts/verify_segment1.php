<?php

declare(strict_types=1);

$root = dirname(__DIR__);

$checks = [];
$errors = [];

function assertContains(string $content, string $needle, string $label, array &$checks, array &$errors): void
{
    if (str_contains($content, $needle)) {
        $checks[] = "OK: {$label}";
        return;
    }

    $errors[] = "FALTA: {$label}";
}

$requestFile = $root . '/src/Http/Request.php';
$corsFile = $root . '/src/Http/Cors.php';
$publicEndpoints = glob($root . '/public/*.php') ?: [];
$adminEndpoints = glob($root . '/admin/*.php') ?: [];

$requestContent = (string) file_get_contents($requestFile);
$corsContent = (string) file_get_contents($corsFile);

assertContains($requestContent, 'public static function method()', 'Request::method disponible', $checks, $errors);
assertContains($requestContent, 'public static function json()', 'Request::json disponible', $checks, $errors);
assertContains($requestContent, 'public static function header(', 'Request::header disponible', $checks, $errors);
assertContains($requestContent, 'public static function ip()', 'Request::ip disponible', $checks, $errors);
assertContains($requestContent, 'public static function requestId()', 'Request::requestId disponible', $checks, $errors);

assertContains($corsContent, "if ((\$_SERVER['REQUEST_METHOD'] ?? 'GET') === 'OPTIONS')", 'CORS atiende preflight OPTIONS', $checks, $errors);
assertContains($corsContent, "http_response_code(204);", 'CORS responde 204 en OPTIONS', $checks, $errors);
assertContains($corsContent, "allowed_origins", 'CORS configurable por allowed_origins', $checks, $errors);

foreach (array_merge($publicEndpoints, $adminEndpoints) as $file) {
    $name = str_replace($root . '/', '', $file);
    $content = (string) file_get_contents($file);

    assertContains($content, "->assertAllowed(", "{$name} aplica rate limit", $checks, $errors);
    assertContains($content, "['requestId' => Request::requestId()]", "{$name} incluye requestId en error no controlado", $checks, $errors);
}

foreach ($adminEndpoints as $file) {
    $name = str_replace($root . '/', '', $file);
    $content = (string) file_get_contents($file);

    assertContains($content, "adminAuth']->assert", "{$name} valida bearer token admin", $checks, $errors);
}

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

echo PHP_EOL . 'Segmento 1 verificado: TODO OK.' . PHP_EOL;
