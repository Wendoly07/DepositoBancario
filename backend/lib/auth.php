<?php
declare(strict_types=1);

function start_secure_session(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }
    $path = (string) cfg('session.path', '');
    if ($path !== '') {
        if (!is_dir($path)) {
            @mkdir($path, 0700, true);
        }
        if (is_dir($path) && is_writable($path)) {
            session_save_path($path);
        }
    }
    $sameSite = (string) cfg('session.samesite', 'Lax');
    session_name(cfg('session.name'));
    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => '/',
        'httponly' => true,
        'secure'   => is_https() || $sameSite === 'None',
        'samesite' => $sameSite,
    ]);
    ini_set('session.use_strict_mode', '1');
    session_start();

    $timeout = (int) cfg('session.idle_timeout', 1800);
    if (isset($_SESSION['last_activity']) && time() - $_SESSION['last_activity'] > $timeout) {
        session_unset();
        session_regenerate_id(true);
    }
    $_SESSION['last_activity'] = time();
}

/** Usuario autenticado (cada usuario pertenece a UN país) */
function current_user(): ?array
{
    start_secure_session();
    if (empty($_SESSION['user_id'])) {
        return null;
    }
    return repo()->findActiveUser((int) $_SESSION['user_id']);
}

function require_auth(): array
{
    $user = current_user();
    if (!$user) {
        json_error('Sesión no válida o expirada / Session expired', 401);
    }
    return $user;
}

function csrf_token(): string
{
    start_secure_session();
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf'];
}

function verify_csrf(): void
{
    $sent = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
    if (!is_string($sent) || $sent === '' || !hash_equals(csrf_token(), $sent)) {
        json_error('Token de seguridad inválido. Recargue la página. / Invalid security token.', 419);
    }
}

function audit(?int $userId, ?int $transferId, string $action, string $detail = ''): void
{
    repo()->audit($userId, $transferId, $action, mb_substr($detail, 0, 255), client_ip());
}
