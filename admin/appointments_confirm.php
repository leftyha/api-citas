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
    $container['rateLimiter']->assertAllowed('admin:appointments_confirm:' . Request::ip());
    $container['adminAuth']->assert((string) Request::header('Authorization', ''));
    $body = Request::json();
    $id = (int) ($body['appointmentId'] ?? 0);
    $data = $container['appointmentService']->transitionAdmin($id, 'confirmed');
    JsonResponse::success($data, 'Cita confirmada.');
} catch (ApiException $e) {
    JsonResponse::error($e->getErrorCode(), $e->getMessage(), $e->getStatusCode(), $e->getErrors());
} catch (Throwable $e) {
    $container['logger']->error('admin_appointments_confirm_unhandled', [
        'requestId' => Request::requestId(),
        'method' => Request::method(),
        'path' => '/admin/appointments_confirm.php',
        'error' => $e->getMessage(),
    ]);
    JsonResponse::error('INTERNAL_ERROR', 'Error interno.', 500, ['requestId' => Request::requestId()]);
}
