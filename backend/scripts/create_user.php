<?php
/**
 * Crea (o restablece la contraseña de) un usuario del panel.
 * Funciona igual en modo JSON (sin base de datos) y en SQL Server.
 *
 * Uso:
 *   php backend/scripts/create_user.php --country=honduras --username=hn.admin --name="Admin Honduras"
 *   (pide la contraseña; también acepta --password="...")
 *
 * En Azure App Service: abrir SSH (Kudu) y ejecutar
 *   php /home/site/wwwroot/backend/scripts/create_user.php ...
 *
 * Países: honduras | el-salvador | nicaragua | guyana | suriname | st-lucia
 */
declare(strict_types=1);
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}
require __DIR__ . '/../lib/bootstrap.php';

$opt = getopt('', ['country:', 'username:', 'name:', 'password:']);
foreach (['country', 'username', 'name'] as $req) {
    if (empty($opt[$req])) {
        fwrite(STDERR, "Falta --$req\nUso: php create_user.php --country=honduras --username=hn.admin --name=\"Admin Honduras\"\n");
        exit(1);
    }
}

$country = find_country($opt['country']);
if (!$country) {
    fwrite(STDERR, "País no válido. Use: honduras, el-salvador, nicaragua, guyana, suriname, st-lucia\n");
    exit(1);
}
if (!preg_match('/^[A-Za-z0-9._-]{3,60}$/', $opt['username'])) {
    fwrite(STDERR, "Usuario no válido (3-60 caracteres: letras, números, punto, guion).\n");
    exit(1);
}

$password = $opt['password'] ?? null;
if ($password === null) {
    echo 'Contraseña (mín. 10 caracteres): ';
    if (DIRECTORY_SEPARATOR === '/') {
        system('stty -echo');
    }
    $password = trim((string) fgets(STDIN));
    if (DIRECTORY_SEPARATOR === '/') {
        system('stty echo');
    }
    echo PHP_EOL;
}
if (mb_strlen($password) < 10) {
    fwrite(STDERR, "La contraseña debe tener al menos 10 caracteres.\n");
    exit(1);
}

repo()->saveUser((int) $country['id'], $opt['username'], $opt['name'], password_hash($password, PASSWORD_DEFAULT));
echo "Usuario '{$opt['username']}' listo para {$country['name']} (almacenamiento: " . cfg('data_driver') . ").\n";
