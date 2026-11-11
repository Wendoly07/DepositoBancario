<?php
/**
 * Marca una transferencia como VERIFICADA (checklist).
 * Acción única e irreversible: no existe endpoint para desmarcar ni eliminar.
 */
declare(strict_types=1);
require __DIR__ . '/../../lib/bootstrap.php';
require __DIR__ . '/../../lib/auth.php';
require __DIR__ . '/../../lib/transfers.php';

require_method('POST');
$user = require_auth();
verify_csrf();

$id = (int) (read_json_body()['id'] ?? 0);
if ($id <= 0) {
    json_error('Registro no válido / Invalid record', 422);
}

$result = repo()->markChecked($id, $user['country_id'], $user['id']);
if ($result === 'not_found') {
    json_error('Registro no encontrado / Record not found', 404);
}
if ($result === 'already_checked') {
    json_error('Esta transferencia ya fue verificada y no puede modificarse. / This transfer is already verified and cannot be changed.', 409);
}

audit($user['id'], $id, 'check');
json_response(['ok' => true, 'data' => present_transfer(repo()->findTransfer($id, $user['country_id']), $user['country_code'])]);
