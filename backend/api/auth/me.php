<?php
declare(strict_types=1);
require __DIR__ . '/../../lib/bootstrap.php';
require __DIR__ . '/../../lib/auth.php';

require_method('GET');
$user = require_auth();
json_response(['ok' => true, 'user' => $user, 'csrf' => csrf_token()]);
