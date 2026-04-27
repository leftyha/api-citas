# api-citas

Estructura base de la API de citas en PHP clásico, alineada con `DOCUMENTO_FINAL_BOOKING_CITAS_API.md`.

## Estructura

- `bootstrap.php`: carga de configuración e instanciación de servicios.
- `public/`: endpoints públicos (`licenses_resolve`, `availability`, `appointments_create`, `appointments_public_get`).
- `admin/`: endpoints administrativos protegidos por token Bearer.
- `src/Http`: request/response, CORS y excepciones de API.
- `src/Security`: autenticación admin, token público de citas, rate limit y utilidades de masking.
- `src/Booking`: servicios de negocio y validaciones.
- `src/Repository`: acceso a datos (capa única con conocimiento de SQL/BD).
- `src/Mapping`: mapeos internos ↔ públicos.
- `src/Database`: wrapper de `ejecutarQueryAzureSQLServerV2()`.
- `src/Config/config.php`: configuración inicial (app, seguridad, booking, base path).
- `logs/`: directorio de logs.

## Segmento 0 aplicado (línea base y contrato)

### 1) Contrato de respuestas JSON

La clase `JsonResponse` define contrato homogéneo para toda salida HTTP:

- Éxito (`JsonResponse::success`):
  - `ok: true`
  - `message: string`
  - `data: object|array`
- Error (`JsonResponse::error`):
  - `ok: false`
  - `code: string`
  - `message: string`
  - `errors: array`

`ApiException` encapsula errores controlados con:

- `errorCode` (código funcional)
- `statusCode` (HTTP)
- `errors` (detalle validable)

> Resultado: el contrato de error/éxito queda centralizado y consistente para endpoints públicos y admin.

### 2) Wiring validado en `bootstrap.php`

`bootstrap.php` confirma el ensamblaje por capas (`endpoint -> service -> repository -> database client`):

1. Carga de configuración vía `Config::load(...)`.
2. Instanciación de `DatabaseClient` con `config['database']`.
3. Repositorios (`LicenseRepository`, `AppointmentRepository`).
4. Seguridad transversal (`RateLimiter`, `AdminAuth`, `AppointmentTokenService`).
5. Servicios de negocio (`LicenseService`, `AvailabilityService`, `AppointmentService`) con sus dependencias inyectadas.

> Resultado: todas las dependencias principales están cableadas sin modificar lógica de negocio.

### 3) Variables de entorno mínimas confirmadas

Variables requeridas para operación segura fuera de desarrollo:

- `BOOKING_TOKEN_SECRET`: secreto criptográfico para token público.
- `BOOKING_TOKEN_KEY_ID`: identificador de llave activa.
- `BOOKING_ADMIN_TOKEN`: bearer token de endpoints admin.

Si no se definen, `src/Config/config.php` usa valores fallback de desarrollo (`change-me-in-production`), por lo que en producción deben forzarse por entorno.

### 4) Estado actual: implementado vs placeholder

- **Implementado (línea base):**
  - estructura de carpetas y endpoints;
  - contrato de respuesta/error;
  - wiring de servicios y seguridad;
  - configuración base.
- **Pendiente / placeholder (segmentos siguientes):**
  - persistencia SQL real completa en repositorios;
  - reglas de negocio completas de disponibilidad/creación/consulta pública;
  - hardening final y QA preproducción.

## Variables de entorno recomendadas

- `BOOKING_TOKEN_SECRET`
- `BOOKING_TOKEN_KEY_ID`
- `BOOKING_ADMIN_TOKEN`

## Nota

Esta base crea el esqueleto y contratos principales para evolucionar la implementación real contra SQL Server/Azure SQL sin exponer identificadores internos.
