<?php

declare(strict_types=1);

use Booking\Http\ApiException;
use Booking\Http\Cors;
use Booking\Http\JsonResponse;
use Booking\Http\Request;

$container = require __DIR__ . '/../bootstrap.php';
Cors::apply($container['config']['app']['cors'] ?? []);

try {
    Request::assertMethod('GET', 'POST');
    $container['rateLimiter']->assertAllowed('public:appointments_public_get:' . Request::ip());
    $token = Request::query('appointmentToken');

    if ($token === null || $token === '') {
        $body = Request::json();
        $token = (string) ($body['appointmentToken'] ?? '');
    }

    $data = $container['appointmentService']->getPublicByToken((string) $token);
    JsonResponse::success($data, 'Cita encontrada.');
} catch (ApiException $e) {
    JsonResponse::error($e->getErrorCode(), $e->getMessage(), $e->getStatusCode(), $e->getErrors());
} catch (Throwable $e) {
    $container['logger']->error('appointments_public_get_unhandled', [
        'requestId' => Request::requestId(),
        'method' => Request::method(),
        'path' => '/public/appointments_public_get.php',
        'error' => $e->getMessage(),
    ]);
    JsonResponse::error('INTERNAL_ERROR', 'Error interno.', 500, ['requestId' => Request::requestId()]);
}
