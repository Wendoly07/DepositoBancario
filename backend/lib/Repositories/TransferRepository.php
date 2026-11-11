<?php
declare(strict_types=1);

class DuplicateReferenceException extends RuntimeException
{
}

/**
 * Contrato de acceso a datos. Cualquier origen (JSON hoy, SQL Server después)
 * implementa estos métodos y el resto del sistema no cambia.
 *
 * REGLA DE NEGOCIO: no existen métodos para eliminar transferencias ni para
 * quitar el check. markChecked() sólo funciona una vez por registro.
 *
 * Todas las fechas/hora se manejan en UTC con formato 'Y-m-d H:i:s'.
 */
interface TransferRepository
{
    /** @return array<int,array{id:int,code:string,slug:string,name:string,language:string,currency:string}> */
    public function countries(): array;

    public function findCountry(string $slug): ?array;

    /** @return array<int,array{id:int,name:string}> */
    public function banks(int $countryId): array;

    public function bankBelongsToCountry(int $bankId, int $countryId): bool;

    // ---- Usuarios del panel ----
    /** @return array{id:int,password_hash:string,active:bool,is_locked:bool}|null */
    public function findUserForLogin(string $username): ?array;

    /** Usuario activo + datos de su país (country_code, country_slug, country_name, language, currency) */
    public function findActiveUser(int $id): ?array;

    public function registerFailedLogin(int $userId, int $maxAttempts, int $lockMinutes): void;

    public function registerSuccessfulLogin(int $userId, ?string $newPasswordHash = null): void;

    /** Crea el usuario o, si ya existe, actualiza país, nombre y contraseña */
    public function saveUser(int $countryId, string $username, string $fullName, string $passwordHash): void;

    // ---- Transferencias ----
    public function referenceExists(int $countryId, int $bankId, string $reference): bool;

    /** @throws DuplicateReferenceException */
    public function createTransfer(array $data): int;

    /** @return array{rows:array,total:int} filas con bank_name y checked_by_name */
    public function searchTransfers(int $countryId, array $filters, int $page, int $perPage): array;

    public function exportTransfers(int $countryId, array $filters): iterable;

    /** @return array{total:int,checked:int,pending:int} */
    public function summary(int $countryId): array;

    public function findTransfer(int $id, int $countryId): ?array;

    /** @return string ok | not_found | already_checked */
    public function markChecked(int $id, int $countryId, int $userId): string;

    // ---- Bitácora ----
    public function audit(?int $userId, ?int $transferId, string $action, string $detail, string $ip): void;
}
