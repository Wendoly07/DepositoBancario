-- =====================================================================
--  Usuario de la aplicación con permisos mínimos (siguiente etapa)
--  Ejecutar en la base de datos de la app con un usuario administrador.
-- =====================================================================

-- OPCIÓN A (recomendada): Managed Identity del App Service (sin contraseñas)
--   1. Activar "Identidad > Asignada por el sistema" en el App Service.
--   2. Ejecutar (reemplazar por el nombre del App Service):
-- CREATE USER [nombre-del-app-service] FROM EXTERNAL PROVIDER;
-- y usar ese nombre en lugar de app_transferencias abajo. App Setting: DB_AUTH=msi

-- OPCIÓN B: usuario SQL contenido (guardar la contraseña en Key Vault)
CREATE USER app_transferencias WITH PASSWORD = N'CAMBIAR-por-una-contraseña-segura!';
GO

GRANT SELECT ON SCHEMA::dbo TO app_transferencias;
GRANT INSERT, UPDATE ON dbo.transfers TO app_transferencias;
GRANT INSERT, UPDATE ON dbo.users     TO app_transferencias;
GRANT INSERT         ON dbo.audit_log TO app_transferencias;

-- La app nunca puede borrar ni alterar tablas o triggers
DENY DELETE ON SCHEMA::dbo TO app_transferencias;
DENY ALTER  ON SCHEMA::dbo TO app_transferencias;
GO
