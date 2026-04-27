# Documento funcional — Booking Citas API

## Objetivo

Exponer una API de citas con separación clara entre acceso público y operación administrativa, sin revelar estructura interna de base de datos.

## Principios de diseño

- Arquitectura por capas: `endpoint -> service -> repository -> database client`.
- Contrato JSON estable para éxito y error.
- Seguridad por token administrativo y token público de cita.
- Protección de datos sensibles en respuestas públicas.

## Recursos de API

## 1) Disponibilidad

### `GET /public/availability.php`

Devuelve slots de agenda disponibles sin exponer datos de citas existentes.

> La resolución `license_uuid -> license_id` es interna y se ejecuta dentro de servicios/repositorios; no se expone como endpoint público.

**Entrada**
- `licenseUuid`
- `date` (YYYY-MM-DD)
- `durationMinutes`

**Salida**
- Lista de `slots` disponibles.

## 2) Creación de cita

### `POST /public/appointments_create.php`

Registra una cita si el horario está disponible y la entrada cumple reglas de negocio.

**Entrada**
- Identificadores públicos de licencia/servicio/profesional.
- Datos del cliente.
- Fecha/hora de inicio y duración.

**Salida**
- Resumen público de cita y token de consulta.

## 3) Consulta pública de cita

### `GET /public/appointments_public_get.php`

Consulta una cita mediante token público firmado.

**Entrada**
- `appointmentToken`

**Salida**
- Estado y datos públicos de la cita en formato enmascarado cuando aplica.

## 4) Operación administrativa

Requiere `Authorization: Bearer <token>`.

- `GET /admin/appointments_list.php`: listado de citas.
- `GET /admin/appointments_get.php`: detalle de cita.
- `PUT /admin/appointments_update.php`: actualización de cita.
- `POST /admin/appointments_confirm.php`: transición a confirmada.
- `POST /admin/appointments_cancel.php`: transición a cancelada.

## Contratos HTTP

### Respuesta de éxito

```json
{
  "ok": true,
  "message": "...",
  "data": {}
}
```

### Respuesta de error

```json
{
  "ok": false,
  "code": "ERROR_CODE",
  "message": "...",
  "errors": []
}
```

## Códigos de error funcionales (referencia)

- `VALIDATION_ERROR`
- `UNAUTHORIZED`
- `METHOD_NOT_ALLOWED`
- `APPOINTMENT_NOT_FOUND`
- `LICENSE_NOT_FOUND`
- `SLOT_NOT_AVAILABLE`
- `INTERNAL_ERROR`

## Configuración relevante

- `BOOKING_TOKEN_SECRET`
- `BOOKING_TOKEN_KEY_ID`
- `BOOKING_ADMIN_TOKEN`
- `BOOKING_CORS_ALLOWED_ORIGINS`
- `BOOKING_RATE_LIMIT_MAX_ATTEMPTS`
- `BOOKING_RATE_LIMIT_WINDOW_SECONDS`
- `BOOKING_RATE_LIMIT_BACKEND`
- `BOOKING_STRICT_DEPLOY`
- `BOOKING_LOG_PATH`
