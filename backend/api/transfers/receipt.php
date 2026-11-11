<?php
/**
 * Muestra el comprobante adjunto (sólo usuarios del mismo país).
 */
declare(strict_types=1);
require __DIR__ . '/../../lib/bootstrap.php';
require __DIR__ . '/../../lib/auth.php';

require_method('GET');
$user = require_auth();

$id  = (int) ($_GET['id'] ?? 0);
$row = repo()->findTransfer($id, $user['country_id']);
if (!$row) {
    json_error('Comprobante no encontrado / Receipt not found', 404);
}

$base = realpath(cfg('upload_dir'));
$path = realpath(cfg('upload_dir') . '/' . $row['receipt_path']);
if ($base === false || $path === false || !str_starts_with($path, $base . DIRECTORY_SEPARATOR) || !is_file($path)) {
    json_error('Archivo no disponible / File not available', 404);
}

audit($user['id'], $id, 'view_receipt');

$disposition = isset($_GET['download']) ? 'attachment' : 'inline';
header('Content-Type: ' . $row['receipt_mime']);
header('Content-Length: ' . filesize($path));
header('Content-Disposition: ' . $disposition . '; filename="' . addslashes($row['receipt_original_name']) . '"');
header('X-Content-Type-Options: nosniff');
header('Cache-Control: private, no-store');
readfile($path);
