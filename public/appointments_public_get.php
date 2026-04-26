<?php

declare(strict_types=1);

use Booking\Http\ApiException;
use Booking\Http\Cors;
use Booking\Http\JsonResponse;
use Booking\Http\Request;

$container = require __DIR__ . '/../bootstrap.php';
Cors::apply();

try {
    $token = Request::query('appointmentToken');

    if ($token === null || $token === '') {
        $body = Request::json();
        $token = (string) ($body['appointmentToken'] ?? '');
    }

    $data = $container['appointmentService']->getPublicByToken((string) $token);
    JsonResponse::success($data, 'Cita obtenida correctamente.');
} catch (ApiException $e) {
    JsonResponse::error($e->getErrorCode(), $e->getMessage(), $e->getStatusCode(), $e->getErrors());
} catch (Throwable) {
    JsonResponse::error('INTERNAL_ERROR', 'Error interno.', 500);
}
