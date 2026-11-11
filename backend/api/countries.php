<?php
declare(strict_types=1);
require __DIR__ . '/../lib/bootstrap.php';

require_method('GET');
$countries = array_map(
    static fn(array $c) => array_intersect_key($c, array_flip(['code', 'slug', 'name', 'language', 'currency'])),
    repo()->countries()
);
json_response(['ok' => true, 'countries' => $countries]);
