<?php
declare(strict_types=1);

function valid_date(string $value): bool
{
    $d = DateTime::createFromFormat('Y-m-d', $value);
    return $d !== false && $d->format('Y-m-d') === $value;
}

/**
 * Limpia los filtros que llegan del panel. El resultado lo usan
 * tanto JsonRepository como SqlServerRepository.
 */
function normalize_filters(array $q): array
{
    $text = static fn(string $k): string => trim((string) ($q[$k] ?? ''));
    $f = [];
    foreach (['reference', 'deposit_code', 'dni', 'q'] as $k) {
        if (($v = $text($k)) !== '') {
            $f[$k] = mb_substr($v, 0, 100);
        }
    }
    if (($v = $text('folio')) !== '' && preg_match('/(\d+)\s*$/', $v, $m)) {
        $f['folio_id'] = (int) $m[1];
    }
    if (($v = (int) ($q['bank_id'] ?? 0)) > 0) {
        $f['bank_id'] = $v;
    }
    if (in_array($text('status'), ['pending', 'checked'], true)) {
        $f['status'] = $text('status');
    }
    foreach (['date_from', 'date_to'] as $k) {
        if (($v = $text($k)) !== '' && valid_date($v)) {
            $f[$k] = $v;
        }
    }
    if (($v = str_replace([',', ' '], '', $text('amount'))) !== '' && is_numeric($v)) {
        $f['amount'] = number_format((float) $v, 2, '.', '');
    }
    return $f;
}

/** Convierte un registro al formato que consume el panel */
function present_transfer(array $row, string $countryCode): array
{
    return [
        'id'                    => (int) $row['id'],
        'folio'                 => transfer_folio($countryCode, (int) $row['id']),
        'created_at'            => utc_to_iso($row['created_at']),
        'email'                 => $row['email'],
        'full_name'             => $row['full_name'],
        'dni'                   => $row['dni'],
        'deposit_code'          => $row['deposit_code'],
        'bank_id'               => (int) $row['bank_id'],
        'bank_name'             => $row['bank_name'] ?? '',
        'transfer_date'         => substr((string) $row['transfer_date'], 0, 10),
        'amount'                => number_format((float) $row['amount'], 2, '.', ''),
        'reference_number'      => $row['reference_number'],
        'receipt_original_name' => $row['receipt_original_name'],
        'receipt_mime'          => $row['receipt_mime'],
        'ownership_confirmed'   => (bool) (int) $row['ownership_confirmed'],
        'checked'               => (bool) (int) $row['checked'],
        'checked_at'            => utc_to_iso($row['checked_at'] ?? null),
        'checked_by_name'       => $row['checked_by_name'] ?? null,
    ];
}
