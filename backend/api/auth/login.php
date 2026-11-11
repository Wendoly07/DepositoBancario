<?php
declare(strict_types=1);
require __DIR__ . '/../../lib/bootstrap.php';
require __DIR__ . '/../../lib/auth.php';

require_method('POST');
start_secure_session();

$body     = read_json_body();
$username = trim((string) ($body['username'] ?? ''));
$password = (string) ($body['password'] ?? '');
if ($username === '' || $password === '') {
    json_error('Ingrese usuario y contraseña / Enter username and password', 422);
}

$user = repo()->findUserForLogin($username);

if ($user && $user['is_locked']) {
    json_error('Usuario bloqueado temporalmente por intentos fallidos. Intente más tarde. / Account temporarily locked.', 429);
}

if (!$user || !$user['active'] || !password_verify($password, $user['password_hash'])) {
    if ($user) {
        repo()->registerFailedLogin($user['id'], (int) cfg('login.max_attempts'), (int) cfg('login.lock_minutes'));
        audit($user['id'], null, 'login_fail');
    }
    usleep(400000);
    json_error('Usuario o contraseña incorrectos / Invalid username or password', 401);
}

$newHash = password_needs_rehash($user['password_hash'], PASSWORD_DEFAULT) ? password_hash($password, PASSWORD_DEFAULT) : null;
repo()->registerSuccessfulLogin($user['id'], $newHash);

session_regenerate_id(true);
$_SESSION['user_id'] = $user['id'];
unset($_SESSION['csrf']);
audit($user['id'], null, 'login_ok');

json_response(['ok' => true, 'user' => current_user(), 'csrf' => csrf_token()]);
