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
- `BOOKING_CORS_ALLOWED_ORIGINS` (csv, ejemplo: `https://web.tu-dominio.com,https://qa.tu-dominio.com`)
- `BOOKING_RATE_LIMIT_MAX_ATTEMPTS`
- `BOOKING_RATE_LIMIT_WINDOW_SECONDS`
- `BOOKING_RATE_LIMIT_BACKEND` (`database` recomendado para multi-instancia)
- `BOOKING_STRICT_DEPLOY` (`true` por defecto, bloquea secrets default)
- `BOOKING_LOG_PATH`

## Segmento 1 aplicado (core HTTP + seguridad base)

### 1) Request normalizado

`Request` ahora incluye soporte base para:

- método HTTP (`Request::method`);
- lectura robusta de headers (`Authorization`, `Content-Type`, y headers estándar);
- lectura de IP (`Request::ip`);
- `requestId` para trazabilidad en errores no controlados (`Request::requestId`).

Además, `Request::json()` evita parsear body en `GET` y mantiene validación de `application/json` para requests con body.

### 2) CORS configurable por entorno

`Cors::apply(...)` recibe configuración y permite:

- usar `*` por defecto en desarrollo;
- restringir a una lista de orígenes (`BOOKING_CORS_ALLOWED_ORIGINS`) para QA/producción;
- mantener `OPTIONS` preflight con respuesta 204.

### 3) Error no controlado homogéneo y trazable

Todos los endpoints (`public/` y `admin/`) responden error interno homogéneo y agregan:

- `errors.requestId`

permitiendo correlación con logs sin exponer detalles sensibles.

### 4) Seguridad base activa en endpoints

- Endpoints admin siguen exigiendo bearer token vía `AdminAuth`.
- Se activó rate limit básico por IP + endpoint en todos los entrypoints usando `RateLimiter::assertAllowed(...)`.
- `Masking` queda disponible para mapeos de salida sensibles (segmentos posteriores de datos públicos/admin completos).

## Nota

Esta base crea el esqueleto y contratos principales para evolucionar la implementación real contra SQL Server/Azure SQL sin exponer identificadores internos.

## Verificación rápida del Segmento 1

Se agregó un verificador ejecutable para comprobar automáticamente los criterios mínimos del Segmento 1 (core HTTP + seguridad base):

```bash
php scripts/verify_segment1.php
```

El script valida presencia de:

- `Request::method/json/header/ip/requestId`.
- CORS configurable con preflight `OPTIONS` y `204`.
- Rate limit por endpoint.
- `requestId` en errores no controlados.
- Autenticación admin en endpoints administrativos.

## Segmentos 2 y 3 aplicados (licencias + disponibilidad pública)

### Segmento 2 — Licencias (resolución pública segura)

- `LicenseService` ahora valida formato de `licenseUuid` y responde error controlado (`VALIDATION_ERROR`) cuando no cumple contrato.
- `LicenseRepository` consulta por UUID y evita exponer campos internos, devolviendo solo el mapeo público.
- `LicenseMapper` soporta aliases de columnas para facilitar integración con SQL Server sin cambiar contrato público.
- `public/licenses_resolve.php` limita el endpoint a `GET` y mantiene salida homogénea.

### Segmento 3 — Disponibilidad pública (sin revelar citas)

- `AvailabilityService` valida entrada (`licenseUuid`, `date`, `durationMinutes`) antes de consultar disponibilidad.
- `AppointmentRepository::listAvailability` calcula slots disponibles y nunca devuelve datos de pacientes/citas ocupadas.
- `public/availability.php` limita el endpoint a `GET` y mantiene formato de respuesta homogéneo.

### Verificación rápida de Segmentos 2 y 3

```bash
php scripts/verify_segment2_3.php
```

El script verifica:

- resolución pública de licencia con contrato mínimo;
- validaciones de UUID/licencia inexistente;
- disponibilidad sin datos sensibles (ni en `slots` ni en la raíz del payload);
- validaciones de fecha y duración.

## Segmentos 4 y 5 aplicados (creación de cita + token público)

### Segmento 4 — Creación de cita pública

- `AppointmentValidator` ahora aplica validaciones fuertes de payload (rangos, email, duración, `startAt` con zona horaria y fecha futura).
- `AppointmentService::create` ejecuta creación en transacción y responde contrato público mínimo (`data.appointment`).
- `AppointmentRepository::create` implementa validación de conflicto (`pending/confirmed`), idempotencia operativa para doble submit y creación real por SQL.

### Segmento 5 — Token público y consulta de cita propia

- `AppointmentTokenService` ahora emite token firmado con payload versionado, `kid`, expiración y parsing robusto.
- `AppointmentRepository::findPublicById` ejecuta lookup real y mapea solo datos necesarios para salida pública.
- `AppointmentService::getPublicByToken` valida token/cita/licencia y devuelve datos mínimos con documento y teléfono enmascarados.

### Verificación rápida de Segmentos 4 y 5

```bash
php scripts/verify_segment4_5.php
```

## Segmentos 6 y 7 aplicados (admin completo + persistencia SQL v1)

### Segmento 6 — API admin completa con autorización y reglas de estado

- `AppointmentRepository` ahora implementa `listAdmin`, `findAdminById`, `updateAdmin` y `transitionStatus` con SQL real.
- `AppointmentService` agrega validación de `appointmentId` y evita actualizar `status` por el endpoint de edición general.
- `StatusMapper` incorpora normalización de estado y reglas explícitas de transición (`pending -> confirmed/cancelled`, `confirmed -> cancelled`).

### Segmento 7 — Persistencia real SQL Server + transaccionalidad v1

- Se eliminaron stubs de datos mock en repositorios principales (`LicenseRepository`, `AppointmentRepository`).
- `DatabaseClient` define estrategia transaccional explícita con `SET XACT_ABORT ON; BEGIN TRANSACTION` y control de estado transaccional interno.
- Fallas de base de datos se encapsulan sin filtrar detalles SQL (`RuntimeException` genérica) y los endpoints mantienen respuesta controlada.

### Verificación rápida de Segmentos 6 y 7

```bash
php scripts/verify_segment6_7.php
```


## Suite unificada de pruebas unitarias negocio-funcional

Además de los verificadores por segmento, existe una suite unificada orientada a flujos de negocio end-to-end a nivel unitario (licencias, disponibilidad, creación/consulta pública, administración y hardening):

```bash
php scripts/test_business_functional.php
```

## Segmento 8 aplicado (QA técnico final + hardening pre-producción)

### Cobertura implementada

- Se agregó `scripts/verify_segment8.php` con pruebas de seguridad de token público (`manipulado` y `expirado`) validando fallo controlado con `APPOINTMENT_NOT_FOUND`.
- Se añadieron verificaciones de privacidad para confirmar que disponibilidad pública y consulta pública no exponen IDs internos ni datos sensibles sin enmascarar.
- Se incorporó revisión técnica de configuración para asegurar que CORS y secretos críticos dependen de variables de entorno.
- Se validó que endpoints administrativos mantienen protección por bearer token.

### Verificación rápida de Segmento 8

```bash
php scripts/verify_segment8.php
```

## Hardening de producción (segmentos 6-7 checklist operativo)

- Endpoints críticos validan método HTTP explícito con respuesta `405 METHOD_NOT_ALLOWED` cuando no coincide contrato.
- El arranque falla en modo estricto (`BOOKING_STRICT_DEPLOY=true`) si secretos críticos usan defaults inseguros.
- Rate limit soporta backend distribuido por SQL (`BOOKING_RATE_LIMIT_BACKEND=database`) para despliegues multi-instancia.
- Se agrega logging estructurado JSON con `requestId` para eventos de error no controlado.
- Se incorporan artefactos operativos:
  - `docs/qa/min_dataset.sql` (dataset mínimo QA).
  - `docs/qa/e2e_evidence.md` (plantilla de evidencia E2E).
  - `docs/operations/logging_alerts.md` (estándar de logs + alertas).
  - `docs/operations/RUNBOOK.md` (runbook corto de incidentes).
  - `docs/operations/production_on_checklist.md` (criterios de producción ON).
