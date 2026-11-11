<?php
declare(strict_types=1);

/**
 * AZURE SQL / SQL SERVER  (preparado, aún NO se usa).
 * Se activa con la variable DATA_DRIVER=sqlsrv y las variables DB_*.
 * Requiere la extensión pdo_sqlsrv (incluida en las imágenes PHP 8 de App Service Linux).
 * El esquema está en backend/database/schema.sql
 */
final class SqlServerRepository implements TransferRepository
{
    private ?PDO $pdo = null;

    public function __construct(private array $cfg)
    {
    }

    private function pdo(): PDO
    {
        if ($this->pdo === null) {
            if (!extension_loaded('pdo_sqlsrv')) {
                throw new RuntimeException('Falta la extensión pdo_sqlsrv');
            }
            $c = $this->cfg;
            $dsn = sprintf(
                'sqlsrv:Server=tcp:%s,%d;Database=%s;Encrypt=%s;TrustServerCertificate=%s;LoginTimeout=30',
                $c['host'], $c['port'], $c['name'], $c['encrypt'] ? 'yes' : 'no', $c['trust_cert'] ? 'yes' : 'no'
            );
            $user = $c['user'] ?: null;
            $pass = $c['pass'] ?: null;
            if ($c['auth'] === 'msi') {
                $dsn .= ';Authentication=ActiveDirectoryMsi'; // Managed Identity: sin contraseña
                $pass = null;
            }
            $options = [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC];
            if (defined('PDO::SQLSRV_ATTR_FORMAT_DECIMALS')) {
                $options[PDO::SQLSRV_ATTR_FORMAT_DECIMALS] = true;
            }
            $this->pdo = new PDO($dsn, $user, $pass, $options);
        }
        return $this->pdo;
    }

    private function one(string $sql, array $params = []): ?array
    {
        $st = $this->pdo()->prepare($sql);
        $st->execute($params);
        return $st->fetch() ?: null;
    }

    private function all(string $sql, array $params = []): array
    {
        $st = $this->pdo()->prepare($sql);
        $st->execute($params);
        return $st->fetchAll();
    }

    private function exec(string $sql, array $params = []): void
    {
        $this->pdo()->prepare($sql)->execute($params);
    }

    private static function castCountry(array $c): array
    {
        $c['id'] = (int) $c['id'];
        $c['code'] = trim($c['code']);
        return $c;
    }

    // ------------------------------------------------------------------ catálogo
    public function countries(): array
    {
        return array_map([self::class, 'castCountry'],
            $this->all('SELECT id, code, slug, name, lang AS language, currency FROM countries WHERE active = 1 ORDER BY id'));
    }

    public function findCountry(string $slug): ?array
    {
        $c = $this->one('SELECT id, code, slug, name, lang AS language, currency FROM countries WHERE slug = ? AND active = 1', [$slug]);
        return $c ? self::castCountry($c) : null;
    }

    public function banks(int $countryId): array
    {
        return array_map(static fn($b) => ['id' => (int) $b['id'], 'name' => $b['name']],
            $this->all('SELECT id, name FROM banks WHERE country_id = ? AND active = 1 ORDER BY sort_order, name', [$countryId]));
    }

    public function bankBelongsToCountry(int $bankId, int $countryId): bool
    {
        return (bool) $this->one('SELECT 1 AS x FROM banks WHERE id = ? AND country_id = ? AND active = 1', [$bankId, $countryId]);
    }

    // ------------------------------------------------------------------ usuarios
    public function findUserForLogin(string $username): ?array
    {
        $u = $this->one(
            'SELECT id, password_hash, active,
                    CASE WHEN locked_until > SYSUTCDATETIME() THEN 1 ELSE 0 END AS is_locked
               FROM users WHERE username = ?', [$username]);
        return $u ? ['id' => (int) $u['id'], 'password_hash' => $u['password_hash'],
                     'active' => (bool) (int) $u['active'], 'is_locked' => (bool) (int) $u['is_locked']] : null;
    }

    public function findActiveUser(int $id): ?array
    {
        $u = $this->one(
            'SELECT u.id, u.username, u.full_name, u.country_id,
                    c.code AS country_code, c.slug AS country_slug, c.name AS country_name,
                    c.lang AS language, c.currency
               FROM users u JOIN countries c ON c.id = u.country_id
              WHERE u.id = ? AND u.active = 1 AND c.active = 1', [$id]);
        if (!$u) {
            return null;
        }
        $u['id'] = (int) $u['id'];
        $u['country_id'] = (int) $u['country_id'];
        $u['country_code'] = trim($u['country_code']);
        return $u;
    }

    public function registerFailedLogin(int $userId, int $maxAttempts, int $lockMinutes): void
    {
        // En SQL Server el lado derecho del SET usa los valores ANTERIORES a la actualización
        $this->exec(
            "UPDATE users
                SET failed_attempts = CASE WHEN failed_attempts + 1 >= $maxAttempts THEN 0 ELSE failed_attempts + 1 END,
                    locked_until    = CASE WHEN failed_attempts + 1 >= $maxAttempts
                                           THEN DATEADD(MINUTE, $lockMinutes, SYSUTCDATETIME()) ELSE locked_until END
              WHERE id = ?", [$userId]);
    }

    public function registerSuccessfulLogin(int $userId, ?string $newPasswordHash = null): void
    {
        $this->exec('UPDATE users SET failed_attempts = 0, locked_until = NULL, last_login_at = SYSUTCDATETIME() WHERE id = ?', [$userId]);
        if ($newPasswordHash) {
            $this->exec('UPDATE users SET password_hash = ? WHERE id = ?', [$newPasswordHash, $userId]);
        }
    }

    public function saveUser(int $countryId, string $username, string $fullName, string $passwordHash): void
    {
        $existing = $this->one('SELECT id FROM users WHERE username = ?', [$username]);
        if ($existing) {
            $this->exec('UPDATE users SET country_id = ?, full_name = ?, password_hash = ?, active = 1,
                                failed_attempts = 0, locked_until = NULL WHERE id = ?',
                [$countryId, $fullName, $passwordHash, $existing['id']]);
        } else {
            $this->exec('INSERT INTO users (country_id, username, full_name, password_hash) VALUES (?, ?, ?, ?)',
                [$countryId, $username, $fullName, $passwordHash]);
        }
    }

    // ------------------------------------------------------------- transferencias
    public function referenceExists(int $countryId, int $bankId, string $reference): bool
    {
        return (bool) $this->one('SELECT 1 AS x FROM transfers WHERE country_id = ? AND bank_id = ? AND reference_number = ?',
            [$countryId, $bankId, $reference]);
    }

    public function createTransfer(array $data): int
    {
        try {
            $this->exec(
                'INSERT INTO transfers
                    (country_id, email, full_name, dni, deposit_code, bank_id, transfer_date, amount, reference_number,
                     receipt_path, receipt_original_name, receipt_mime, receipt_size, ownership_confirmed, ip_address, user_agent)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
                [
                    $data['country_id'], $data['email'], $data['full_name'], $data['dni'], $data['deposit_code'],
                    $data['bank_id'], $data['transfer_date'], $data['amount'], $data['reference_number'],
                    $data['receipt_path'], $data['receipt_original_name'], $data['receipt_mime'], $data['receipt_size'],
                    $data['ownership_confirmed'], $data['ip_address'], $data['user_agent'],
                ]);
        } catch (PDOException $e) {
            if ($e->getCode() === '23000') {
                throw new DuplicateReferenceException('Referencia duplicada', 0, $e);
            }
            throw $e;
        }
        return (int) $this->pdo()->lastInsertId();
    }

    private const SELECT = 'SELECT t.*, b.name AS bank_name, cu.full_name AS checked_by_name
                              FROM transfers t
                              JOIN banks b ON b.id = t.bank_id
                         LEFT JOIN users cu ON cu.id = t.checked_by';

    /** @return array{0:string,1:array} */
    private function where(int $countryId, array $f): array
    {
        $w = ['t.country_id = ?'];
        $p = [$countryId];
        $like = static fn(string $v): string => '%' . addcslashes($v, '%_[\\') . '%';
        foreach (['reference' => 't.reference_number', 'deposit_code' => 't.deposit_code', 'dni' => 't.dni'] as $k => $col) {
            if (isset($f[$k])) {
                $w[] = "$col LIKE ? ESCAPE '\\'";
                $p[] = $like($f[$k]);
            }
        }
        if (isset($f['q'])) {
            $w[] = "(t.full_name LIKE ? ESCAPE '\\' OR t.email LIKE ? ESCAPE '\\')";
            $p[] = $like($f['q']);
            $p[] = $like($f['q']);
        }
        if (isset($f['folio_id'])) { $w[] = 't.id = ?';             $p[] = $f['folio_id']; }
        if (isset($f['bank_id']))  { $w[] = 't.bank_id = ?';        $p[] = $f['bank_id']; }
        if (isset($f['date_from'])) { $w[] = 't.transfer_date >= ?'; $p[] = $f['date_from']; }
        if (isset($f['date_to']))   { $w[] = 't.transfer_date <= ?'; $p[] = $f['date_to']; }
        if (isset($f['amount']))    { $w[] = 't.amount = ?';         $p[] = $f['amount']; }
        if (($f['status'] ?? '') === 'pending') { $w[] = 't.checked = 0'; }
        if (($f['status'] ?? '') === 'checked') { $w[] = 't.checked = 1'; }
        return [implode(' AND ', $w), $p];
    }

    public function searchTransfers(int $countryId, array $filters, int $page, int $perPage): array
    {
        [$where, $params] = $this->where($countryId, $filters);
        $total  = (int) $this->one("SELECT COUNT(*) AS n FROM transfers t WHERE $where", $params)['n'];
        $offset = ($page - 1) * $perPage;
        $rows = $this->all(self::SELECT . " WHERE $where ORDER BY t.created_at DESC, t.id DESC
                                            OFFSET $offset ROWS FETCH NEXT $perPage ROWS ONLY", $params);
        return ['rows' => $rows, 'total' => $total];
    }

    public function exportTransfers(int $countryId, array $filters): iterable
    {
        [$where, $params] = $this->where($countryId, $filters);
        $st = $this->pdo()->prepare(self::SELECT . " WHERE $where ORDER BY t.created_at DESC, t.id DESC");
        $st->execute($params);
        while ($row = $st->fetch()) {
            yield $row;
        }
    }

    public function summary(int $countryId): array
    {
        $s = $this->one('SELECT COUNT(*) AS total, COALESCE(SUM(CASE WHEN checked = 1 THEN 1 ELSE 0 END), 0) AS checked
                           FROM transfers WHERE country_id = ?', [$countryId]);
        return ['total' => (int) $s['total'], 'checked' => (int) $s['checked'], 'pending' => (int) $s['total'] - (int) $s['checked']];
    }

    public function findTransfer(int $id, int $countryId): ?array
    {
        return $this->one(self::SELECT . ' WHERE t.id = ? AND t.country_id = ?', [$id, $countryId]);
    }

    public function markChecked(int $id, int $countryId, int $userId): string
    {
        $pdo = $this->pdo();
        $pdo->beginTransaction();
        try {
            $row = $this->one('SELECT checked FROM transfers WITH (UPDLOCK, ROWLOCK) WHERE id = ? AND country_id = ?', [$id, $countryId]);
            if (!$row) {
                $pdo->rollBack();
                return 'not_found';
            }
            if ((int) $row['checked'] === 1) {
                $pdo->rollBack();
                return 'already_checked';
            }
            $this->exec('UPDATE transfers SET checked = 1, checked_at = SYSUTCDATETIME(), checked_by = ? WHERE id = ?', [$userId, $id]);
            $pdo->commit();
            return 'ok';
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        }
    }

    // ------------------------------------------------------------------ bitácora
    public function audit(?int $userId, ?int $transferId, string $action, string $detail, string $ip): void
    {
        $this->exec('INSERT INTO audit_log (user_id, transfer_id, action, detail, ip_address) VALUES (?, ?, ?, ?, ?)',
            [$userId, $transferId, $action, $detail, $ip]);
    }
}
