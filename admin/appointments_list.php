<?php

declare(strict_types=1);

use Booking\Http\ApiException;
use Booking\Http\Cors;
use Booking\Http\JsonResponse;
use Booking\Http\Request;

$container = require __DIR__ . '/../bootstrap.php';
Cors::apply($container['config']['app']['cors'] ?? []);

try {
    Request::assertMethod('GET');
    $container['rateLimiter']->assertAllowed('admin:appointments_list:' . Request::ip());
    $container['adminAuth']->assert((string) Request::header('Authorization', ''));
    $data = ['items' => $container['appointmentService']->listAdmin($_GET)];
    JsonResponse::success($data, 'Listado de citas.');
} catch (ApiException $e) {
    JsonResponse::error($e->getErrorCode(), $e->getMessage(), $e->getStatusCode(), $e->getErrors());
} catch (Throwable $e) {
    $container['logger']->error('admin_appointments_list_unhandled', [
        'requestId' => Request::requestId(),
        'method' => Request::method(),
        'path' => '/admin/appointments_list.php',
        'error' => $e->getMessage(),
    ]);
    JsonResponse::error('INTERNAL_ERROR', 'Error interno.', 500, ['requestId' => Request::requestId()]);
}
