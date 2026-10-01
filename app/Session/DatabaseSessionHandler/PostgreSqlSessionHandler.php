<?php
declare(strict_types=1);

namespace App\Session\DatabaseSessionHandler;

/**
 * Sessions v PostgreSQL (`sessionHandler: driver: postgresql`). Potřebuje PHP rozšíření pdo_pgsql a připojení
 * na PostgreSQL v sekci `database:` (sessionHandler: connection: @database.<název>). Tabulka se zakládá ručně,
 * SQL viz docs/Architecture/sessions.md.
 *
 * Zámek je session-level advisory lock (drží ho připojení, při pádu požadavku se sám uvolní). pg_advisory_lock()
 * neumí timeout, proto se opakuje pg_try_advisory_lock(). Nefunguje přes PgBouncer v režimu transaction pooling
 * (zámek by zůstal na jiném serverovém připojení).
 */
final class PostgreSqlSessionHandler extends SqlSessionHandler
{

	protected function getDriverName(): string
	{
		return 'pgsql';
	}


	protected function upsert(string $key, string $data, int $expiresAt): void
	{
		$this->connection->query(
			'INSERT INTO ?name (id, data, expiresAt) VALUES (?, ?, ?)
			ON CONFLICT (id) DO UPDATE SET data = EXCLUDED.data, expiresAt = EXCLUDED.expiresAt',
			$this->table,
			$key,
			$data,
			$expiresAt,
		);
	}


	protected function acquireLock(string $key, int $timeout): bool
	{
		$lockId = $this->lockId($key);

		return $this->retryUntil(
			fn(): bool => $this->connection->query('SELECT pg_try_advisory_lock(CAST(? AS bigint))', $lockId)->fetchField() === true,
			$timeout,
		);
	}


	protected function releaseLock(string $key): void
	{
		$this->connection->query('SELECT pg_advisory_unlock(CAST(? AS bigint))', $this->lockId($key));
	}


	/**
	 * Advisory lock má jako klíč bigint - prvních 60 bitů hashe (kladné číslo, vejde se do PHP int)
	 */
	private function lockId(string $key): int
	{
		return (int) hexdec(substr($key, 0, 15));
	}

}
