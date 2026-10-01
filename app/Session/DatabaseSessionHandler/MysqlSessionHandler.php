<?php
declare(strict_types=1);

namespace App\Session\DatabaseSessionHandler;

/**
 * Sessions v MySQL/MariaDB (`sessionHandler: driver: mysql`). Tabulku `system_sessions` zakládá migrace jádra
 * (data/migrations/structures/20260929140000.sql).
 *
 * Zámek je GET_LOCK() - drží ho připojení, takže se při pádu požadavku (ukončení připojení) sám uvolní. Názvy zámků
 * jsou společné pro celý MySQL server, proto prefix.
 */
final class MysqlSessionHandler extends SqlSessionHandler
{

	protected function getDriverName(): string
	{
		return 'mysql';
	}


	protected function upsert(string $key, string $data, int $expiresAt): void
	{
		$this->connection->query(
			'INSERT INTO ?name (id, data, expiresAt) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE data = ?, expiresAt = ?',
			$this->table,
			$key,
			$data,
			$expiresAt,
			$data,
			$expiresAt,
		);
	}


	protected function acquireLock(string $key, int $timeout): bool
	{
		// GET_LOCK čeká sám: 1 = získán, 0 = vypršel čas, NULL = chyba
		$acquired = $this->connection->query('SELECT GET_LOCK(?, ?)', $this->lockName($key), $timeout)->fetchField();

		return is_numeric($acquired) && (int) $acquired === 1;
	}


	protected function releaseLock(string $key): void
	{
		$this->connection->query('SELECT RELEASE_LOCK(?)', $this->lockName($key));
	}


	/**
	 * MySQL povoluje název zámku nejvýš 64 znaků
	 */
	private function lockName(string $key): string
	{
		return 'session_' . substr($key, 0, 56);
	}

}
