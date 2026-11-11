<?php
/**
 * Listado con filtros y paginación. Sólo muestra registros del país del usuario.
 */
declare(strict_types=1);
require __DIR__ . '/../../lib/bootstrap.php';
require __DIR__ . '/../../lib/auth.php';
require __DIR__ . '/../../lib/transfers.php';

require_method('GET');
$user = require_auth();

$filters = normalize_filters($_GET);
$page    = max(1, (int) ($_GET['page'] ?? 1));
$perPage = min(100, max(5, (int) ($_GET['per_page'] ?? 25)));

$result = repo()->searchTransfers($user['country_id'], $filters, $page, $perPage);

json_response([
    'ok'         => true,
    'data'       => array_map(static fn(array $r) => present_transfer($r, $user['country_code']), $result['rows']),
    'pagination' => [
        'page'     => $page,
        'per_page' => $perPage,
        'total'    => $result['total'],
        'pages'    => max(1, (int) ceil($result['total'] / $perPage)),
    ],
    'summary' => repo()->summary($user['country_id']),
]);
