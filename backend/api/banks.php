<?php
declare(strict_types=1);
require __DIR__ . '/../lib/bootstrap.php';

require_method('GET');
$country = find_country((string) ($_GET['country'] ?? ''));
if (!$country) {
    json_error('País no válido / Invalid country', 404);
}
json_response(['ok' => true, 'banks' => repo()->banks((int) $country['id'])]);
