<?php
declare(strict_types=1);

/**
 * MODO SIN BASE DE DATOS.
 * Guarda usuarios, transferencias y bitácora en archivos JSON dentro de data_dir.
 * Usa un bloqueo de archivo para evitar que dos envíos simultáneos se pisen.
 *
 * Pensado para la etapa de estructura / pruebas. Para producción con volumen
 * real cambiar a DATA_DRIVER=sqlsrv.
 */
final class JsonRepository implements TransferRepository
{
    private const USERS     = 'users.json';
    private const TRANSFERS = 'transfers.json';
    private const AUDIT     = 'audit.log';

    public function __construct(private string $dir, private array $catalog)
    {
        if (!is_dir($dir) && !@mkdir($dir, 0750, true) && !is_dir($dir)) {
            throw new RuntimeException("No se pudo crear la carpeta de datos: $dir");
        }
    }

    // ------------------------------------------------------------------ catálogo
    public function countries(): array
    {
        return array_values(array_filter($this->catalog['countries'], static fn($c) => $c['active'] ?? true));
    }

    public function findCountry(string $slug): ?array
    {
        foreach ($this->countries() as $c) {
            if ($c['slug'] === $slug) {
                return $c;
            }
        }
        return null;
    }

    private function countryById(int $id): ?array
    {
        foreach ($this->countries() as $c) {
            if ($c['id'] === $id) {
                return $c;
            }
        }
        return null;
    }

    public function banks(int $countryId): array
    {
        $country = $this->countryById($countryId);
        $out = [];
        foreach ($this->catalog['banks'] as $id => $bank) {
            if ($country && $bank['country'] === $country['slug'] && ($bank['active'] ?? true)) {
                $out[] = ['id' => (int) $id, 'name' => $bank['name']];
            }
        }
        return $out;
    }

    public function bankBelongsToCountry(int $bankId, int $countryId): bool
    {
        foreach ($this->banks($countryId) as $b) {
            if ($b['id'] === $bankId) {
                return true;
            }
        }
        return false;
    }

    private function bankName(int $id): string
    {
        return $this->catalog['banks'][$id]['name'] ?? '';
    }

    // ------------------------------------------------------------------ usuarios
    public function findUserForLogin(string $username): ?array
    {
        foreach ($this->load(self::USERS) as $u) {
            if (strcasecmp($u['username'], $username) === 0) {
                return [
                    'id'            => (int) $u['id'],
                    'password_hash' => $u['password_hash'],
                    'active'        => (bool) $u['active'],
                    'is_locked'     => !empty($u['locked_until']) && strtotime($u['locked_until'] . ' UTC') > time(),
                ];
            }
        }
        return null;
    }

    public function findActiveUser(int $id): ?array
    {
        foreach ($this->load(self::USERS) as $u) {
            if ((int) $u['id'] === $id && $u['active']) {
                $c = $this->countryById((int) $u['country_id']);
                if (!$c) {
                    return null;
                }
                return [
                    'id'           => (int) $u['id'],
                    'username'     => $u['username'],
                    'full_name'    => $u['full_name'],
                    'country_id'   => $c['id'],
                    'country_code' => $c['code'],
                    'country_slug' => $c['slug'],
                    'country_name' => $c['name'],
                    'language'     => $c['language'],
                    'currency'     => $c['currency'],
                ];
            }
        }
        return null;
    }

    public function registerFailedLogin(int $userId, int $maxAttempts, int $lockMinutes): void
    {
        $this->updateUser($userId, static function (array $u) use ($maxAttempts, $lockMinutes): array {
            $fails = (int) $u['failed_attempts'] + 1;
            if ($fails >= $maxAttempts) {
                $u['failed_attempts'] = 0;
                $u['locked_until']    = gmdate('Y-m-d H:i:s', time() + 60 * $lockMinutes);
            } else {
                $u['failed_attempts'] = $fails;
            }
            return $u;
        });
    }

    public function registerSuccessfulLogin(int $userId, ?string $newPasswordHash = null): void
    {
        $this->updateUser($userId, static function (array $u) use ($newPasswordHash): array {
            $u['failed_attempts'] = 0;
            $u['locked_until']    = null;
            $u['last_login_at']   = gmdate('Y-m-d H:i:s');
            if ($newPasswordHash) {
                $u['password_hash'] = $newPasswordHash;
            }
            return $u;
        });
    }

    public function saveUser(int $countryId, string $username, string $fullName, string $passwordHash): void
    {
        $this->locked(function () use ($countryId, $username, $fullName, $passwordHash): void {
            $users = $this->load(self::USERS);
            foreach ($users as &$u) {
                if (strcasecmp($u['username'], $username) === 0) {
                    $u = array_merge($u, [
                        'country_id' => $countryId, 'full_name' => $fullName, 'password_hash' => $passwordHash,
                        'active' => true, 'failed_attempts' => 0, 'locked_until' => null,
                    ]);
                    $this->save(self::USERS, $users);
                    return;
                }
            }
            unset($u);
            $users[] = [
                'id'              => $users ? max(array_column($users, 'id')) + 1 : 1,
                'country_id'      => $countryId,
                'username'        => $username,
                'full_name'       => $fullName,
                'password_hash'   => $passwordHash,
                'active'          => true,
                'failed_attempts' => 0,
                'locked_until'    => null,
                'last_login_at'   => null,
                'created_at'      => gmdate('Y-m-d H:i:s'),
            ];
            $this->save(self::USERS, $users);
        });
    }

    private function updateUser(int $userId, callable $change): void
    {
        $this->locked(function () use ($userId, $change): void {
            $users = $this->load(self::USERS);
            foreach ($users as $i => $u) {
                if ((int) $u['id'] === $userId) {
                    $users[$i] = $change($u);
                    $this->save(self::USERS, $users);
                    return;
                }
            }
        });
    }

    // ------------------------------------------------------------- transferencias
    public function referenceExists(int $countryId, int $bankId, string $reference): bool
    {
        return $this->findDuplicate($this->transfers()['items'], $countryId, $bankId, $reference);
    }

    public function createTransfer(array $data): int
    {
        return $this->locked(function () use ($data): int {
            $db = $this->transfers();
            if ($this->findDuplicate($db['items'], (int) $data['country_id'], (int) $data['bank_id'], $data['reference_number'])) {
                throw new DuplicateReferenceException('Referencia duplicada');
            }
            $id = (int) $db['next_id'];
            $db['items'][] = $data + [
                'id'              => $id,
                'checked'         => 0,
                'checked_at'      => null,
                'checked_by'      => null,
                'created_at'      => gmdate('Y-m-d H:i:s'),
            ];
            $db['next_id'] = $id + 1;
            $this->save(self::TRANSFERS, $db);
            return $id;
        });
    }

    public function searchTransfers(int $countryId, array $filters, int $page, int $perPage): array
    {
        $rows = $this->filtered($countryId, $filters);
        return [
            'rows'  => array_map(fn($r) => $this->decorate($r), array_slice($rows, ($page - 1) * $perPage, $perPage)),
            'total' => count($rows),
        ];
    }

    public function exportTransfers(int $countryId, array $filters): iterable
    {
        foreach ($this->filtered($countryId, $filters) as $r) {
            yield $this->decorate($r);
        }
    }

    public function summary(int $countryId): array
    {
        $total = $checked = 0;
        foreach ($this->transfers()['items'] as $t) {
            if ((int) $t['country_id'] === $countryId) {
                $total++;
                $checked += (int) $t['checked'];
            }
        }
        return ['total' => $total, 'checked' => $checked, 'pending' => $total - $checked];
    }

    public function findTransfer(int $id, int $countryId): ?array
    {
        foreach ($this->transfers()['items'] as $t) {
            if ((int) $t['id'] === $id && (int) $t['country_id'] === $countryId) {
                return $this->decorate($t);
            }
        }
        return null;
    }

    public function markChecked(int $id, int $countryId, int $userId): string
    {
        return $this->locked(function () use ($id, $countryId, $userId): string {
            $db = $this->transfers();
            foreach ($db['items'] as $i => $t) {
                if ((int) $t['id'] === $id && (int) $t['country_id'] === $countryId) {
                    if ((int) $t['checked'] === 1) {
                        return 'already_checked';
                    }
                    $db['items'][$i]['checked']    = 1;
                    $db['items'][$i]['checked_at'] = gmdate('Y-m-d H:i:s');
                    $db['items'][$i]['checked_by'] = $userId;
                    $this->save(self::TRANSFERS, $db);
                    return 'ok';
                }
            }
            return 'not_found';
        });
    }

    // ------------------------------------------------------------------ bitácora
    public function audit(?int $userId, ?int $transferId, string $action, string $detail, string $ip): void
    {
        $line = json_encode([
            'at' => gmdate('Y-m-d H:i:s'), 'user_id' => $userId, 'transfer_id' => $transferId,
            'action' => $action, 'detail' => $detail, 'ip' => $ip,
        ], JSON_UNESCAPED_UNICODE) . PHP_EOL;
        file_put_contents($this->dir . '/' . self::AUDIT, $line, FILE_APPEND | LOCK_EX);
    }

    // ------------------------------------------------------------------ internos
    private function transfers(): array
    {
        return $this->load(self::TRANSFERS) + ['next_id' => 1, 'items' => []];
    }

    private function findDuplicate(array $items, int $countryId, int $bankId, string $reference): bool
    {
        foreach ($items as $t) {
            if ((int) $t['country_id'] === $countryId && (int) $t['bank_id'] === $bankId
                && mb_strtolower($t['reference_number']) === mb_strtolower($reference)) {
                return true;
            }
        }
        return false;
    }

    private function filtered(int $countryId, array $f): array
    {
        $has = static fn(string $field, string $needle): bool => mb_stripos($field, $needle) !== false;
        $rows = array_filter($this->transfers()['items'], static function (array $t) use ($countryId, $f, $has): bool {
            if ((int) $t['country_id'] !== $countryId) return false;
            if (isset($f['reference']) && !$has($t['reference_number'], $f['reference'])) return false;
            if (isset($f['deposit_code']) && !$has($t['deposit_code'], $f['deposit_code'])) return false;
            if (isset($f['dni']) && !$has($t['dni'], $f['dni'])) return false;
            if (isset($f['q']) && !$has($t['full_name'], $f['q']) && !$has($t['email'], $f['q'])) return false;
            if (isset($f['folio_id']) && (int) $t['id'] !== $f['folio_id']) return false;
            if (isset($f['bank_id']) && (int) $t['bank_id'] !== $f['bank_id']) return false;
            if (($f['status'] ?? '') === 'pending' && (int) $t['checked'] === 1) return false;
            if (($f['status'] ?? '') === 'checked' && (int) $t['checked'] !== 1) return false;
            if (isset($f['date_from']) && $t['transfer_date'] < $f['date_from']) return false;
            if (isset($f['date_to']) && $t['transfer_date'] > $f['date_to']) return false;
            if (isset($f['amount']) && $t['amount'] !== $f['amount']) return false;
            return true;
        });
        usort($rows, static fn($a, $b) => [$b['created_at'], $b['id']] <=> [$a['created_at'], $a['id']]);
        return $rows;
    }

    private function decorate(array $t): array
    {
        $t['bank_name'] = $this->bankName((int) $t['bank_id']);
        $t['checked_by_name'] = null;
        if (!empty($t['checked_by'])) {
            foreach ($this->load(self::USERS) as $u) {
                if ((int) $u['id'] === (int) $t['checked_by']) {
                    $t['checked_by_name'] = $u['full_name'];
                }
            }
        }
        return $t;
    }

    private function load(string $file): array
    {
        $path = $this->dir . '/' . $file;
        if (!is_file($path)) {
            return [];
        }
        $data = json_decode((string) file_get_contents($path), true);
        return is_array($data) ? $data : [];
    }

    private function save(string $file, array $data): void
    {
        $path = $this->dir . '/' . $file;
        $tmp  = $path . '.' . bin2hex(random_bytes(4)) . '.tmp';
        file_put_contents($tmp, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        rename($tmp, $path);
    }

    /** Ejecuta $fn con bloqueo exclusivo (una escritura a la vez) */
    private function locked(callable $fn): mixed
    {
        $fh = fopen($this->dir . '/.lock', 'c');
        if ($fh === false) {
            throw new RuntimeException('No se pudo bloquear el almacenamiento de datos');
        }
        try {
            flock($fh, LOCK_EX);
            return $fn();
        } finally {
            flock($fh, LOCK_UN);
            fclose($fh);
        }
    }
}
