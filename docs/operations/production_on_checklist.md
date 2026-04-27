# Checklist de criterios para producción ON

- [ ] Métodos HTTP cerrados en todos los endpoints según contrato.
- [ ] Secretos productivos activos (sin defaults de desarrollo).
- [ ] Rate limiting distribuido habilitado y validado bajo carga.
- [ ] Pruebas E2E verdes (flujo público + admin) en SQL real.
- [ ] Casos críticos validados: conflicto horario, token inválido/expirado, transición inválida.
- [ ] No exposición de datos internos en endpoints públicos.
- [ ] Logs estructurados + requestId en observabilidad central.
- [ ] Alertas operativas activas con umbrales definidos.
- [ ] Runbook operativo aprobado por equipo técnico.
- [ ] Checklist de despliegue firmado (configuración, BD, healthcheck, rollback).
