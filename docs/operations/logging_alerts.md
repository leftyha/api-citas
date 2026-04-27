# Logging estructurado y alertas mínimas

## Logging obligatorio

Formato JSON por línea con los campos:

- `timestamp` (ISO-8601 UTC)
- `level` (`info`, `warning`, `error`)
- `event` (nombre estable)
- `requestId` (siempre presente)
- `context` (objeto)

Eventos críticos mínimos:

- Errores no controlados (`*_unhandled`).
- Rechazos de autenticación admin (`401/403`).
- Exceso de rate limit (`429`).
- Fallas de base de datos.

## Alertas operativas mínimas

1. **Errores 5xx**
   - Umbral: > 2% por 5 minutos.
2. **401/403 anómalos**
   - Umbral: incremento > 3x del baseline por 10 minutos.
3. **429 anómalos**
   - Umbral: > 10% de requests por endpoint por 10 minutos.
4. **Latencia p95/p99**
   - Umbral: p95 > 800ms o p99 > 1500ms por 5 minutos.

## Métricas sugeridas

- `http_requests_total{endpoint,method,status}`
- `http_request_duration_ms_bucket{endpoint}`
- `booking_rate_limit_exceeded_total{endpoint}`
- `booking_admin_auth_fail_total{endpoint}`
