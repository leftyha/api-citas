# Booking API (PHP 7.4)

Flujo soportado (únicamente):

- Obtener/validar licencia por `id_lice_encr`.
- Ver disponibilidad (`availability.php`).
- Registrar cita (`appointments_create.php`).
- Actualizar cita (`appointments_update.php`).
- Borrar cita (`appointments_delete.php`).
- Listar citas (`appointments_list.php`).

## Tabla de citas
Se usa `t_cita` con estas columnas:
- `id_cita`
- `id_lice`
- `estu`
- `cedu_paci`
- `fech_cita`
- `fech_crea`
- `opto`
- `id_empl_opto`

## Endpoints

### `GET /availability.php`
Query params:
- `id_lice_encr` (obligatorio)
- `date` (obligatorio, `YYYY-MM-DD`)

### `POST /appointments_create.php`
JSON requerido:
- `id_lice_encr`
- `startAt` (ISO-8601)
- `customerDocument`

Reglas implementadas:
- Idempotencia: si existe misma licencia + mismo documento + mismo horario, retorna cita existente.
- No-duplicidad: si el mismo horario ya está ocupado por otro documento, retorna `409 SLOT_NOT_AVAILABLE`.

### `GET|POST /appointments_list.php`
- `id_lice_encr` obligatorio.

### `PATCH|PUT|POST /appointments_update.php`
JSON requerido:
- `id_lice_encr`
- `appointmentId`

JSON opcional:
- `status`
- `startAt`
- `opto`
- `id_empl_opto`

### `DELETE|POST /appointments_delete.php`
- `id_lice_encr` + `appointmentId`.
