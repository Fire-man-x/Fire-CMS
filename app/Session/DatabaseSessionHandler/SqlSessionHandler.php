<?php
declare(strict_types=1);

namespace App\Session\DatabaseSessionHandler;

use App\Session\DatabaseSessionHandler;
use Nette\Database\Connection;

/**
 * Společná část MySQL a PostgreSQL handleru - tabulka `id` (SHA-256 hash ID), `data` (base64), `expiresAt` (unix
 * timestamp). Liší se jen zámek a INSERT s aktualizací existujícího řádku. Schéma tabulky viz docs/Architecture/sessions.md.
 *
 * Data jsou v base64: serializovaná session může obsahovat libovolné bajty (neplatné UTF-8, \0), které by textový
 * sloupec nebo parametr dotazu v UTF-8 připojení poškodil. Názvy sloupců jsou v SQL bez uvozovek, v PostgreSQL se tak
 * shodují s tabulkou vytvořenou také bez uvozovek (`expiresAt` = `expiresat`).
 */
abstract class SqlSessionHandler extends DatabaseSessionHandler
{
	/** Kratší prodloužení platnosti se nezapisuje - ušetří UPDATE při každém požadavku beze změny dat */
	private const int TouchThreshold = 60;

	private bool $driverChecked = false;


	public function __construct(
		protected readonly Connection $connection,
		protected readonly string $table = 'system_sessions',
		int $lockTimeout = 30,
	) {
		parent::__construct($lockTimeout);
	}


	/**
	 * Název PDO driveru, se kterým handler umí pracovat (`mysql`, `pgsql`)
	 */
	abstract protected function getDriverName(): string;


	abstract protected function upsert(string $key, string $data, int $expiresAt): void;


	public function open(string $path, string $name): bool
	{
		// připojení se předává konfigurací (sessionHandler: connection:) - chybné (např. MySQL pro PostgreSQL handler)
		// by jinak skončilo nesrozumitelnou chybou SQL
		if (!$this->driverChecked) {
			$driver = $this->connection->getPdo()->getAttribute(\PDO::ATTR_DRIVER_NAME);
			if ($driver !== $this->getDriverName()) {
				throw new \LogicException(sprintf("%s needs a '%s' database connection, '%s' given (sessionHandler: connection:).", static::class, $this->getDriverName(), is_string($driver) ? $driver : '?'));
			}
			$this->driverChecked = true;
		}

		return true;
	}


	protected function fetch(string $key): ?string
	{
		$stored = $this->connection->query('SELECT data FROM ?name WHERE id = ? AND expiresAt > ?', $this->table, $key, time())
			->fetchField();
		if (!is_string($stored)) {
			return null;
		}

		$data = base64_decode($stored, true);

		return $data === false ? null : $data;
	}


	protected function store(string $key, string $data, int $lifetime): void
	{
		$this->upsert($key, base64_encode($data), time() + $lifetime);
	}


	protected function touch(string $key, string $data, int $lifetime): void
	{
		$expiresAt = time() + $lifetime;
		$this->connection->query(
			'UPDATE ?name SET expiresAt = ? WHERE id = ? AND expiresAt < ?',
			$this->table,
			$expiresAt,
			$key,
			$expiresAt - self::TouchThreshold,
		);
	}


	protected function exists(string $key): bool
	{
		return $this->connection->query('SELECT 1 FROM ?name WHERE id = ? AND expiresAt > ?', $this->table, $key, time())
			->fetchField() !== null;
	}


	protected function remove(string $key): void
	{
		$this->connection->query('DELETE FROM ?name WHERE id = ?', $this->table, $key);
	}


	public function deleteExpired(): int
	{
		return $this->connection->query('DELETE FROM ?name WHERE expiresAt <= ?', $this->table, time())
			->getRowCount() ?? 0;
	}

}
