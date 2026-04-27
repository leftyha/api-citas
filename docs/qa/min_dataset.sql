-- Dataset mínimo QA para flujo E2E (licencia activa + agenda base)
-- Ejecutar sobre entorno QA con SQL Server.

SET NOCOUNT ON;

IF NOT EXISTS (SELECT 1 FROM booking_licenses WHERE license_uuid = 'qa-license-001')
BEGIN
    INSERT INTO booking_licenses (license_uuid, status, holder_name, created_at)
    VALUES ('qa-license-001', 'active', 'Licencia QA', SYSUTCDATETIME());
END;

DECLARE @licenseId INT = (
    SELECT TOP 1 license_id
    FROM booking_licenses
    WHERE license_uuid = 'qa-license-001'
);

IF @licenseId IS NOT NULL
BEGIN
    -- Limpieza controlada de citas de prueba del día siguiente
    DELETE FROM booking_appointments
    WHERE license_id = @licenseId
      AND CAST(start_at AS DATE) = CAST(DATEADD(DAY, 1, SYSUTCDATETIME()) AS DATE)
      AND customer_document LIKE 'QA-%';

    -- Cita base pendiente para validar list/get/update/transition admin
    INSERT INTO booking_appointments (
        license_id, customer_document, customer_name, customer_phone, customer_email,
        start_at, end_at, duration_minutes, service_type, professional_id, notes, status
    )
    VALUES (
        @licenseId,
        'QA-0001',
        'Usuario QA',
        '+580000000001',
        'qa1@example.com',
        DATEADD(DAY, 1, DATEADD(HOUR, 14, CAST(CAST(SYSUTCDATETIME() AS DATE) AS DATETIME2))),
        DATEADD(DAY, 1, DATEADD(HOUR, 14, DATEADD(MINUTE, 30, CAST(CAST(SYSUTCDATETIME() AS DATE) AS DATETIME2)))),
        30,
        'qa-e2e',
        101,
        'seed qa',
        'pending'
    );
END;
