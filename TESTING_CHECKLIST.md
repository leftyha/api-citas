# Set de pruebas completo (end-to-end + regresión)

Este checklist cubre flujos públicos, administrativos, seguridad, validaciones y regresión técnica de la API.

## 1) Precondiciones

- Configurar variables de entorno (`BOOKING_*`) y una licencia activa en DB.
- Definir:
  - `BASE_URL` (ej. `http://localhost:8000`)
  - `LICENSE_UUID` (licencia activa)
  - `ADMIN_TOKEN` (igual a `BOOKING_ADMIN_TOKEN`)
- Usar una fecha futura en `TEST_DATE` y un `START_AT` válido con zona horaria.

## 2) Pruebas automatizadas (sin tocar endpoint HTTP)

```bash
php scripts/test_business_functional.php
php scripts/test_full_regression.php
```

## 3) Pruebas públicas (HTTP)

### 3.1 Resolver licencia
```bash
curl -sS "$BASE_URL/public/licenses_resolve.php?licenseUuid=$LICENSE_UUID"
```
**Esperado:** `ok=true`, `data.license.bookingEnabled=true`.

### 3.2 Consultar disponibilidad
```bash
curl -sS "$BASE_URL/public/availability.php?licenseUuid=$LICENSE_UUID&date=$TEST_DATE&durationMinutes=30"
```
**Esperado:** `ok=true`, `data.slots[]` con `startAt/endAt` solamente.

### 3.3 Crear cita pública
```bash
curl -sS -X POST "$BASE_URL/public/appointments_create.php" \
  -H 'Content-Type: application/json' \
  -d '{
    "licenseUuid":"'"$LICENSE_UUID"'",
    "serviceType":"consulta",
    "professionalId":12,
    "startAt":"'"$START_AT"'",
    "durationMinutes":30,
    "customer":{
      "name":"Paciente Prueba",
      "document":"V12345678",
      "phone":"+584121234567",
      "email":"paciente@example.com"
    }
  }'
```
**Esperado:** `ok=true` y `data.appointment.appointmentToken` con prefijo `apt_`.

### 3.4 Consultar cita por token público
```bash
curl -sS "$BASE_URL/public/appointments_public_get.php?appointmentToken=$APPOINTMENT_TOKEN"
```
**Esperado:** documento/teléfono enmascarados en respuesta pública.

## 4) Pruebas admin

### 4.1 Listado
```bash
curl -sS "$BASE_URL/admin/appointments_list.php?date=$TEST_DATE" \
  -H "Authorization: Bearer $ADMIN_TOKEN"
```

### 4.2 Detalle
```bash
curl -sS "$BASE_URL/admin/appointments_get.php?appointmentId=$APPOINTMENT_ID" \
  -H "Authorization: Bearer $ADMIN_TOKEN"
```

### 4.3 Confirmar cita
```bash
curl -sS -X POST "$BASE_URL/admin/appointments_confirm.php" \
  -H "Authorization: Bearer $ADMIN_TOKEN" \
  -H 'Content-Type: application/json' \
  -d '{"appointmentId":'"$APPOINTMENT_ID"'}'
```

### 4.4 Cancelar cita
```bash
curl -sS -X POST "$BASE_URL/admin/appointments_cancel.php" \
  -H "Authorization: Bearer $ADMIN_TOKEN" \
  -H 'Content-Type: application/json' \
  -d '{"appointmentId":'"$APPOINTMENT_ID"',"reason":"Paciente no asistió"}'
```

### 4.5 Actualizar cita
```bash
curl -sS -X PUT "$BASE_URL/admin/appointments_update.php" \
  -H "Authorization: Bearer $ADMIN_TOKEN" \
  -H 'Content-Type: application/json' \
  -d '{"appointmentId":'"$APPOINTMENT_ID"',"notes":"Reprogramada por llamada"}'
```

## 5) Pruebas negativas clave

- Token admin inválido o faltante → `UNAUTHORIZED` (401).
- Método incorrecto en cada endpoint (ej. GET en create) → `METHOD_NOT_ALLOWED` (405).
- JSON inválido o `Content-Type` incorrecto → `VALIDATION_ERROR`/`UNSUPPORTED_MEDIA_TYPE`.
- `licenseUuid`, `date`, `startAt`, `durationMinutes` inválidos → `VALIDATION_ERROR`.
- Colisión de horario al crear cita → `SLOT_CONFLICT` (409).
- Uso de token público manipulado/expirado → `APPOINTMENT_NOT_FOUND` (404).
- Superar umbral de rate limit por IP/license → `RATE_LIMIT_EXCEEDED` (429).

## 6) Regresión de seguridad y despliegue

- Verificar `BOOKING_STRICT_DEPLOY=true` en producción.
- Confirmar que `BOOKING_TOKEN_SECRET` y `BOOKING_ADMIN_TOKEN` no usen defaults.
- Revisar CORS según ambientes (`BOOKING_CORS_ALLOWED_ORIGINS`).
- Confirmar logs de errores en `BOOKING_LOG_PATH`.

## 7) Criterio de salida (go/no-go)

- ✅ Pasan suites: `test_business_functional` + `test_full_regression`.
- ✅ Flujos público y admin end-to-end en ambiente de staging.
- ✅ Pruebas negativas devuelven `code/status` esperados.
- ✅ Sin errores críticos en logs durante corrida completa.
