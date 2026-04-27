# API Citas

API REST en PHP para gestión de citas médicas con endpoints públicos y administrativos.

## Base conceptual

- Endpoints delgados en `public/` y `admin/`.
- Lógica de negocio en `src/Booking`.
- Acceso a datos en `src/Repository` vía `src/Database/DatabaseClient.php`.
- Contrato HTTP homogéneo con `src/Http/JsonResponse.php` y `src/Http/ApiException.php`.

## Endpoints públicos

> Nota: la resolución interna `license_uuid -> license_id` ocurre dentro de la API en cada flujo público que recibe `licenseUuid`. No existe endpoint público dedicado para ese proceso interno.

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
- `customerName`
- `customerDocument`
- `customerPhone`
- `customerEmail`

> Compatibilidad: también se acepta `customer` anidado con `name/document/phone/email`, y la API lo normaliza internamente.

### `GET /public/appointments_public_get.php`
Consulta una cita por token público.

**Query params**
- `appointmentToken` (string, requerido)

## Endpoints admin

Todos requieren header `Authorization: Bearer <BOOKING_ADMIN_TOKEN>`.

- `GET /admin/appointments_list.php`
- `GET /admin/appointments_get.php`
- `PATCH /admin/appointments_update.php` (también acepta `PUT` por compatibilidad)
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
- `BOOKING_TRUSTED_PROXIES` (lista separada por comas; solo estos proxies pueden aportar `X-Forwarded-For`)

## Pruebas del core

Suites disponibles:

```bash
php scripts/test_business_functional.php
php scripts/test_full_regression.php
```

Checklist completo de pruebas E2E/negativas/regresión:

- `TESTING_CHECKLIST.md`
