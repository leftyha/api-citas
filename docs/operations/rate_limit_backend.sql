-- Backend distribuido para rate limiting (SQL Server)

IF OBJECT_ID('dbo.booking_rate_limit', 'U') IS NULL
BEGIN
    CREATE TABLE dbo.booking_rate_limit (
        key_hash VARCHAR(64) NOT NULL,
        bucket BIGINT NOT NULL,
        attempts INT NOT NULL,
        updated_at DATETIME2 NOT NULL DEFAULT SYSUTCDATETIME(),
        CONSTRAINT PK_booking_rate_limit PRIMARY KEY (key_hash, bucket)
    );

    CREATE INDEX IX_booking_rate_limit_updated_at
        ON dbo.booking_rate_limit (updated_at);
END;
