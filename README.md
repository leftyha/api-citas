# API de Citas

API REST en PHP para la gestión operativa de agendas médicas. Está pensada para cubrir dos frentes de negocio: autoservicio de pacientes (canal público) y operación interna de backoffice (canal administrativo).

## Propósito funcional

La API resuelve el ciclo completo de una cita:

1. Consultar disponibilidad por sede/licencia y duración.
2. Registrar una cita con datos del paciente.
3. Entregar un token público para consulta posterior.
4. Permitir que el equipo interno liste, consulte, edite y cambie el estado de citas.

## Alcance de negocio

- **Canal público:** permite reservar y consultar sin exponer datos sensibles completos.
- **Canal administrativo:** permite operar la agenda con autenticación de backoffice.
- **Seguridad operacional:** incluye control de tasa por IP, token público de cita y control de acceso con Bearer token administrativo.
- **Integridad de agenda:** evita doble reserva de horario activo y maneja transiciones válidas de estado.

## Arquitectura funcional

- `public/` y `admin/`: capa HTTP con endpoints delgados.
- `src/Booking`: casos de uso del negocio (disponibilidad y citas).
- `src/Repository`: acceso a datos SQL Server.
- `src/Security`: autenticación admin, emisión/validación de token de cita y rate limiting.
- `src/Http`: contrato homogéneo de respuestas de éxito/error y parsing de request.

## Endpoints de la API

### Públicos

| Método | Endpoint | Objetivo funcional | Entrada principal | Resultado esperado |
|---|---|---|---|---|
| GET | `/public/availability.php` | Consultar slots libres por fecha, licencia y duración. | Query: `licenseUuid`, `date` (YYYY-MM-DD), `durationMinutes` (15/30/45/60/90/120). | Lista de bloques disponibles para reservar. |
| POST | `/public/appointments_create.php` | Crear una cita nueva en estado pendiente. | JSON con `licenseUuid`, `startAt` (con zona horaria), `durationMinutes`, datos del paciente y opcionales (`serviceType`, `professionalId`, `notes`). | Cita creada con `appointmentToken` para seguimiento. |
| GET / POST | `/public/appointments_public_get.php` | Consultar una cita por token público. | `appointmentToken` por query o body. | Detalle público de la cita con documento y teléfono enmascarados. |

### Administrativos

> Requieren header `Authorization: Bearer <BOOKING_ADMIN_TOKEN>`.

| Método | Endpoint | Objetivo funcional | Entrada principal | Resultado esperado |
|---|---|---|---|---|
| GET | `/admin/appointments_list.php` | Listar citas para operación diaria. | Filtros opcionales: `date`, `status`, `professionalId`, `customerDocument`. | Colección de citas con datos administrativos. |
| GET | `/admin/appointments_get.php` | Ver detalle completo de una cita. | Query: `appointmentId`. | Registro único con información de cliente y agenda. |
| PATCH / PUT | `/admin/appointments_update.php` | Actualizar datos operativos de una cita. | JSON con `appointmentId` y campos editables (no permite `status`). | Cita actualizada. |
| POST | `/admin/appointments_confirm.php` | Confirmar una cita pendiente. | JSON: `appointmentId`. | Cambio de estado aplicado (si transición es válida). |
| POST | `/admin/appointments_cancel.php` | Cancelar una cita en flujo operativo. | JSON: `appointmentId`. | Cambio de estado aplicado (si transición es válida). |

## Reglas funcionales clave

### Disponibilidad

- Solo permite fechas actuales o futuras.
- Duraciones permitidas: 15, 30, 45, 60, 90 y 120 minutos.
- Ventanas de agenda por día:
  - Lunes a viernes: 09:00 a 17:00.
  - Sábado: 09:00 a 13:00.
  - Domingo: sin disponibilidad.

### Creación de citas

- `licenseUuid` debe existir y estar habilitada para reservas.
- `startAt` debe incluir zona horaria y no puede estar en el pasado.
- Aplica validaciones de calidad de datos para documento, nombre, teléfono y correo.
- Evita colisiones de horario en estados activos (`pending`, `confirmed`).
- Si se detecta un intento duplicado con mismo horario y documento, reutiliza la cita existente.

### Consulta pública por token

- El token representa la autorización de lectura pública de la cita.
- La respuesta pública minimiza exposición de PII usando enmascaramiento de documento y teléfono.

### Operación administrativa

- El listado soporta filtros de uso real de call center y backoffice.
- La edición administrativa bloquea cambios directos de estado vía endpoint de update.
- Las transiciones de estado se hacen en endpoints dedicados para asegurar reglas de flujo.

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

- `BOOKING_DB_DRIVER`
- `BOOKING_DB_HOST`
- `BOOKING_DB_PORT`
- `BOOKING_DB_NAME`
- `BOOKING_DB_USER`
- `BOOKING_DB_PASSWORD`
- `BOOKING_DB_ENCRYPT`
- `BOOKING_DB_TRUST_SERVER_CERTIFICATE`
- `BOOKING_ENV`
- `BOOKING_STRICT_DEPLOY`
- `BOOKING_TIMEZONE`
- `BOOKING_BASE_PATH`
- `BOOKING_TOKEN_SECRET`
- `BOOKING_TOKEN_KEY_ID`
- `BOOKING_TOKEN_PREFIX`
- `BOOKING_ADMIN_TOKEN`
- `BOOKING_DEFAULT_DURATION_MINUTES`
- `BOOKING_TOKEN_TTL_SECONDS`
- `BOOKING_CORS_ALLOWED_ORIGINS`
- `BOOKING_RATE_LIMIT_MAX_ATTEMPTS`
- `BOOKING_RATE_LIMIT_WINDOW_SECONDS`
- `BOOKING_RATE_LIMIT_BACKEND`
- `BOOKING_LOG_PATH`
- `BOOKING_TRUSTED_PROXIES` (lista separada por comas; solo estos proxies pueden aportar `X-Forwarded-For`)

> Recomendación: usa `.env` solo en entornos locales y nunca lo versionas. Toma `.env.example` como plantilla.
>
> `bootstrap.php` carga automáticamente `.env` (si existe) y **no sobreescribe** variables ya definidas por el entorno del servidor.

Plantillas incluidas:

- `.env.example`: base para desarrollo/local.
- `.env.production.example`: base endurecida para despliegue en producción (reemplazando placeholders `<<...>>`).


## Pruebas del core

Suites disponibles:

```bash
php scripts/test_business_functional.php
php scripts/test_full_regression.php
```

Variables de entorno relevantes:

- `BOOKING_ADMIN_TOKEN`: token de acceso para endpoints administrativos.
- `BOOKING_TOKEN_SECRET`: secreto para emisión/validación de token público de citas.
- `BOOKING_TOKEN_KEY_ID`: identificador de clave para trazabilidad del token.
- `BOOKING_CORS_ALLOWED_ORIGINS`: orígenes autorizados para consumo web.
- `BOOKING_RATE_LIMIT_BACKEND`: backend de rate limiting (`database` o `file`).
- `BOOKING_RATE_LIMIT_MAX_ATTEMPTS`: intentos máximos por ventana.
- `BOOKING_RATE_LIMIT_WINDOW_SECONDS`: tamaño de la ventana de control.
- `BOOKING_TRUSTED_PROXIES`: proxies confiables para resolución de IP real.
