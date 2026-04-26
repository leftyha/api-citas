# Documento final — Booking Citas API en PHP

## 1. Objetivo

Construir una API en **PHP clásico** para gestionar citas desde una web pública, usando la base de datos existente y sin exponer nombres reales de tablas, columnas ni identificadores internos.

La API debe permitir:

```txt
1. Validar una licencia pública.
2. Mostrar horarios disponibles sin revelar detalles de citas existentes.
3. Crear citas de forma controlada y segura.
4. Entregar un token público seguro para que el usuario valide su propia cita.
5. Permitir que solo usuarios/admin autorizados puedan listar, consultar y gestionar citas completas.
6. Evitar abuso, doble creación, doble submit y exposición de información sensible.
7. No modificar la base de datos en la primera versión.
8. Mantener una arquitectura fácil de migrar posteriormente a Laminas/Zend.
```

---

## 2. Decisiones principales

### 2.1 Tecnología

```txt
Backend: PHP clásico
Base de datos: SQL Server / Azure SQL
Formato: JSON
Ubicación API: /ws/dashboard-usd/api/
Función DB existente: ejecutarQueryAzureSQLServerV2()
```

### 2.2 No usar Zend/Laminas en primera versión

No se usará Zend Framework/Laminas para esta primera versión.

Motivos:

```txt
1. El flujo inicial es pequeño y controlado.
2. Ya existe una función obligatoria para conexión SQL.
3. La estructura de despliegue ya está definida.
4. Un framework completo agregaría complejidad innecesaria para la primera entrega.
5. PHP clásico con servicios, repositorios y helpers bien separados es suficiente.
```

Sin embargo, el código debe escribirse de forma modular para permitir migración futura a Laminas/Zend sin rehacer la lógica de negocio.

Regla principal:

```txt
Endpoint PHP delgado -> Service -> Repository -> DatabaseClient -> ejecutarQueryAzureSQLServerV2()
```

Evitar:

```txt
Endpoint PHP -> SQL directo -> echo json_encode() -> exit
```

### 2.3 No exponer estructura de base de datos

El frontend nunca debe conocer:

```txt
- nombres reales de tablas
- nombres reales de columnas
- identificadores internos de licencia
- identificadores internos de cita en endpoints públicos
- errores SQL
- estructura interna de la base de datos
```

El API usará nombres públicos de negocio:

```txt
licenseUuid
appointmentToken
customerDocument
startAt
endAt
status
serviceType
professionalId
```

### 2.4 No modificar la base de datos en v1

La primera versión no debe requerir cambios estructurales en la base de datos.

No se requiere inicialmente:

```txt
ALTER TABLE
nuevas columnas
nuevas tablas
índices nuevos
triggers nuevos
procedimientos nuevos
```

Limitaciones aceptadas de esta decisión:

```txt
1. El token público no podrá revocarse individualmente si no se guarda.
2. El rate limit por IP puede ser limitado si no hay almacenamiento disponible.
3. La auditoría puede depender de logs de aplicación.
4. No se podrá crear una restricción única de BD para impedir duplicados.
5. La protección contra concurrencia deberá resolverse con transacciones y locks.
```

---

## 3. Arquitectura recomendada

### 3.1 Estructura física recomendada

```txt
/ws/dashboard-usd/api/
├── bootstrap.php
├── public/
│   ├── licenses_resolve.php
│   ├── availability.php
│   ├── appointments_create.php
│   └── appointments_public_get.php
├── admin/
│   ├── appointments_list.php
│   ├── appointments_get.php
│   ├── appointments_update.php
│   ├── appointments_confirm.php
│   └── appointments_cancel.php
├── src/
│   ├── Http/
│   │   ├── Request.php
│   │   ├── JsonResponse.php
│   │   ├── ApiException.php
│   │   └── Cors.php
│   ├── Security/
│   │   ├── AppointmentTokenService.php
│   │   ├── AdminAuth.php
│   │   ├── RateLimiter.php
│   │   └── Masking.php
│   ├── Booking/
│   │   ├── LicenseService.php
│   │   ├── AvailabilityService.php
│   │   ├── AppointmentService.php
│   │   └── AppointmentValidator.php
│   ├── Repository/
│   │   ├── LicenseRepository.php
│   │   └── AppointmentRepository.php
│   ├── Mapping/
│   │   ├── StatusMapper.php
│   │   ├── AppointmentMapper.php
│   │   └── LicenseMapper.php
│   ├── Database/
│   │   └── DatabaseClient.php
│   └── Config/
│       └── config.php
└── logs/
```

### 3.2 Responsabilidades

#### Endpoints

Los archivos `.php` públicos y admin deben encargarse solo de:

```txt
1. Cargar bootstrap.php.
2. Leer request.
3. Llamar al service correspondiente.
4. Devolver JsonResponse.
5. Capturar errores controlados.
```

No deben contener:

```txt
SQL directo
reglas de negocio complejas
cifrado manual
mapeo de columnas reales
validaciones duplicadas
```

#### Services

Los services contienen reglas de negocio:

```txt
LicenseService
AvailabilityService
AppointmentService
AppointmentValidator
```

Ejemplos:

```txt
validar licencia
validar horario
validar ventana de reserva
validar conflicto
crear cita
confirmar cita
cancelar cita
validar transición de estado
```

#### Repositories

Los repositories son la única capa que conoce:

```txt
nombres reales de tablas
nombres reales de columnas
consultas SQL
mapeos de BD
función ejecutarQueryAzureSQLServerV2()
```

#### DatabaseClient

La función existente `ejecutarQueryAzureSQLServerV2()` debe envolverse para evitar acoplarla a todo el sistema.

Ejemplo conceptual:

```php
final class DatabaseClient
{
    public function query(string $sql, array $params = []): array
    {
        return ejecutarQueryAzureSQLServerV2($sql, $params);
    }

    public function beginTransaction(): void
    {
        // Implementar según capacidades reales disponibles.
    }

    public function commit(): void
    {
        // Implementar según capacidades reales disponibles.
    }

    public function rollback(): void
    {
        // Implementar según capacidades reales disponibles.
    }
}
```

Si la función actual no soporta transacciones, se debe validar cómo ejecutar `BEGIN TRANSACTION`, `COMMIT` y `ROLLBACK` manteniendo la misma conexión.

Esto es crítico para evitar doble creación de citas.

---

## 4. Conceptos principales

### 4.1 Licencia pública

El frontend enviará:

```txt
licenseUuid
```

Ese valor representa el identificador público/encriptado de la licencia.

Flujo:

```txt
licenseUuid recibido
↓
backend busca licencia internamente
↓
backend obtiene id interno
↓
backend usa ese id interno para operar citas
```

El id interno nunca se devuelve al frontend público.

### 4.2 Cita pública

El frontend público no recibirá el id interno de la cita.

En su lugar recibirá:

```txt
appointmentToken
```

Ejemplo:

```txt
apt_xxxxx
```

Ese token permite consultar una cita específica sin exponer datos internos ni permitir listar citas ajenas.

---

## 5. Token público de cita

### 5.1 Objetivo del token

El token sirve para que un usuario pueda validar su propia cita sin poder listar ni descubrir citas de otras personas.

Ejemplo de uso recomendado:

```http
POST /ws/dashboard-usd/api/public/appointments_public_get.php
Content-Type: application/json

{
  "appointmentToken": "apt_xxxxx"
}
```

También se puede soportar `GET` por simplicidad, pero no es la opción más segura porque el token puede quedar en logs, historial del navegador o cabeceras Referer.

Si se usa `GET`, no se debe registrar el query string completo.

### 5.2 Requisitos del token

El token debe ser:

```txt
1. No adivinable.
2. No reversible fácilmente por terceros.
3. No debe exponer appointmentId en claro.
4. No debe exponer licencia interna.
5. Debe estar autenticado.
6. Debe estar cifrado.
7. Debe tener expiración.
8. Debe poder validarse sin guardar nada en la base de datos.
9. Debe soportar versión de formato.
10. Debe permitir rotación futura de claves.
```

### 5.3 Diseño elegido

Como no se modificará la base de datos, se usará:

```txt
Token cifrado + autenticado + con expiración
```

Conceptualmente:

```txt
appointmentToken = apt_ + encryptedAuthenticatedPayload
```

Primitivas permitidas:

```txt
1. libsodium secretbox, preferido si está disponible.
2. AES-256-GCM si libsodium no está disponible.
```

No usar:

```txt
base64 simple
md5
sha1
cifrado casero
appointmentId reversible sin cifrado
hash sin cifrado
```

### 5.4 Payload interno del token

El token puede contener internamente, cifrado:

```json
{
  "v": 1,
  "kid": "booking-token-2026-01",
  "appointmentId": 2451,
  "licenseUuid": "abc123",
  "iat": 1777200000,
  "exp": 1784976000
}
```

Campos:

| Campo           | Descripción                                     |
| --------------- | ----------------------------------------------- |
| `v`             | Versión del formato del token                   |
| `kid`           | Identificador de clave para rotación futura     |
| `appointmentId` | Id interno de la cita, cifrado dentro del token |
| `licenseUuid`   | Licencia pública asociada                       |
| `iat`           | Fecha de emisión Unix timestamp                 |
| `exp`           | Fecha de expiración Unix timestamp              |

Este contenido no debe ser visible para el frontend.

### 5.5 Validación del token

Cuando el usuario consulta su cita:

```txt
1. El backend recibe appointmentToken.
2. Verifica formato apt_...
3. Decodifica el contenido.
4. Valida autenticidad/integridad.
5. Descifra el payload.
6. Valida versión.
7. Valida expiración.
8. Extrae appointmentId y licenseUuid.
9. Resuelve internamente la licencia.
10. Busca internamente la cita.
11. Verifica que la cita pertenece a esa licencia.
12. Devuelve solo datos públicos mínimos.
```

### 5.6 Respuesta ante token inválido

No diferenciar públicamente entre:

```txt
token inválido
token expirado
firma incorrecta
cita inexistente
licencia inexistente
cita de otra licencia
```

Respuesta única:

```json
{
  "ok": false,
  "code": "APPOINTMENT_NOT_FOUND",
  "message": "No se encontró una cita válida.",
  "errors": []
}
```

Esto evita dar pistas a atacantes.

### 5.7 Limitaciones de no guardar token en BD

Como el token no se guarda en base de datos:

```txt
1. No se puede revocar individualmente.
2. Si el usuario pierde el token, no se puede recuperar exactamente el mismo token desde BD.
3. Si cambia la clave secreta sin mantener claves anteriores, los tokens antiguos dejan de funcionar.
4. El token debe entregarse correctamente al usuario al crear la cita.
5. No se puede auditar uso de token salvo por logs de aplicación.
```

Esta limitación es aceptable para primera versión.

### 5.8 Claves secretas

El backend debe tener una clave secreta fuera del repo.

Ejemplo conceptual:

```txt
BOOKING_TOKEN_SECRET=<clave-larga-aleatoria>
BOOKING_TOKEN_KEY_ID=booking-token-2026-01
```

Reglas:

```txt
1. No subir al repo.
2. No poner en frontend.
3. No imprimir en logs.
4. No registrar accidentalmente en errores.
5. Guardar en archivo privado del servidor o variable de entorno.
6. Debe ser larga, aleatoria y difícil de adivinar.
7. Debe existir procedimiento manual de rotación.
```

---

## 6. Separación de API pública y API admin

### 6.1 API pública

La API pública puede:

```txt
1. Validar licencia.
2. Mostrar disponibilidad.
3. Crear cita.
4. Consultar una cita usando appointmentToken.
```

La API pública no puede:

```txt
1. Listar citas.
2. Ver datos de otros pacientes.
3. Ver detalles de citas ocupadas.
4. Ver appointmentId interno.
5. Ver id interno de licencia.
6. Cambiar citas sin token o autorización.
7. Saber si un appointmentId existe.
```

### 6.2 API admin

La API admin puede:

```txt
1. Listar citas.
2. Ver detalles completos.
3. Confirmar citas.
4. Reprogramar citas.
5. Cancelar citas.
6. Filtrar por fecha, estado, profesional o paciente.
```

Debe requerir autorización.

Primera versión recomendada:

```http
Authorization: Bearer <admin-token>
```

Limitaciones del admin token estático:

```txt
1. No identifica usuario individual.
2. No permite roles granulares.
3. No permite revocación por usuario.
4. No deja auditoría fuerte por persona.
```

Es aceptable para v1 si el token es largo, secreto, rotado manualmente y validado con comparación segura.

Para v2 se recomienda integrar autenticación real del dashboard o un esquema de usuarios/roles.

---

## 7. Base path

```txt
/ws/dashboard-usd/api/
```

Endpoints públicos recomendados:

```txt
/ws/dashboard-usd/api/public/...
```

Endpoints admin:

```txt
/ws/dashboard-usd/api/admin/...
```

Si por compatibilidad se requiere no usar `/public/`, los nombres de archivos pueden mantenerse en la raíz, pero la separación lógica debe conservarse.

---

## 8. Headers

### 8.1 Headers públicos

```http
Content-Type: application/json
X-Request-Id: uuid-v4
X-Channel: web
```

`X-Request-Id` puede ser generado por el backend si el cliente no lo envía.

### 8.2 Headers admin

```http
Content-Type: application/json
Authorization: Bearer <admin-token>
X-Request-Id: uuid-v4
X-Channel: admin
```

### 8.3 Header opcional para doble submit

Recomendado para creación de citas:

```http
Idempotency-Key: uuid-v4
```

Si no se puede persistir formalmente por no modificar BD, al menos se debe seguir validando duplicado por:

```txt
misma licencia
mismo customerDocument
mismo startAt
estado activo
```

---

## 9. Canales permitidos

```txt
web
call-center
store
admin
internal
```

Para primera versión pública:

```txt
web
```

El backend debe validar que el canal recibido pertenece a la lista permitida.

---

## 10. Respuesta estándar

### 10.1 Éxito

```json
{
  "ok": true,
  "message": "Operación realizada correctamente.",
  "data": {}
}
```

### 10.2 Error

```json
{
  "ok": false,
  "code": "ERROR_CODE",
  "message": "Mensaje claro para frontend.",
  "errors": []
}
```

### 10.3 Reglas de respuesta

```txt
1. Siempre responder JSON.
2. No devolver errores SQL al cliente.
3. No devolver stack traces.
4. No devolver nombres reales de tablas o columnas.
5. No mezclar formatos de error.
6. No usar mensajes técnicos en endpoints públicos.
```

---

## 11. Códigos de error

| HTTP | Código                      | Uso                                    |
| ---: | --------------------------- | -------------------------------------- |
|  400 | `VALIDATION_ERROR`          | Datos inválidos o incompletos          |
|  401 | `UNAUTHORIZED`              | Falta autorización admin               |
|  403 | `FORBIDDEN`                 | Token válido pero sin permiso          |
|  404 | `LICENSE_NOT_FOUND`         | Licencia no encontrada o no disponible |
|  404 | `APPOINTMENT_NOT_FOUND`     | Cita no encontrada o token inválido    |
|  409 | `SLOT_CONFLICT`             | Horario ocupado                        |
|  409 | `BOOKING_LIMIT_REACHED`     | Límite de creación alcanzado           |
|  409 | `INVALID_STATUS_TRANSITION` | Cambio de estado no permitido          |
|  415 | `UNSUPPORTED_MEDIA_TYPE`    | No se envió JSON                       |
|  429 | `RATE_LIMITED`              | Demasiados intentos                    |
|  500 | `INTERNAL_ERROR`            | Error inesperado                       |

---

## 12. Estados públicos de cita

Estados públicos permitidos:

```txt
pending
confirmed
cancelled
completed
no_show
```

Primera versión mínima:

```txt
pending
confirmed
cancelled
```

| Estado      | Significado                            |
| ----------- | -------------------------------------- |
| `pending`   | Cita creada, pendiente de confirmación |
| `confirmed` | Cita confirmada                        |
| `cancelled` | Cita cancelada                         |
| `completed` | Cita atendida                          |
| `no_show`   | Paciente no asistió                    |

El backend mapeará estos estados al formato interno de la base.

### 12.1 Estados activos

Para validar conflictos, se consideran estados activos:

```txt
pending
confirmed
```

Estados no activos:

```txt
cancelled
completed
no_show
```

---

## 13. Fecha, hora y zona horaria

### 13.1 Regla general

El frontend debe enviar fechas en formato ISO-8601 con offset:

```txt
2026-05-04T10:30:00-04:00
```

El backend debe:

```txt
1. Validar que la fecha sea válida.
2. Validar que incluya hora.
3. Validar o normalizar zona horaria.
4. Convertir internamente a una representación consistente.
5. Responder también en ISO-8601.
```

### 13.2 Zona horaria oficial

Debe definirse una zona horaria oficial por negocio/licencia.

Ejemplo:

```txt
America/Caracas
```

Si la BD ya tiene zona horaria por licencia, se debe usar esa fuente.

Si no existe, v1 usará una zona horaria por defecto definida en configuración.

---

## 14. Endpoint público: resolver licencia

### Ruta

```http
GET /ws/dashboard-usd/api/public/licenses_resolve.php?licenseUuid={licenseUuid}
```

Ruta compatible si no se usa `/public/`:

```http
GET /ws/dashboard-usd/api/licenses_resolve.php?licenseUuid={licenseUuid}
```

### Objetivo

Validar que una licencia existe y que puede recibir citas web.

### Query params

| Campo         | Tipo   | Obligatorio | Descripción                       |
| ------------- | ------ | ----------: | --------------------------------- |
| `licenseUuid` | string |          sí | Identificador público de licencia |

### Respuesta exitosa

```json
{
  "ok": true,
  "message": "Licencia disponible.",
  "data": {
    "license": {
      "licenseUuid": "abc123",
      "licenseName": "Óptica Demo",
      "logoUrl": "https://dominio.com/logo.png",
      "bookingEnabled": true
    }
  }
}
```

### Respuesta si no existe o no está disponible

```json
{
  "ok": false,
  "code": "LICENSE_NOT_FOUND",
  "message": "Licencia no encontrada.",
  "errors": []
}
```

### Reglas

```txt
1. No devolver id interno.
2. No devolver nombres reales de base de datos.
3. No devolver detalles técnicos.
4. Si la licencia no existe o no puede reservar, responder genérico.
5. Validar formato de licenseUuid.
```

---

## 15. Endpoint público: disponibilidad

### Ruta

```http
GET /ws/dashboard-usd/api/public/availability.php
```

Ruta compatible:

```http
GET /ws/dashboard-usd/api/availability.php
```

### Objetivo

Listar horarios disponibles sin revelar detalles de las citas existentes.

### Query params

| Campo             | Tipo   | Obligatorio | Descripción                               |
| ----------------- | ------ | ----------: | ----------------------------------------- |
| `licenseUuid`     | string |          sí | Identificador público de licencia         |
| `date`            | date   |          sí | Fecha a consultar en formato `YYYY-MM-DD` |
| `durationMinutes` | number |          no | Duración deseada                          |
| `serviceType`     | string |          no | Tipo de servicio                          |
| `professionalId`  | number |          no | Profesional específico                    |

### Ejemplo

```http
GET /ws/dashboard-usd/api/public/availability.php?licenseUuid=abc123&date=2026-05-04&durationMinutes=30
```

### Respuesta

```json
{
  "ok": true,
  "message": "Disponibilidad obtenida correctamente.",
  "data": {
    "date": "2026-05-04",
    "durationMinutes": 30,
    "slots": [
      {
        "startAt": "2026-05-04T09:00:00-04:00",
        "endAt": "2026-05-04T09:30:00-04:00",
        "available": true
      },
      {
        "startAt": "2026-05-04T09:30:00-04:00",
        "endAt": "2026-05-04T10:00:00-04:00",
        "available": false
      }
    ]
  }
}
```

### No debe devolver

```txt
appointmentId
appointmentToken
customerDocument
customerName
customerPhone
customerEmail
notes
cantidad de citas en el horario
estado exacto de citas ocupadas
datos de pacientes
```

### Regla de seguridad

La disponibilidad solo muestra:

```txt
horario + available true/false
```

Nunca detalles de la cita ni del paciente.

### Regla sobre lecturas inconsistentes

No usar `WITH (NOLOCK)` para cálculos de disponibilidad si el resultado se usará inmediatamente para crear una cita.

Puede usarse únicamente en reportes o consultas informativas donde una lectura inconsistente no afecte la seguridad ni la creación de citas.

---

## 16. Endpoint público: crear cita

### Ruta

```http
POST /ws/dashboard-usd/api/public/appointments_create.php
```

Ruta compatible:

```http
POST /ws/dashboard-usd/api/appointments_create.php
```

### Body recomendado

```json
{
  "licenseUuid": "abc123",
  "customerDocument": "12345678",
  "customerName": "Ana Pérez",
  "customerPhone": "+584121234567",
  "customerEmail": "ana@email.com",
  "startAt": "2026-05-04T10:30:00-04:00",
  "durationMinutes": 30,
  "serviceType": "optometry",
  "professionalId": 15,
  "notes": "Prefiere atención en español"
}
```

### Body mínimo

```json
{
  "licenseUuid": "abc123",
  "customerDocument": "12345678",
  "customerName": "Ana Pérez",
  "customerPhone": "+584121234567",
  "startAt": "2026-05-04T10:30:00-04:00"
}
```

### Campos

| Campo              | Tipo     |    Obligatorio | Descripción                   |
| ------------------ | -------- | -------------: | ----------------------------- |
| `licenseUuid`      | string   |             sí | Licencia pública              |
| `customerDocument` | string   |             sí | Documento/cédula del paciente |
| `customerName`     | string   | recomendado/sí | Nombre del paciente           |
| `customerPhone`    | string   | recomendado/sí | Teléfono del paciente         |
| `customerEmail`    | string   |             no | Correo del paciente           |
| `startAt`          | datetime |             sí | Fecha/hora de la cita         |
| `durationMinutes`  | number   |             no | Duración                      |
| `serviceType`      | string   |             no | Servicio                      |
| `professionalId`   | number   |             no | Profesional                   |
| `notes`            | string   |             no | Observaciones                 |

### Validaciones de formato

```txt
1. Content-Type debe ser application/json.
2. licenseUuid es obligatorio.
3. customerDocument es obligatorio.
4. customerName recomendado como obligatorio.
5. customerPhone recomendado como obligatorio.
6. startAt es obligatorio.
7. startAt debe ser fecha válida.
8. startAt debe incluir hora y zona horaria u offset.
9. startAt no puede estar en el pasado.
10. startAt debe estar dentro de la ventana de reserva permitida.
11. durationMinutes debe ser positivo si se envía.
12. professionalId debe ser entero positivo si se envía.
13. serviceType debe pertenecer a valores permitidos si se valida catálogo.
14. notes debe tener límite de longitud.
15. customerEmail debe ser válido si se envía.
```

### Límites recomendados de campos

```txt
licenseUuid: 3-120 caracteres
customerDocument: 5-30 caracteres
customerName: 2-120 caracteres
customerPhone: 7-25 caracteres
customerEmail: máximo 254 caracteres
notes: máximo 500 o 1000 caracteres
serviceType: 2-50 caracteres
durationMinutes: valores permitidos, por ejemplo 15, 30, 45, 60
```

### Controles obligatorios antes de crear

```txt
1. Validar licencia existente.
2. Validar que la licencia permite booking.
3. Validar horario laboral.
4. Validar ventana de reserva.
5. Validar disponibilidad.
6. Validar conflicto de horario.
7. Validar límite por IP si hay mecanismo disponible.
8. Validar límite por documento.
9. Validar límite por teléfono.
10. Validar doble submit.
11. Crear cita con estado pending.
12. Generar appointmentToken.
13. Devolver solo datos mínimos.
```

### Regla de conflicto inicial

Existe conflicto si ya hay una cita activa con:

```txt
misma licencia
misma fecha/hora
mismo profesional, si professionalId fue enviado
estado activo
```

Estados activos:

```txt
pending
confirmed
```

Estados no activos:

```txt
cancelled
completed
no_show
```

### Regla de transacción y concurrencia

La creación de citas debe hacerse dentro de una transacción.

Flujo recomendado:

```txt
BEGIN TRANSACTION

1. Resolver licencia.
2. Validar configuración de booking.
3. Validar ventana de reserva.
4. Consultar conflicto usando locks adecuados.
5. Si existe conflicto, ROLLBACK y responder SLOT_CONFLICT.
6. Insertar cita.
7. Obtener appointmentId interno.
8. COMMIT.
9. Generar appointmentToken.
10. Responder 201 Created.
```

Para SQL Server, la consulta final de conflicto antes de insertar debe usar una estrategia consistente y protegida.

Ejemplo conceptual:

```sql
SELECT ...
FROM citas WITH (UPDLOCK, HOLDLOCK)
WHERE licencia_id = @licenseId
  AND fecha_inicio = @startAt
  AND estado IN (...)
```

No usar `WITH (NOLOCK)` en la validación final de conflicto.

Si no se puede garantizar transacción con la función DB existente, se debe documentar como riesgo crítico antes de salir a producción.

### Respuesta exitosa

HTTP:

```http
201 Created
```

Body:

```json
{
  "ok": true,
  "message": "Cita creada correctamente.",
  "data": {
    "appointment": {
      "appointmentToken": "apt_xxxxx",
      "startAt": "2026-05-04T10:30:00-04:00",
      "endAt": "2026-05-04T11:00:00-04:00",
      "durationMinutes": 30,
      "serviceType": "optometry",
      "status": "pending"
    }
  }
}
```

### No debe devolver

```txt
appointmentId interno
id interno de licencia
datos completos innecesarios
nombres reales de BD
errores SQL
```

---

## 17. Endpoint público: validar cita propia

### Ruta recomendada

```http
POST /ws/dashboard-usd/api/public/appointments_public_get.php
```

Body:

```json
{
  "appointmentToken": "apt_xxxxx"
}
```

Ruta compatible por GET:

```http
GET /ws/dashboard-usd/api/public/appointments_public_get.php?appointmentToken={appointmentToken}
```

Si se soporta GET, el backend no debe registrar el query string completo.

### Objetivo

Permitir que un usuario valide su propia cita usando el token recibido al crearla.

### Respuesta exitosa

```json
{
  "ok": true,
  "message": "Cita encontrada.",
  "data": {
    "appointment": {
      "appointmentToken": "apt_xxxxx",
      "startAt": "2026-05-04T10:30:00-04:00",
      "endAt": "2026-05-04T11:00:00-04:00",
      "durationMinutes": 30,
      "serviceType": "optometry",
      "status": "pending",
      "customer": {
        "documentMasked": "******78",
        "phoneMasked": "*******567"
      }
    }
  }
}
```

### Si el token es inválido, expiró o la cita no existe

```json
{
  "ok": false,
  "code": "APPOINTMENT_NOT_FOUND",
  "message": "No se encontró una cita válida.",
  "errors": []
}
```

---

## 18. Endpoints admin

Todos los endpoints admin requieren:

```http
Authorization: Bearer <admin-token>
```

La validación del token admin debe ocurrir antes de cualquier consulta a la base de datos.

### 18.1 Listar citas

```http
GET /ws/dashboard-usd/api/admin/appointments_list.php
```

Query params:

| Campo              | Obligatorio | Descripción   |
| ------------------ | ----------: | ------------- |
| `licenseUuid`      |          sí | Licencia      |
| `dateFrom`         |          no | Fecha inicial |
| `dateTo`           |          no | Fecha final   |
| `status`           |          no | Estado        |
| `professionalId`   |          no | Profesional   |
| `customerDocument` |          no | Documento     |
| `limit`            |          no | Límite        |
| `offset`           |          no | Paginación    |

### Respuesta

```json
{
  "ok": true,
  "message": "Citas obtenidas correctamente.",
  "data": {
    "items": [
      {
        "appointmentId": 2451,
        "customerDocument": "12345678",
        "customerName": "Ana Pérez",
        "customerPhone": "+584121234567",
        "customerEmail": "ana@email.com",
        "startAt": "2026-05-04T10:30:00-04:00",
        "endAt": "2026-05-04T11:00:00-04:00",
        "durationMinutes": 30,
        "serviceType": "optometry",
        "professionalId": 15,
        "status": "pending",
        "notes": "Prefiere atención en español",
        "createdAt": "2026-04-26T12:00:00-04:00"
      }
    ],
    "pagination": {
      "limit": 100,
      "offset": 0,
      "count": 1,
      "hasMore": false
    }
  }
}
```

Nota: si el token no se guarda en BD, no debe prometerse `appointmentTokenPreview`. Puede omitirse.

### 18.2 Obtener cita admin

```http
GET /ws/dashboard-usd/api/admin/appointments_get.php?licenseUuid=abc123&appointmentId=2451
```

Debe validar:

```txt
1. Authorization.
2. licenseUuid.
3. appointmentId.
4. Que la cita pertenece a esa licencia.
```

### 18.3 Actualizar cita admin

```http
POST /ws/dashboard-usd/api/admin/appointments_update.php
```

Body:

```json
{
  "licenseUuid": "abc123",
  "appointmentId": 2451,
  "startAt": "2026-05-04T11:00:00-04:00",
  "durationMinutes": 30,
  "serviceType": "optometry",
  "professionalId": 20,
  "status": "confirmed",
  "notes": "Reprogramada por solicitud del paciente"
}
```

Debe validar conflicto si cambia `startAt`, `durationMinutes` o `professionalId`.

### 18.4 Confirmar cita admin

```http
POST /ws/dashboard-usd/api/admin/appointments_confirm.php
```

Body:

```json
{
  "licenseUuid": "abc123",
  "appointmentId": 2451
}
```

Respuesta:

```json
{
  "ok": true,
  "message": "Cita confirmada correctamente.",
  "data": {
    "appointment": {
      "appointmentId": 2451,
      "status": "confirmed"
    }
  }
}
```

### 18.5 Cancelar cita admin

```http
POST /ws/dashboard-usd/api/admin/appointments_cancel.php
```

Body:

```json
{
  "licenseUuid": "abc123",
  "appointmentId": 2451,
  "reason": "Paciente canceló por teléfono"
}
```

Respuesta:

```json
{
  "ok": true,
  "message": "Cita cancelada correctamente.",
  "data": {
    "appointment": {
      "appointmentId": 2451,
      "status": "cancelled"
    }
  }
}
```

No se permite DELETE físico.

---

## 19. Control para evitar citas sin control

### 19.1 Rate limit

Aplicar límites mínimos:

```txt
máximo 5 intentos por IP cada 10 minutos
máximo 3 citas por documento por día por licencia
máximo 3 citas por teléfono por día por licencia
máximo 1 cita activa para el mismo documento en el mismo horario
```

Si no se puede guardar contador por no modificar BD, primera versión puede validar por consultas a citas existentes:

```txt
misma licencia
mismo documento
mismo día
estado activo
```

Limitación:

```txt
El rate limit por IP requiere algún almacenamiento: archivo temporal, Redis, tabla existente de logs o middleware externo.
```

Si no existe almacenamiento para IP, el límite por IP queda como limitación conocida de v1.

### 19.2 Doble submit

Evitar duplicados si el usuario hace doble clic.

Regla:

```txt
rechazar si ya existe cita activa con:
- misma licencia
- mismo customerDocument
- mismo startAt
```

Respuesta:

```json
{
  "ok": false,
  "code": "SLOT_CONFLICT",
  "message": "Ya existe una cita activa para ese horario.",
  "errors": []
}
```

### 19.3 Ventana de reserva

Reglas iniciales:

```txt
No permitir citas en el pasado.
No permitir citas con menos de 2 horas de anticipación.
No permitir citas a más de 60 días en el futuro.
```

Estos valores deben quedar en configuración.

### 19.4 Horario laboral

Primera versión:

```txt
Usar horario fijo por defecto si no existe configuración en BD.
```

Ejemplo:

```txt
Lunes a viernes: 09:00 - 18:00
Sábado: 09:00 - 13:00
Domingo: cerrado
```

Si la BD ya tiene horarios por licencia o profesional, se debe usar esa fuente.

---

## 20. Seguridad

### 20.1 Reglas públicas

```txt
1. No listar citas públicamente.
2. No devolver datos de otros pacientes.
3. No devolver appointmentId interno en endpoints públicos.
4. No devolver id interno de licencia.
5. No devolver errores SQL.
6. No indicar si un appointmentId existe.
7. Usar mensajes genéricos ante token inválido.
8. Enmascarar datos sensibles.
9. No registrar appointmentToken completo.
10. Validar y parametrizar todos los inputs.
```

### 20.2 Reglas admin

```txt
1. Requiere Authorization Bearer.
2. Token admin fuera del repo.
3. Validar token antes de cualquier consulta.
4. Usar comparación segura, por ejemplo hash_equals().
5. Registrar acciones sensibles.
6. No permitir DELETE físico.
7. Validar que la cita pertenece a la licencia indicada.
```

### 20.3 SQL Injection

Todas las consultas deben usar parámetros.

Nunca concatenar directamente inputs del usuario en SQL.

Inputs especialmente sensibles:

```txt
licenseUuid
appointmentToken
customerDocument
customerPhone
date
startAt
status
professionalId
limit
offset
```

### 20.4 CORS

Permitir solo dominios reales.

Ejemplo:

```txt
https://web.tu-dominio.com
https://qa.tu-dominio.com
```

No usar en producción:

```txt
Access-Control-Allow-Origin: *
```

### 20.5 HTTPS

Todos los endpoints deben consumirse por HTTPS.

No aceptar uso productivo sobre HTTP plano.

---

## 21. Logs

Registrar internamente:

```txt
timestamp
requestId
endpoint
method
IP
channel
licenseUuid parcial o hasheado
appointmentId si aplica
status HTTP
error code
duración
```

No registrar:

```txt
tokens completos
contraseñas
credenciales
errores SQL completos en respuesta pública
datos sensibles innecesarios
customerDocument completo salvo necesidad operativa controlada
customerPhone completo salvo necesidad operativa controlada
query string completo cuando incluya appointmentToken
```

---

## 22. Permisos de base de datos

### Usuario lectura

Necesario para:

```txt
resolver licencia
consultar disponibilidad
validar conflictos
consultar cita por token
listar admin
```

Permisos:

```txt
SELECT
```

### Usuario escritura

Necesario para:

```txt
crear cita
actualizar cita
confirmar cita
cancelar cita
```

Permisos:

```txt
SELECT
INSERT
UPDATE
```

No se requiere:

```txt
DELETE
ALTER
DROP
```

---

## 23. Uso de `WITH (NOLOCK)`

`WITH (NOLOCK)` no debe usarse de forma general.

Permitido solo en:

```txt
consultas informativas
reportes no críticos
lecturas donde una inconsistencia temporal no afecte decisiones de negocio
```

Prohibido en:

```txt
validación final de conflicto
creación de cita
consulta por appointmentToken
confirmación de cita
cancelación de cita
actualización de cita
validaciones de seguridad
```

Para creación y actualización se deben usar transacciones y locks apropiados.

---

## 24. Datos que aún deben confirmarse antes de codificar

Antes de codificar al 100%, confirmar:

```txt
1. Tipo real del UUID público de licencia.
2. Si la licencia tiene campo de activo/inactivo.
3. Si existe configuración de booking habilitado.
4. Si el identificador de cita es autoincremental.
5. Valores reales usados para estado.
6. Si la cita tiene campo para nombre del paciente.
7. Si la cita tiene campo para teléfono.
8. Si la cita tiene campo para email.
9. Si la cita tiene campo para observaciones.
10. Si existe duración.
11. Si existe fecha de actualización.
12. Si existe profesional obligatorio.
13. Si se permite cita sin professionalId.
14. Si el paciente debe existir previamente.
15. Si hay horarios por licencia/profesional.
16. Si la función ejecutarQueryAzureSQLServerV2() soporta parámetros.
17. Si la función ejecutarQueryAzureSQLServerV2() soporta transacciones reales.
18. Si existe forma de obtener el último appointmentId insertado.
19. Zona horaria oficial por licencia o por sistema.
20. Si existe algún almacenamiento disponible para rate limit por IP.
```

---

## 25. Si faltan campos en la base

Como no se modificará la base en la primera versión:

### Si no existe campo para nombre

```txt
No se guarda customerName
o se busca si existe tabla de pacientes
```

### Si no existe campo para teléfono/email

```txt
No se guarda en primera versión
o se busca si existe tabla de pacientes
```

### Si no existe campo para notas

```txt
No se soporta notes en primera versión
```

### Si no existe campo para duración

```txt
durationMinutes se usa solo para calcular endAt en respuesta/disponibilidad
pero no se guarda
```

### Si no existe updatedAt

```txt
No se devuelve updatedAt
o se devuelve null
```

---

## 26. Contrato público final

```txt
GET  /ws/dashboard-usd/api/public/licenses_resolve.php?licenseUuid={licenseUuid}

GET  /ws/dashboard-usd/api/public/availability.php?licenseUuid={licenseUuid}&date={YYYY-MM-DD}

POST /ws/dashboard-usd/api/public/appointments_create.php

POST /ws/dashboard-usd/api/public/appointments_public_get.php
```

Rutas compatibles si no se usa `/public/`:

```txt
GET  /ws/dashboard-usd/api/licenses_resolve.php?licenseUuid={licenseUuid}

GET  /ws/dashboard-usd/api/availability.php?licenseUuid={licenseUuid}&date={YYYY-MM-DD}

POST /ws/dashboard-usd/api/appointments_create.php

POST /ws/dashboard-usd/api/appointments_public_get.php
```

---

## 27. Contrato admin final

```txt
GET  /ws/dashboard-usd/api/admin/appointments_list.php

GET  /ws/dashboard-usd/api/admin/appointments_get.php

POST /ws/dashboard-usd/api/admin/appointments_update.php

POST /ws/dashboard-usd/api/admin/appointments_confirm.php

POST /ws/dashboard-usd/api/admin/appointments_cancel.php
```

Todos requieren:

```txt
Authorization: Bearer <admin-token>
```

---

## 28. Orden recomendado de construcción

### Fase 1 — Bootstrap y helpers base

Construir:

```txt
bootstrap.php
JsonResponse
Request
ApiException
config.php
DatabaseClient
```

Debe incluir:

```txt
response JSON
lectura de JSON body
validaciones básicas
manejo seguro de errores
requestId
channel
CORS
carga de configuración secreta
```

### Fase 2 — Seguridad

Construir:

```txt
AppointmentTokenService
AdminAuth
Masking
RateLimiter básico
```

Debe incluir:

```txt
token encrypt/decrypt
validación de expiración
validación de formato apt_
validación admin token
enmascaramiento de documento y teléfono
no loguear tokens completos
```

### Fase 3 — Licencia

Construir:

```txt
LicenseRepository
LicenseService
licenses_resolve.php
```

Validar:

```txt
licenseUuid existe
booking habilitado
no devuelve id interno
no devuelve datos sensibles
```

### Fase 4 — Disponibilidad

Construir:

```txt
AvailabilityService
availability.php
```

Validar:

```txt
solo devuelve slots
no devuelve citas
no devuelve pacientes
respeta horario laboral
respeta duración
respeta estados activos
```

### Fase 5 — Crear cita

Construir:

```txt
AppointmentRepository
AppointmentService
AppointmentValidator
appointments_create.php
```

Debe hacer:

```txt
validar payload
resolver licencia
validar reglas de booking
validar conflicto con transacción/lock
crear cita
generar appointmentToken
devolver respuesta pública mínima
```

### Fase 6 — Validar cita pública

Construir:

```txt
appointments_public_get.php
```

Debe hacer:

```txt
validar token
descifrar token
validar expiración
consultar cita
validar pertenencia a licencia
devolver datos mínimos enmascarados
```

### Fase 7 — Admin

Construir:

```txt
admin/appointments_list.php
admin/appointments_get.php
admin/appointments_update.php
admin/appointments_confirm.php
admin/appointments_cancel.php
```

Debe hacer:

```txt
validar Authorization
validar licencia
validar pertenencia de cita a licencia
validar transiciones de estado
permitir operaciones administrativas
registrar acciones sensibles
```

---

## 29. Migración futura a Laminas/Zend

### 29.1 Dificultad estimada

Si se implementa con services y repositories separados:

```txt
Dificultad futura: baja-media
Estimación: 3/10 a 4/10
```

Si se implementa como scripts PHP con SQL, validaciones y responses mezclados:

```txt
Dificultad futura: media-alta
Estimación: 6/10 a 7/10
```

### 29.2 Qué cambiaría al migrar

| PHP clásico v1           | Laminas/Zend futuro                       |
| ------------------------ | ----------------------------------------- |
| `.php` por endpoint      | rutas/controladores o middleware handlers |
| `Request::fromGlobals()` | Request PSR-7 o MVC request               |
| `JsonResponse` propio    | JsonModel o ResponseFactory               |
| `AdminAuth` manual       | Middleware/auth service                   |
| `config.php`             | module config/config provider             |
| `DatabaseClient`         | service inyectado por container           |
| `AppointmentService`     | mismo service registrado en container     |
| `AppointmentRepository`  | mismo repository registrado en container  |

### 29.3 Regla para facilitar migración

El service no debe depender de:

```txt
$_GET
$_POST
$_SERVER
echo
exit
headers directos
rutas físicas
```

El service debe recibir datos ya normalizados:

```php
$appointmentService->createPublicAppointment($input, $context);
```

---

## 30. Checklist antes de producción

```txt
1. Confirmar mapeo real de tablas y columnas.
2. Confirmar que ejecutarQueryAzureSQLServerV2() soporta parámetros.
3. Confirmar estrategia de transacciones.
4. Confirmar cómo obtener appointmentId insertado.
5. Confirmar zona horaria oficial.
6. Confirmar estados internos y mapeo público.
7. Confirmar campos disponibles para paciente.
8. Configurar claves secretas fuera del repo.
9. Configurar CORS real.
10. Configurar HTTPS.
11. Validar que no se muestran errores SQL.
12. Validar que no se loguean tokens completos.
13. Probar doble submit.
14. Probar dos creaciones simultáneas al mismo horario.
15. Probar token inválido, expirado y manipulado.
16. Probar licencia inválida.
17. Probar límites por documento/teléfono.
18. Probar endpoints admin sin token, con token inválido y con token válido.
19. Probar que endpoints públicos no devuelven appointmentId.
20. Probar que disponibilidad no revela datos de citas.
```

---

## 31. Reglas finales de diseño

```txt
1. El frontend público usa licenseUuid.
2. El frontend público nunca recibe appointmentId interno.
3. El frontend público recibe appointmentToken al crear cita.
4. El appointmentToken es cifrado, autenticado y expirable.
5. El token no se guarda en BD en v1.
6. La disponibilidad pública no muestra detalles de citas.
7. Listar citas requiere autorización admin.
8. Crear citas requiere validaciones fuertes.
9. Crear citas requiere control de concurrencia.
10. No se modifica la base de datos en primera versión.
11. No se exponen nombres reales de BD.
12. No se devuelven errores SQL al cliente.
13. No se eliminan citas físicamente.
14. No se usa NOLOCK en validaciones críticas.
15. El código se organiza para futura migración a Laminas/Zend.
```

---

## 32. Conclusión

La arquitectura final queda así:

```txt
API pública:
- segura
- limitada
- sin exposición de citas
- sin exposición de IDs internos
- con token público cifrado para validar cita propia

API admin:
- autorizada
- con acceso a detalles completos
- con capacidad de gestionar citas
- sin eliminación física

Base de datos:
- sin modificación inicial
- usada mediante mapeo interno PHP
- protegida de exposición al frontend

Código PHP:
- clásico en despliegue
- modular internamente
- preparado para migrar después a Laminas/Zend
```

Este diseño permite construir el backend en PHP clásico de forma segura, manteniendo la base de datos legacy intacta, evitando que usuarios públicos puedan descubrir o consultar citas ajenas y reduciendo el costo de una futura migración a un framework formal.
