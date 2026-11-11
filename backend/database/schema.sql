-- =====================================================================
--  AZURE SQL / SQL SERVER  —  PREPARADO PARA LA SIGUIENTE ETAPA
--  (El proyecto funciona hoy SIN base de datos: DATA_DRIVER=json)
--
--  Ejecutar con SSMS, Azure Data Studio o sqlcmd (usa separadores GO)
--  sobre la base de datos ya creada en Azure.
--  Fechas en UTC (SYSUTCDATETIME).
-- =====================================================================

CREATE TABLE dbo.countries (
    id        TINYINT       NOT NULL PRIMARY KEY,
    code      NCHAR(2)      NOT NULL UNIQUE,
    slug      NVARCHAR(30)  NOT NULL UNIQUE,
    name      NVARCHAR(60)  NOT NULL,
    lang      NCHAR(2)      NOT NULL,           -- es | en
    currency  NCHAR(3)      NOT NULL,
    active    BIT           NOT NULL DEFAULT 1
);

INSERT INTO dbo.countries (id, code, slug, name, lang, currency) VALUES
 (1, N'HN', N'honduras',    N'Honduras',    N'es', N'HNL'),
 (2, N'SV', N'el-salvador', N'El Salvador', N'es', N'USD'),
 (3, N'NI', N'nicaragua',   N'Nicaragua',   N'es', N'NIO'),
 (4, N'GY', N'guyana',      N'Guyana',      N'en', N'GYD'),
 (5, N'SR', N'suriname',    N'Suriname',    N'en', N'SRD'),
 (6, N'LC', N'st-lucia',    N'St. Lucia',   N'en', N'XCD');

CREATE TABLE dbo.banks (
    id          INT IDENTITY(1,1) NOT NULL PRIMARY KEY,
    country_id  TINYINT       NOT NULL REFERENCES dbo.countries(id),
    name        NVARCHAR(120) NOT NULL,
    sort_order  SMALLINT      NOT NULL DEFAULT 0,
    active      BIT           NOT NULL DEFAULT 1,
    CONSTRAINT uq_bank_country UNIQUE (country_id, name)
);

-- Mismos IDs que backend/config/catalog.php
SET IDENTITY_INSERT dbo.banks ON;
INSERT INTO dbo.banks (id, country_id, name, sort_order) VALUES
 (1, 1, N'Banco Atlántida', 1), (2, 1, N'BAC Credomatic Honduras', 2), (3, 1, N'Banco Ficohsa', 3), (4, 1, N'Banpaís', 4),
 (5, 2, N'Banco Agrícola', 1), (6, 2, N'BAC Credomatic El Salvador', 2), (7, 2, N'Banco Cuscatlán', 3), (8, 2, N'Banco Davivienda', 4),
 (9, 3, N'BANPRO', 1), (10, 3, N'BAC Credomatic Nicaragua', 2), (11, 3, N'LAFISE Bancentro', 3), (12, 3, N'Banco Ficohsa Nicaragua', 4), (13, 3, N'BDF', 5),
 (14, 4, N'Republic Bank (Guyana)', 1), (15, 4, N'GBTI', 2), (16, 4, N'Demerara Bank', 3), (17, 4, N'Citizens Bank Guyana', 4),
 (18, 5, N'De Surinaamsche Bank (DSB)', 1), (19, 5, N'Hakrinbank', 2), (20, 5, N'Finabank', 3), (21, 5, N'Republic Bank Suriname', 4),
 (22, 6, N'Bank of Saint Lucia', 1), (23, 6, N'1st National Bank St. Lucia', 2), (24, 6, N'Republic Bank (EC)', 3), (25, 6, N'CIBC Caribbean', 4);
SET IDENTITY_INSERT dbo.banks OFF;

CREATE TABLE dbo.users (
    id               INT IDENTITY(1,1) NOT NULL PRIMARY KEY,
    country_id       TINYINT       NOT NULL REFERENCES dbo.countries(id),
    username         NVARCHAR(60)  NOT NULL UNIQUE,
    full_name        NVARCHAR(120) NOT NULL,
    password_hash    NVARCHAR(255) NOT NULL,
    active           BIT           NOT NULL DEFAULT 1,
    failed_attempts  TINYINT       NOT NULL DEFAULT 0,
    locked_until     DATETIME2(0)  NULL,
    last_login_at    DATETIME2(0)  NULL,
    created_at       DATETIME2(0)  NOT NULL DEFAULT SYSUTCDATETIME()
);

CREATE TABLE dbo.transfers (
    id                     BIGINT IDENTITY(1,1) NOT NULL PRIMARY KEY,
    country_id             TINYINT        NOT NULL REFERENCES dbo.countries(id),
    email                  NVARCHAR(190)  NOT NULL,   -- 1
    full_name              NVARCHAR(150)  NOT NULL,   -- 2
    dni                    NVARCHAR(30)   NOT NULL,   -- 3
    deposit_code           NVARCHAR(60)   NOT NULL,   -- 4
    bank_id                INT            NOT NULL REFERENCES dbo.banks(id),  -- 5
    transfer_date          DATE           NOT NULL,   -- 6
    amount                 DECIMAL(14,2)  NOT NULL CHECK (amount > 0),  -- 7
    reference_number       NVARCHAR(80)   NOT NULL,   -- 8
    receipt_path           NVARCHAR(255)  NOT NULL,   -- 9
    receipt_original_name  NVARCHAR(255)  NOT NULL,
    receipt_mime           NVARCHAR(100)  NOT NULL,
    receipt_size           INT            NOT NULL,
    ownership_confirmed    BIT            NOT NULL,   -- 10
    checked                BIT            NOT NULL DEFAULT 0,
    checked_at             DATETIME2(0)   NULL,
    checked_by             INT            NULL REFERENCES dbo.users(id),
    ip_address             NVARCHAR(45)   NULL,
    user_agent             NVARCHAR(255)  NULL,
    created_at             DATETIME2(0)   NOT NULL DEFAULT SYSUTCDATETIME(),
    CONSTRAINT uq_reference UNIQUE (country_id, bank_id, reference_number)
);
CREATE INDEX idx_transfers_country_created ON dbo.transfers (country_id, created_at DESC);
CREATE INDEX idx_transfers_reference       ON dbo.transfers (reference_number);
CREATE INDEX idx_transfers_deposit         ON dbo.transfers (deposit_code);
CREATE INDEX idx_transfers_dni             ON dbo.transfers (dni);
CREATE INDEX idx_transfers_checked         ON dbo.transfers (country_id, checked);

CREATE TABLE dbo.audit_log (
    id           BIGINT IDENTITY(1,1) NOT NULL PRIMARY KEY,
    user_id      INT           NULL,
    transfer_id  BIGINT        NULL,
    action       NVARCHAR(30)  NOT NULL,   -- login_ok, login_fail, check, view_receipt, export
    detail       NVARCHAR(255) NULL,
    ip_address   NVARCHAR(45)  NULL,
    created_at   DATETIME2(0)  NOT NULL DEFAULT SYSUTCDATETIME()
);
CREATE INDEX idx_audit_transfer ON dbo.audit_log (transfer_id);
GO

-- =====================================================================
-- REGLAS DE NEGOCIO EN LA BASE DE DATOS
-- =====================================================================

-- No se pueden eliminar transferencias
CREATE TRIGGER dbo.trg_transfers_no_delete ON dbo.transfers
INSTEAD OF DELETE
AS
BEGIN
    THROW 50001, N'No se permite eliminar transferencias', 1;
END;
GO

-- El check se da UNA vez, nunca se quita, y los datos del formulario no cambian
CREATE TRIGGER dbo.trg_transfers_check_once ON dbo.transfers
INSTEAD OF UPDATE
AS
BEGIN
    SET NOCOUNT ON;
    IF EXISTS (SELECT 1 FROM deleted WHERE checked = 1)
        THROW 50002, N'Transferencia ya verificada: no se puede modificar', 1;
    IF EXISTS (SELECT 1 FROM inserted WHERE checked <> 1 OR checked_by IS NULL)
        THROW 50003, N'Solo se permite marcar la transferencia como verificada', 1;

    -- Sólo se aplican los campos del check; el resto se ignora
    UPDATE t
       SET t.checked    = 1,
           t.checked_at = COALESCE(i.checked_at, SYSUTCDATETIME()),
           t.checked_by = i.checked_by
      FROM dbo.transfers t
      JOIN inserted i ON i.id = t.id;
END;
GO

-- La bitácora no se puede modificar ni eliminar
CREATE TRIGGER dbo.trg_audit_readonly ON dbo.audit_log
INSTEAD OF UPDATE, DELETE
AS
BEGIN
    THROW 50004, N'La bitácora no se puede modificar ni eliminar', 1;
END;
GO
