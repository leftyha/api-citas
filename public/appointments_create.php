<?php

declare(strict_types=1);

use Booking\Http\ApiException;
use Booking\Http\Cors;
use Booking\Http\JsonResponse;
use Booking\Http\Request;

$container = require __DIR__ . '/../bootstrap.php';
Cors::apply($container['config']['app']['cors'] ?? []);

try {
    $container['rateLimiter']->assertAllowed('public:appointments_create:' . Request::ip());
    $body = Request::json();
    $data = $container['appointmentService']->create($body, $container['config']['booking']['token_ttl_seconds']);
    JsonResponse::success($data, 'Cita creada correctamente.', 201);
} catch (ApiException $e) {
    JsonResponse::error($e->getErrorCode(), $e->getMessage(), $e->getStatusCode(), $e->getErrors());
} catch (Throwable) {
    JsonResponse::error('INTERNAL_ERROR', 'Error interno.', 500, ['requestId' => Request::requestId()]);
}
