# Booking API (PHP 7.4)

API REST para consultar disponibilidad y gestionar citas médicas.

## Qué resuelve

- Publica horarios disponibles por licencia profesional.
- Permite crear citas desde canal público.
- Entrega un token de consulta para que el paciente vea su cita sin autenticación administrativa.
- Expone endpoints administrativos para listar, consultar, actualizar y cambiar estado de citas.

## Requisitos

- PHP 7.4+ con PDO habilitado.
- Base de datos compatible con `sqlsrv` o `mysql`.
- Tablas de negocio:
  - `booking_licenses`
  - `booking_appointments`

## Configuración

1. Copiar variables desde `.env.example`.
2. Configurar conexión a base de datos.
3. Definir secretos y token administrativo en producción:
   - `BOOKING_TOKEN_SECRET`
   - `BOOKING_ADMIN_TOKEN`
4. Configurar zona horaria y CORS según entorno.

Variables principales:

- `BOOKING_DB_DRIVER` (`sqlsrv` o `mysql`)
- `BOOKING_DB_HOST`
- `BOOKING_DB_PORT`
- `BOOKING_DB_NAME`
- `BOOKING_DB_USER`
- `BOOKING_DB_PASSWORD`
- `BOOKING_ADMIN_TOKEN`
- `BOOKING_TOKEN_SECRET`
- `BOOKING_TOKEN_TTL_SECONDS`

## Endpoints

### Públicos

#### `GET /availability.php`
Consulta slots disponibles.

Parámetros de query:
- `id_lice_encr` (obligatorio)
- `date` (obligatorio, formato `YYYY-MM-DD`)
- `durationMinutes` (opcional, entero)

#### `POST /appointments_create.php`
Crea una cita en estado `pending`.

Campos JSON requeridos:
- `id_lice_encr`
- `startAt` (ISO-8601)
- `customerDocument`
- `customerName`
- `customerPhone`

Campos opcionales:
- `durationMinutes`
- `customerEmail`
- `serviceType`
- `professionalId`
- `notes`

Respuesta relevante:
- `appointment.appointmentToken` para consulta pública posterior.

#### `GET|POST /appointments_public_get.php`
Obtiene detalle público de una cita a partir de `appointmentToken`.

#### `GET|POST /appointments_list.php`
Lista citas de una licencia usando `id_lice_encr`.

#### `PATCH|PUT|POST /appointments_update.php`
Actualiza una cita de la misma licencia (`appointmentId` + `id_lice_encr`).

#### `DELETE|POST /appointments_delete.php`
Elimina una cita de la misma licencia (`appointmentId` + `id_lice_encr`).

### Administrativos (Bearer token)

Requieren header:

```http
Authorization: Bearer <BOOKING_ADMIN_TOKEN>
```

#### `GET /admin_appointments_list.php`
Lista citas con filtros opcionales:
- `date`
- `status`
- `professionalId`
- `customerDocument`

#### `GET /admin_appointments_get.php`
Consulta una cita por `appointmentId`.

#### `PATCH|PUT|POST /admin_appointments_update.php`
Actualiza datos de cita (excepto estado).

#### `POST /admin_appointments_confirm.php`
Cambia estado a `confirmed`.

#### `POST /admin_appointments_cancel.php`
Cambia estado a `cancelled`.

## Reglas funcionales importantes

- Solo se permiten reservas en licencias activas (`booking_enabled = true`).
- Un horario ocupado por cita `pending` o `confirmed` no puede duplicarse para otro documento.
- Si se reintenta reservar exactamente el mismo horario con el mismo documento, la API reutiliza la cita existente.
- El token público de cita tiene expiración según `BOOKING_TOKEN_TTL_SECONDS`.

## Formato de respuestas

Éxito:

```json
{"ok":true,"message":"...","data":{}}
```

Error:

```json
{"ok":false,"code":"ERROR_CODE","message":"...","errors":[]}
```
