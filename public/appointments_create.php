<?php

declare(strict_types=1);

use Booking\Http\ApiException;
use Booking\Http\Cors;
use Booking\Http\JsonResponse;
use Booking\Http\Request;

$container = require __DIR__ . '/../bootstrap.php';
Cors::apply($container['config']['app']['cors'] ?? []);

try {
    Request::assertMethod('POST');
    $container['rateLimiter']->assertAllowed('public:appointments_create:' . Request::ip());
    $body = Request::json();
    $data = $container['appointmentService']->create($body, $container['config']['booking']['token_ttl_seconds']);
    JsonResponse::success($data, 'Cita creada correctamente.', 201);
} catch (ApiException $e) {
    JsonResponse::error($e->getErrorCode(), $e->getMessage(), $e->getStatusCode(), $e->getErrors());
} catch (Throwable $e) {
    $container['logger']->error('appointments_create_unhandled', [
        'requestId' => Request::requestId(),
        'method' => Request::method(),
        'path' => '/public/appointments_create.php',
        'error' => $e->getMessage(),
    ]);
    JsonResponse::error('INTERNAL_ERROR', 'Error interno.', 500, ['requestId' => Request::requestId()]);
}
