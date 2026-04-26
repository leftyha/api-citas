<?php

declare(strict_types=1);

use Booking\Http\ApiException;
use Booking\Http\Cors;
use Booking\Http\JsonResponse;
use Booking\Http\Request;

$container = require __DIR__ . '/../bootstrap.php';
Cors::apply();

try {
    $licenseUuid = (string) Request::query('licenseUuid', '');
    $date = (string) Request::query('date', '');
    $duration = (int) Request::query('durationMinutes', 30);

    $data = $container['availabilityService']->getSlots($licenseUuid, $date, $duration);
    JsonResponse::success($data, 'Disponibilidad obtenida correctamente.');
} catch (ApiException $e) {
    JsonResponse::error($e->getErrorCode(), $e->getMessage(), $e->getStatusCode(), $e->getErrors());
} catch (Throwable) {
    JsonResponse::error('INTERNAL_ERROR', 'Error interno.', 500);
}
