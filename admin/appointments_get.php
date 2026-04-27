<?php

declare(strict_types=1);

use Booking\Http\ApiException;
use Booking\Http\Cors;
use Booking\Http\JsonResponse;
use Booking\Http\Request;

$container = require __DIR__ . '/../bootstrap.php';
Cors::apply($container['config']['app']['cors'] ?? []);

try {
    $container['rateLimiter']->assertAllowed('admin:appointments_get:' . Request::ip());
    $container['adminAuth']->assert((string) Request::header('Authorization', ''));
    $id = (int) Request::query('appointmentId', 0);
    $data = $container['appointmentService']->getAdmin($id);
    JsonResponse::success($data, 'Detalle de cita.');
} catch (ApiException $e) {
    JsonResponse::error($e->getErrorCode(), $e->getMessage(), $e->getStatusCode(), $e->getErrors());
} catch (Throwable) {
    JsonResponse::error('INTERNAL_ERROR', 'Error interno.', 500, ['requestId' => Request::requestId()]);
}
