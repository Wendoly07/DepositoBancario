<?php
/**
 * Recibe el formulario público de transferencia (multipart/form-data).
 */
declare(strict_types=1);
require __DIR__ . '/../lib/bootstrap.php';
require __DIR__ . '/../lib/transfers.php';

require_method('POST');

// Si el archivo supera post_max_size, PHP vacía $_POST y $_FILES
if (empty($_POST) && (int) ($_SERVER['CONTENT_LENGTH'] ?? 0) > 0) {
    json_error('El envío es demasiado grande (máx. 10 MB). / The upload is too large (max. 10 MB).', 413);
}

$country = find_country((string) ($_POST['country'] ?? ''));
if (!$country) {
    json_error('País no válido / Invalid country', 422);
}
$countryId = (int) $country['id'];
$M = require APP_ROOT . '/lang/' . ($country['language'] === 'en' ? 'en' : 'es') . '.php';

$in = static fn(string $k): string => trim((string) ($_POST[$k] ?? ''));
$errors = [];

// 1. Correo
$email = mb_strtolower($in('email'));
if ($email === '') {
    $errors['email'] = $M['required'];
} elseif (!filter_var($email, FILTER_VALIDATE_EMAIL) || mb_strlen($email) > 190) {
    $errors['email'] = $M['email'];
}

// 2. Nombre completo
$fullName = (string) preg_replace('/\s+/u', ' ', $in('full_name'));
if ($fullName === '') {
    $errors['full_name'] = $M['required'];
} elseif (mb_strlen($fullName) < 3 || mb_strlen($fullName) > 150) {
    $errors['full_name'] = $M['full_name'];
}

// 3. DNI
$dni = $in('dni');
if ($dni === '') {
    $errors['dni'] = $M['required'];
} elseif (!preg_match('/^[A-Za-z0-9\- ]{4,30}$/', $dni)) {
    $errors['dni'] = $M['dni'];
}

// 4. Código de depósito
$depositCode = $in('deposit_code');
if ($depositCode === '') {
    $errors['deposit_code'] = $M['required'];
} elseif (!preg_match('/^[A-Za-z0-9\-_.\/ ]{1,60}$/', $depositCode)) {
    $errors['deposit_code'] = $M['deposit_code'];
}

// 5. Banco (debe pertenecer al país)
$bankId = (int) $in('bank_id');
if (!$bankId) {
    $errors['bank_id'] = $M['required'];
} elseif (!repo()->bankBelongsToCountry($bankId, $countryId)) {
    $errors['bank_id'] = $M['bank'];
}

// 6. Fecha (no futura)
$date = $in('transfer_date');
if ($date === '') {
    $errors['transfer_date'] = $M['required'];
} elseif (!valid_date($date) || $date > date('Y-m-d')) {
    $errors['transfer_date'] = $M['date'];
}

// 7. Monto
$amount = str_replace([',', ' '], '', $in('amount'));
if ($amount === '') {
    $errors['amount'] = $M['required'];
} elseif (!preg_match('/^\d{1,12}(\.\d{1,2})?$/', $amount) || (float) $amount <= 0) {
    $errors['amount'] = $M['amount'];
}

// 8. Referencia bancaria
$reference = $in('reference_number');
if ($reference === '') {
    $errors['reference_number'] = $M['required'];
} elseif (!preg_match('/^[A-Za-z0-9\-_.\/ ]{3,80}$/', $reference)) {
    $errors['reference_number'] = $M['reference'];
}

// 9. Comprobante (1 archivo, PDF o imagen, máx. 10 MB)
$file = $_FILES['receipt'] ?? null;
$mime = null;
if (!$file || is_array($file['name']) || $file['error'] === UPLOAD_ERR_NO_FILE) {
    $errors['receipt'] = $M['file'];
} elseif (in_array($file['error'], [UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE], true) || $file['size'] > cfg('max_upload_bytes')) {
    $errors['receipt'] = $M['file_size'];
} elseif ($file['error'] !== UPLOAD_ERR_OK || !is_uploaded_file($file['tmp_name'])) {
    $errors['receipt'] = $M['upload_fail'];
} else {
    $mime = (new finfo(FILEINFO_MIME_TYPE))->file($file['tmp_name']) ?: '';
    if (!isset(cfg('allowed_mime')[$mime])) {
        $errors['receipt'] = $M['file_type'];
    }
}

// 10. Titularidad
if (($_POST['ownership'] ?? '') !== '1') {
    $errors['ownership'] = $M['ownership'];
}

if ($errors) {
    json_error($M['fix_errors'], 422, ['errors' => $errors]);
}

// Duplicado: misma referencia + mismo banco + mismo país
if (repo()->referenceExists($countryId, $bankId, $reference)) {
    json_error($M['duplicate'], 409, ['errors' => ['reference_number' => $M['duplicate']]]);
}

// Guardar archivo con nombre aleatorio
$relDir = $country['slug'] . '/' . date('Y/m');
$absDir = cfg('upload_dir') . '/' . $relDir;
if (!is_dir($absDir) && !@mkdir($absDir, 0750, true) && !is_dir($absDir)) {
    json_error($M['upload_fail'], 500);
}
$storedName = bin2hex(random_bytes(16)) . '.' . cfg('allowed_mime')[$mime];
if (!move_uploaded_file($file['tmp_name'], $absDir . '/' . $storedName)) {
    json_error($M['upload_fail'], 500);
}
$originalName = mb_substr(preg_replace('/[^\pL\pN._\- ]/u', '_', basename((string) $file['name'])) ?: 'comprobante', 0, 200);

try {
    $id = repo()->createTransfer([
        'country_id'            => $countryId,
        'email'                 => $email,
        'full_name'             => $fullName,
        'dni'                   => $dni,
        'deposit_code'          => $depositCode,
        'bank_id'               => $bankId,
        'transfer_date'         => $date,
        'amount'                => number_format((float) $amount, 2, '.', ''),
        'reference_number'      => $reference,
        'receipt_path'          => $relDir . '/' . $storedName,
        'receipt_original_name' => $originalName,
        'receipt_mime'          => $mime,
        'receipt_size'          => (int) $file['size'],
        'ownership_confirmed'   => 1,
        'ip_address'            => client_ip(),
        'user_agent'            => mb_substr((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 255),
    ]);
} catch (DuplicateReferenceException) {
    @unlink($absDir . '/' . $storedName);
    json_error($M['duplicate'], 409, ['errors' => ['reference_number' => $M['duplicate']]]);
} catch (Throwable $e) {
    @unlink($absDir . '/' . $storedName);
    throw $e;
}

$folio = transfer_folio($country['code'], $id);
json_response(['ok' => true, 'folio' => $folio, 'message' => sprintf($M['success'], $folio)], 201);
