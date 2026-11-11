<?php
declare(strict_types=1);

const APP_ROOT = __DIR__ . '/..';

$GLOBALS['APP_CONFIG'] = require APP_ROOT . '/config/config.php';
date_default_timezone_set($GLOBALS['APP_CONFIG']['timezone']);
ini_set('display_errors', $GLOBALS['APP_CONFIG']['debug'] ? '1' : '0');
error_reporting(E_ALL);

/** Lee un valor de configuración con notación de puntos: cfg('db.host') */
function cfg(string $key, mixed $default = null): mixed
{
    $value = $GLOBALS['APP_CONFIG'];
    foreach (explode('.', $key) as $part) {
        if (!is_array($value) || !array_key_exists($part, $value)) {
            return $default;
        }
        $value = $value[$part];
    }
    return $value;
}

require_once APP_ROOT . '/lib/Repositories/TransferRepository.php';
require_once APP_ROOT . '/lib/Repositories/JsonRepository.php';
require_once APP_ROOT . '/lib/Repositories/SqlServerRepository.php';

/**
 * Punto ÚNICO de acceso a los datos.
 * Hoy: archivos JSON (sin base de datos). Mañana: SQL Server con DATA_DRIVER=sqlsrv.
 */
function repo(): TransferRepository
{
    static $repo = null;
    if ($repo === null) {
        $repo = match (cfg('data_driver')) {
            'json'   => new JsonRepository((string) cfg('data_dir'), require APP_ROOT . '/config/catalog.php'),
            'sqlsrv' => new SqlServerRepository(cfg('db')),
            default  => throw new RuntimeException('DATA_DRIVER no válido: use json o sqlsrv'),
        };
    }
    return $repo;
}

function json_response(array $data, int $status = 200): never
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');
    header('X-Content-Type-Options: nosniff');
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function json_error(string $message, int $status = 400, array $extra = []): never
{
    json_response(['ok' => false, 'error' => $message] + $extra, $status);
}

function require_method(string $method): void
{
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== $method) {
        json_error('Método no permitido / Method not allowed', 405);
    }
}

function read_json_body(): array
{
    $data = json_decode(file_get_contents('php://input') ?: '[]', true);
    return is_array($data) ? $data : [];
}

/** HTTPS real (App Service termina el TLS y reenvía X-Forwarded-Proto) */
function is_https(): bool
{
    return (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || strtolower((string) ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '')) === 'https'
        || !empty($_SERVER['HTTP_X_ARR_SSL']);
}

/** IP real del cliente detrás del balanceador de App Service */
function client_ip(): string
{
    $fwd = (string) ($_SERVER['HTTP_X_CLIENT_IP'] ?? $_SERVER['HTTP_X_FORWARDED_FOR'] ?? '');
    if ($fwd !== '') {
        $first = trim(explode(',', $fwd)[0]);
        if (preg_match('/^(\d{1,3}(?:\.\d{1,3}){3}):\d+$/', $first, $m)) {
            $first = $m[1]; // App Service agrega el puerto: 1.2.3.4:5678
        }
        if (filter_var($first, FILTER_VALIDATE_IP)) {
            return $first;
        }
    }
    return substr((string) ($_SERVER['REMOTE_ADDR'] ?? ''), 0, 45);
}

/** La BD guarda fechas en UTC. Formato ISO para el navegador. */
function utc_to_iso(?string $value): ?string
{
    if (!$value) {
        return null;
    }
    return (new DateTimeImmutable(substr($value, 0, 19), new DateTimeZone('UTC')))->format('Y-m-d\TH:i:s\Z');
}

/** UTC de la BD -> hora local configurada (para exportaciones) */
function utc_to_local(?string $value): string
{
    if (!$value) {
        return '';
    }
    return (new DateTimeImmutable(substr($value, 0, 19), new DateTimeZone('UTC')))
        ->setTimezone(new DateTimeZone(date_default_timezone_get()))
        ->format('Y-m-d H:i:s');
}

function find_country(string $slug): ?array
{
    return repo()->findCountry($slug);
}

function transfer_folio(string $countryCode, int $id): string
{
    return strtoupper(trim($countryCode)) . '-' . str_pad((string) $id, 6, '0', STR_PAD_LEFT);
}

function send_cors_headers(): void
{
    $origin = $_SERVER['HTTP_ORIGIN'] ?? '';
    if ($origin !== '' && in_array($origin, cfg('allowed_origins', []), true)) {
        header('Access-Control-Allow-Origin: ' . $origin);
        header('Access-Control-Allow-Credentials: true');
        header('Access-Control-Allow-Headers: Content-Type, X-CSRF-Token, Accept');
        header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
        header('Vary: Origin');
    }
    if (($_SERVER['REQUEST_METHOD'] ?? '') === 'OPTIONS') {
        http_response_code(204);
        exit;
    }
}

set_exception_handler(static function (Throwable $e): void {
    error_log((string) $e);
    if (PHP_SAPI === 'cli') {
        fwrite(STDERR, 'Error: ' . $e->getMessage() . PHP_EOL);
        exit(1);
    }
    json_error(cfg('debug') ? $e->getMessage() : 'Error interno del servidor / Internal server error', 500);
});

if (PHP_SAPI !== 'cli') {
    header('Strict-Transport-Security: max-age=31536000');
    send_cors_headers();
}
