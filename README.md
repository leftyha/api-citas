# API Citas

API REST en PHP para gestión de citas médicas con endpoints públicos y administrativos.

## Base conceptual

- Endpoints delgados en `public/` y `admin/`.
- Lógica de negocio en `src/Booking`.
- Acceso a datos en `src/Repository` vía `src/Database/DatabaseClient.php`.
- Contrato HTTP homogéneo con `src/Http/JsonResponse.php` y `src/Http/ApiException.php`.

## Endpoints públicos

### `GET /public/licenses_resolve.php`
Resuelve una licencia pública por `licenseUuid`.

**Query params**
- `licenseUuid` (string, requerido)

### `GET /public/availability.php`
Lista slots disponibles para una licencia en una fecha.

**Query params**
- `licenseUuid` (string, requerido)
- `date` (YYYY-MM-DD, requerido)
- `durationMinutes` (int, requerido)

### `POST /public/appointments_create.php`
Crea una cita pública.

**Body JSON**
- `licenseUuid`
- `serviceType`
- `professionalId`
- `startAt`
- `durationMinutes`
- `customer`:
  - `name`
  - `document`
  - `phone`
  - `email`

### `GET /public/appointments_public_get.php`
Consulta una cita por token público.

**Query params**
- `appointmentToken` (string, requerido)

## Endpoints admin

Todos requieren header `Authorization: Bearer <BOOKING_ADMIN_TOKEN>`.

- `GET /admin/appointments_list.php`
- `GET /admin/appointments_get.php`
- `PUT /admin/appointments_update.php`
- `POST /admin/appointments_confirm.php`
- `POST /admin/appointments_cancel.php`

## Contrato de respuesta

### Éxito

```json
{
  "ok": true,
  "message": "...",
  "data": {}
}
```

### Error

```json
{
  "ok": false,
  "code": "ERROR_CODE",
  "message": "...",
  "errors": []
}
```

## Configuración por entorno

Variables principales:

- `BOOKING_TOKEN_SECRET`
- `BOOKING_TOKEN_KEY_ID`
- `BOOKING_ADMIN_TOKEN`
- `BOOKING_CORS_ALLOWED_ORIGINS`
- `BOOKING_RATE_LIMIT_MAX_ATTEMPTS`
- `BOOKING_RATE_LIMIT_WINDOW_SECONDS`
- `BOOKING_RATE_LIMIT_BACKEND`
- `BOOKING_STRICT_DEPLOY`
- `BOOKING_LOG_PATH`

## Pruebas del core

Suite funcional de negocio disponible en:

```bash
php scripts/test_business_functional.php
```
