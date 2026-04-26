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

## Variables de entorno recomendadas

- `BOOKING_TOKEN_SECRET`
- `BOOKING_TOKEN_KEY_ID`
- `BOOKING_ADMIN_TOKEN`

## Nota

Esta base crea el esqueleto y contratos principales para evolucionar la implementación real contra SQL Server/Azure SQL sin exponer identificadores internos.
