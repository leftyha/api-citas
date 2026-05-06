# Booking API (PHP 7.4)

API REST para gestión de disponibilidad y citas médicas, con canales público y administrativo.

## Estructura simplificada (9 archivos PHP)

1. `core.php` (lógica completa: config, conexión DB, seguridad, validaciones, handlers).
2. `availability.php`
3. `appointments_create.php`
4. `appointments_public_get.php`
5. `admin_appointments_list.php`
6. `admin_appointments_get.php`
7. `admin_appointments_update.php`
8. `admin_appointments_confirm.php`
9. `admin_appointments_cancel.php`

## Endpoints

### Públicos
- `GET /availability.php?licenseUuid=...&date=YYYY-MM-DD&durationMinutes=30`
- `POST /appointments_create.php`
- `GET|POST /appointments_public_get.php`

### Administrativos (Bearer token)
- `GET /admin_appointments_list.php`
- `GET /admin_appointments_get.php?appointmentId=...`
- `PATCH|PUT|POST /admin_appointments_update.php`
- `POST /admin_appointments_confirm.php`
- `POST /admin_appointments_cancel.php`

## Variables de entorno principales

- `BOOKING_DB_DRIVER` (`sqlsrv` o `mysql`)
- `BOOKING_DB_HOST`
- `BOOKING_DB_PORT`
- `BOOKING_DB_NAME`
- `BOOKING_DB_USER`
- `BOOKING_DB_PASSWORD`
- `BOOKING_ADMIN_TOKEN`
- `BOOKING_TOKEN_SECRET`
- `BOOKING_TOKEN_TTL_SECONDS`

## Contrato de respuesta

Éxito:
```json
{"ok":true,"message":"...","data":{}}
```

Error:
```json
{"ok":false,"code":"ERROR_CODE","message":"...","errors":[]}
```
