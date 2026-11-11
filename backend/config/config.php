<?php
/**
 * Configuración del backend.
 * En Azure App Service estos valores se definen como "App settings"
 * (Configuración > Variables de entorno); PHP los lee con getenv().
 */
$onAzure = getenv('WEBSITE_SITE_NAME') !== false;

return [
    'debug'    => getenv('APP_DEBUG') === '1',
    'timezone' => getenv('APP_TZ') ?: 'America/Tegucigalpa',

    /*
     * Origen de los datos:
     *   json   -> (ACTUAL) sin base de datos. Guarda en archivos JSON dentro de data_dir.
     *   sqlsrv -> (FUTURO) Azure SQL / SQL Server. Sólo cambiar DATA_DRIVER=sqlsrv
     *             y completar los datos de "db". No hay que tocar el resto del código.
     */
    'data_driver' => getenv('DATA_DRIVER') ?: 'json',
    'data_dir'    => getenv('DATA_DIR') ?: ($onAzure ? '/home/data/transferencias/data' : __DIR__ . '/../storage/data'),

    // Preparado para cuando se conecte SQL Server (no se usa con DATA_DRIVER=json)
    'db' => [
        'host'       => getenv('DB_HOST') ?: 'localhost',     // ej: miservidor.database.windows.net
        'port'       => (int) (getenv('DB_PORT') ?: 1433),
        'name'       => getenv('DB_NAME') ?: 'transferencias',
        'auth'       => getenv('DB_AUTH') ?: 'sql',           // sql | msi (Managed Identity)
        'user'       => getenv('DB_USER') ?: '',
        'pass'       => getenv('DB_PASS') ?: '',
        'encrypt'    => getenv('DB_ENCRYPT') !== '0',
        'trust_cert' => getenv('DB_TRUST_CERT') === '1',
    ],

    // Comprobantes. En Azure van a /home (persistente, compartido entre instancias y
    // FUERA de wwwroot para que un nuevo despliegue no los borre)
    'upload_dir'       => getenv('UPLOAD_DIR') ?: ($onAzure ? '/home/data/transferencias/uploads' : __DIR__ . '/../storage/uploads'),
    'max_upload_bytes' => 10 * 1024 * 1024, // 10 MB
    'allowed_mime'     => [
        'application/pdf' => 'pdf',
        'image/jpeg'      => 'jpg',
        'image/png'       => 'png',
        'image/webp'      => 'webp',
        'image/gif'       => 'gif',
        'image/heic'      => 'heic',
        'image/heif'      => 'heif',
    ],

    'session' => [
        'name'         => 'TRANSFER_PANEL',
        'idle_timeout' => 60 * 30,
        'path'         => getenv('SESSION_PATH') ?: ($onAzure ? '/home/data/transferencias/sessions' : ''),
        'samesite'     => getenv('SESSION_SAMESITE') ?: 'Lax',
    ],

    'login' => [
        'max_attempts' => 5,
        'lock_minutes' => 15,
    ],

    // Sólo si el frontend se publica en OTRO dominio (separar en 2 App Services)
    'allowed_origins' => array_values(array_filter(array_map('trim', explode(',', getenv('APP_ALLOWED_ORIGINS') ?: '')))),
];
