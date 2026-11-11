<?php
/**
 * Exporta a CSV (Excel) los registros que cumplen los filtros actuales.
 */
declare(strict_types=1);
require __DIR__ . '/../../lib/bootstrap.php';
require __DIR__ . '/../../lib/auth.php';
require __DIR__ . '/../../lib/transfers.php';

require_method('GET');
$user = require_auth();
$rows = repo()->exportTransfers($user['country_id'], normalize_filters($_GET));
audit($user['id'], null, 'export');

$en = $user['language'] === 'en';
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="transferencias-' . $user['country_slug'] . '-' . date('Ymd-His') . '.csv"');
header('Cache-Control: no-store');

$out = fopen('php://output', 'w');
fwrite($out, "\xEF\xBB\xBF"); // BOM para que Excel respete acentos
fputcsv($out, $en
    ? ['ID', 'Received', 'Email', 'Full name', 'ID number', 'Deposit code', 'Bank', 'Transfer date', 'Amount', 'Reference', 'Status', 'Verified by', 'Verified at']
    : ['Folio', 'Recibido', 'Correo', 'Nombre completo', 'DNI', 'Código de depósito', 'Banco', 'Fecha transferencia', 'Monto', 'Referencia', 'Estado', 'Verificado por', 'Fecha verificación']);

foreach ($rows as $r) {
    $checked = (int) $r['checked'] === 1;
    fputcsv($out, [
        transfer_folio($user['country_code'], (int) $r['id']),
        utc_to_local($r['created_at']), $r['email'], $r['full_name'], $r['dni'], $r['deposit_code'], $r['bank_name'],
        substr((string) $r['transfer_date'], 0, 10), number_format((float) $r['amount'], 2, '.', ''), $r['reference_number'],
        $checked ? ($en ? 'Verified' : 'Verificado') : ($en ? 'Pending' : 'Pendiente'),
        $r['checked_by_name'] ?? '', utc_to_local($r['checked_at'] ?? null),
    ]);
}
fclose($out);
