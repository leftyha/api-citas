# Booking API (PHP 7.4) - Final

API de reservas funcional con estructura simplificada (sin carpetas de código), manteniendo operaciones públicas y administrativas.

## Archivos
- `api.php`: router y endpoints
- `api_core.php`: utilidades, seguridad, DB y helpers
- `.env.example`: configuración base

## Requisitos
- PHP 7.4+
- MySQL/MariaDB
- Tablas existentes: `booking_licenses`, `booking_appointments`

## Configuración
1. Copiar `.env.example` a `.env`
2. Ajustar variables
3. Ejecutar:
```bash
php -S 0.0.0.0:8000
```

## Endpoints (compatibilidad funcional)
### Público
- `GET /api.php?action=availability&licenseUuid={uuid}&date=YYYY-MM-DD&durationMinutes=30`
- `POST /api.php?action=appointments.create`
- `GET /api.php?action=appointments.get&token={token}`

### Admin (header `X-Admin-Key`)
- `GET /api.php?action=admin.appointments.list`
- `GET /api.php?action=admin.appointments.get&appointmentId={id}`
- `PUT /api.php?action=admin.appointments.update&appointmentId={id}`
- `POST /api.php?action=admin.appointments.confirm&appointmentId={id}`
- `POST /api.php?action=admin.appointments.cancel&appointmentId={id}`

## Formato de respuesta
Éxito:
```json
{"success": true, "message": "...", "data": {}}
```
Error:
```json
{"success": false, "errorCode": "...", "message": "...", "errors": [], "requestId": "..."}
```
