<?php

declare(strict_types=1);

use Booking\Http\ApiException;
use Booking\Http\Cors;
use Booking\Http\JsonResponse;
use Booking\Http\Request;

$container = require __DIR__ . '/../bootstrap.php';
Cors::apply($container['config']['app']['cors'] ?? []);

try {
    if (Request::method() !== 'GET') {
        throw new ApiException('Método no permitido.', 'METHOD_NOT_ALLOWED', 405);
    }

    $container['rateLimiter']->assertAllowed('public:availability:' . Request::ip());
    $licenseUuid = (string) Request::query('licenseUuid', '');
    $date = (string) Request::query('date', '');
    $duration = (int) Request::query('durationMinutes', 30);

    $data = $container['availabilityService']->getSlots($licenseUuid, $date, $duration);
    JsonResponse::success($data, 'Disponibilidad obtenida correctamente.');
} catch (ApiException $e) {
    JsonResponse::error($e->getErrorCode(), $e->getMessage(), $e->getStatusCode(), $e->getErrors());
} catch (Throwable) {
    JsonResponse::error('INTERNAL_ERROR', 'Error interno.', 500, ['requestId' => Request::requestId()]);
}
