# Plan segmentado de implementación (basado en el documento y en el código actual)

Este plan está diseñado para que puedas pedir en el futuro: **"implementa el segmento N"** y avanzar la API completa sin salir del alcance definido en `DOCUMENTO_FINAL_BOOKING_CITAS_API.md` ni de la estructura ya creada en el repositorio.

## Reglas del plan (aplican a todos los segmentos)

1. No cambiar alcance funcional fuera del documento base.
2. No exponer IDs internos ni nombres reales de BD en endpoints públicos.
3. Mantener arquitectura: `endpoint -> service -> repository -> database client`.
4. No modificar estructura de BD en v1.
5. Cada segmento se cierra con checklist técnico y pruebas mínimas.

---

## Segmento 0 — Línea base y contrato de implementación

**Objetivo:** dejar listo el marco para trabajar por segmentos sin desviaciones.

**Incluye:**
- Confirmar configuración y contratos de respuesta/errores.
- Confirmar wiring de `bootstrap.php` y dependencias actuales.
- Confirmar variables de entorno mínimas.

**Archivos foco:**
- `bootstrap.php`
- `src/Config/config.php`
- `src/Http/JsonResponse.php`
- `src/Http/ApiException.php`
- `README.md`

**Criterio de cierre:**
- Se documenta y valida qué está implementado y qué es placeholder.
- No se altera comportamiento de negocio.

**Solicitud futura sugerida:**
- `implementa el segmento 0`.

---

## Segmento 1 — Core HTTP + seguridad base

**Objetivo:** endurecer entrada/salida HTTP y controles mínimos comunes.

**Incluye:**
- Normalizar `Request` (query/body/header) y validaciones básicas.
- Asegurar `Cors` según configuración.
- Estandarizar formato de error controlado/no controlado.
- Verificar `AdminAuth`, `RateLimiter`, `Masking` y su uso base en endpoints.

**Archivos foco:**
- `src/Http/Request.php`
- `src/Http/Cors.php`
- `src/Security/AdminAuth.php`
- `src/Security/RateLimiter.php`
- `src/Security/Masking.php`
- `public/*.php`
- `admin/*.php`

**Criterio de cierre:**
- Respuestas homogéneas en éxito/error.
- Endpoints admin bloquean acceso sin bearer válido.
- No se filtran datos sensibles por error.

**Solicitud futura sugerida:**
- `implementa el segmento 1`.

---

## Segmento 2 — Licencias (resolución pública segura)

**Objetivo:** completar la resolución de licencia pública sin exponer internos.

**Incluye:**
- Implementar consulta real en `LicenseRepository`.
- Aplicar validaciones en `LicenseService`.
- Ajustar mapping público en `LicenseMapper`.
- Cerrar `public/licenses_resolve.php` con contrato final.

**Archivos foco:**
- `src/Repository/LicenseRepository.php`
- `src/Booking/LicenseService.php`
- `src/Mapping/LicenseMapper.php`
- `public/licenses_resolve.php`

**Criterio de cierre:**
- `licenseUuid` válido devuelve solo campos públicos.
- Licencia inválida/inactiva devuelve error controlado.

**Solicitud futura sugerida:**
- `implementa el segmento 2`.

---

## Segmento 3 — Disponibilidad pública (sin revelar citas)

**Objetivo:** entregar slots de disponibilidad sin filtrar información privada.

**Incluye:**
- Implementar cálculo/listado real en `AppointmentRepository::listAvailability`.
- Aplicar reglas de negocio en `AvailabilityService` + `AppointmentValidator`.
- Devolver exclusivamente slots públicos desde `public/availability.php`.

**Archivos foco:**
- `src/Repository/AppointmentRepository.php`
- `src/Booking/AvailabilityService.php`
- `src/Booking/AppointmentValidator.php`
- `public/availability.php`

**Criterio de cierre:**
- La API no devuelve detalle de citas/pacientes.
- Se respetan duración y validaciones de fecha/licencia.

**Solicitud futura sugerida:**
- `implementa el segmento 3`.

---

## Segmento 4 — Creación de cita pública

**Objetivo:** completar alta de cita con validación fuerte y respuesta mínima.

**Incluye:**
- Implementar creación real en `AppointmentRepository::create`.
- Validar payload y reglas de negocio en `AppointmentValidator` y `AppointmentService`.
- Asegurar idempotencia operativa (anti doble submit) dentro del alcance v1.
- Cerrar contrato de `public/appointments_create.php`.

**Archivos foco:**
- `src/Repository/AppointmentRepository.php`
- `src/Booking/AppointmentService.php`
- `src/Booking/AppointmentValidator.php`
- `public/appointments_create.php`

**Criterio de cierre:**
- La cita se crea con estado y campos públicos esperados.
- No se expone `appointmentId` interno.

**Solicitud futura sugerida:**
- `implementa el segmento 4`.

---

## Segmento 5 — Token público y consulta de cita propia

**Objetivo:** hacer robusto el ciclo `issue/parse` del token y consulta pública por token.

**Incluye:**
- Completar `AppointmentTokenService` (formato, expiración, validación).
- Implementar lookup real en `AppointmentRepository::findPublicById`.
- Ajustar `AppointmentService::getPublicByToken`.
- Cerrar `public/appointments_public_get.php`.

**Archivos foco:**
- `src/Security/AppointmentTokenService.php`
- `src/Repository/AppointmentRepository.php`
- `src/Booking/AppointmentService.php`
- `public/appointments_public_get.php`

**Criterio de cierre:**
- Token inválido/manipulado/expirado falla controladamente.
- Token válido devuelve solo datos mínimos y enmascarados.

**Solicitud futura sugerida:**
- `implementa el segmento 5`.

---

## Segmento 6 — Operación admin (listar, ver, actualizar, transiciones)

**Objetivo:** cerrar el flujo administrativo completo con autorización.

**Incluye:**
- Implementar `listAdmin`, `findAdminById`, `updateAdmin`, `transitionStatus`.
- Validar transiciones de estado con `StatusMapper`/reglas en servicio.
- Endurecer endpoints admin para filtros, validaciones y errores.

**Archivos foco:**
- `src/Repository/AppointmentRepository.php`
- `src/Booking/AppointmentService.php`
- `src/Mapping/StatusMapper.php`
- `admin/appointments_list.php`
- `admin/appointments_get.php`
- `admin/appointments_update.php`
- `admin/appointments_confirm.php`
- `admin/appointments_cancel.php`

**Criterio de cierre:**
- Todas las operaciones admin requieren token válido.
- Transiciones inválidas no se permiten.

**Solicitud futura sugerida:**
- `implementa el segmento 6`.

---

## Segmento 7 — Persistencia real SQL Server + transaccionalidad v1

**Objetivo:** reemplazar stubs por consultas reales manteniendo seguridad y diseño.

**Incluye:**
- Implementar queries reales en repositorios vía `DatabaseClient`.
- Definir estrategia de transacción/lock compatible con `ejecutarQueryAzureSQLServerV2()`.
- Manejar rollback/error sin filtrar detalles SQL al cliente.

**Archivos foco:**
- `src/Database/DatabaseClient.php`
- `src/Repository/LicenseRepository.php`
- `src/Repository/AppointmentRepository.php`
- `src/Booking/*Service.php`

**Criterio de cierre:**
- Sin datos mock/random en flujos principales.
- Operaciones críticas con control de concurrencia definido.

**Solicitud futura sugerida:**
- `implementa el segmento 7`.

---

## Segmento 8 — QA técnico final y hardening pre-producción

**Objetivo:** validar checklist final del documento y dejar release candidate.

**Incluye:**
- Pruebas de seguridad (token inválido, expirado, manipulado).
- Pruebas de privacidad (no exponer IDs internos, no revelar citas en disponibilidad).
- Pruebas funcionales de endpoints públicos y admin.
- Revisión final de configuración (CORS, HTTPS, secretos fuera de repo, logs).

**Archivos foco:**
- Todo el árbol API, con foco en endpoints y seguridad.
- `DOCUMENTO_FINAL_BOOKING_CITAS_API.md` (checklist de referencia).

**Criterio de cierre:**
- Checklist preproducción completado y evidenciado.
- API lista para despliegue v1.

**Solicitud futura sugerida:**
- `implementa el segmento 8`.

---

## Modo de uso recomendado (para pedir trabajo futuro)

Puedes pedir exactamente una de estas formas:

1. `implementa el segmento N`.
2. `implementa segmentos N y M`.
3. `implementa segmento N, solo backend sin tocar contratos`.
4. `implementa segmento N y deja pruebas mínimas ejecutables`.

> Nota: si pides varios segmentos juntos, se ejecutan en orden ascendente (0 -> 8) para evitar retrabajo.

---

## Matriz rápida de estado actual vs objetivo

- Estructura base y endpoints: **existen**.
- Servicios y repositorios: **existen, parcialmente con stubs/placeholders**.
- Persistencia real SQL y reglas completas: **pendiente por segmentos 2–7**.
- Hardening y QA preproducción: **pendiente por segmento 8**.

