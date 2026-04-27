# Runbook operativo corto

## 1) Incidente de tokens (público/admin)

1. Confirmar tasa de `401/403` y endpoints afectados.
2. Validar variables activas en runtime (`BOOKING_TOKEN_SECRET`, `BOOKING_ADMIN_TOKEN`, `BOOKING_TOKEN_KEY_ID`).
3. Rotar secreto/token comprometido.
4. Desplegar y verificar healthcheck + smoke tests admin/public.

## 2) Saturación (429 elevado)

1. Confirmar si el patrón es ataque o pico legítimo.
2. Ajustar temporalmente `BOOKING_RATE_LIMIT_MAX_ATTEMPTS`.
3. Validar backend distribuido (`BOOKING_RATE_LIMIT_BACKEND=database`).
4. Ejecutar rollback de límite al normal al estabilizar.

## 3) Caída de base de datos

1. Validar conectividad SQL y credenciales.
2. Poner estado degradado/controlado en monitoreo.
3. Coordinar con equipo de plataforma recuperación del motor.
4. Ejecutar pruebas de lectura/escritura al restablecer.

## 4) Recuperación post-incidente

1. Correr suite E2E completa en entorno integrado.
2. Validar métricas de error/latencia y alertas en verde.
3. Documentar RCA y acciones preventivas.
