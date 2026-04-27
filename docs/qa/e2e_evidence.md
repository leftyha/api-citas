# Evidencia E2E (entorno integrado SQL real)

Fecha de ejecución: _pendiente_
Entorno: _qa / staging_
Responsable: _pendiente_

## Precondiciones

- Dataset mínimo cargado con `docs/qa/min_dataset.sql`.
- `BOOKING_TOKEN_SECRET` y `BOOKING_ADMIN_TOKEN` configurados con valores no default.
- Rate limit backend en `database`.

## Evidencia por endpoint

| Endpoint | Método | Resultado esperado | Evidencia |
|---|---|---|---|
| `/public/appointments_create.php` | POST | 201 + `appointmentToken` | Pendiente |
| `/public/appointments_public_get.php` | GET/POST | 200 + datos mínimos enmascarados | Pendiente |
| `/admin/appointments_list.php` | GET | 200 con `items[]` | Pendiente |
| `/admin/appointments_get.php` | GET | 200 detalle por `appointmentId` | Pendiente |
| `/admin/appointments_update.php` | PATCH | 200 + actualización aplicada | Pendiente |
| `/admin/appointments_confirm.php` | POST | 200 + transición válida | Pendiente |
| `/admin/appointments_cancel.php` | POST | 200 + transición válida | Pendiente |

## Casos críticos mínimos

- Conflicto de horario (`SLOT_CONFLICT`).
- Token inválido (`APPOINTMENT_NOT_FOUND`).
- Token expirado (`APPOINTMENT_NOT_FOUND`).
- Transición inválida (`INVALID_STATUS_TRANSITION`).
