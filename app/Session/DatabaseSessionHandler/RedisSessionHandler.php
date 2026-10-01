<?php
declare(strict_types=1);

namespace App\Session\DatabaseSessionHandler;

use App\Session\DatabaseSessionHandler;

/**
 * Sessions v Redisu (`sessionHandler: driver: redis`). Klíč `<prefix><hash ID>` s expirací (SET ... EX), prošlé
 * sessions maže Redis sám - deleteExpired() nic nedělá a cron úloha se neregistruje.
 *
 * Zámek je klíč `<prefix>lock:<hash ID>` (SET NX PX) s náhodným tokenem, uvolní ho jen ten, kdo ho získal (Lua skript).
 * Zámek má vlastní expiraci $lockTtl sekund - po pádu požadavku nezůstane viset; déle běžící požadavek o něj přijde.
 *
 * Více projektů na jednom Redisu: každý svůj $prefix nebo číslo databáze (sessionHandler: redis: prefix/database).
 */
final class RedisSessionHandler extends DatabaseSessionHandler
{
	private const string UnlockScript = "if redis.call('get', KEYS[1]) == ARGV[1] then return redis.call('del', KEYS[1]) else return 0 end";

	/** Token zámku, který teď držíme (ke klíči $lockedKey předka) */
	private ?string $lockToken = null;


	public function __construct(
		private readonly RedisConnection $redis,
		private readonly string $prefix = 'firecms:session:',
		private readonly int $lockTtl = 60,
		int $lockTimeout = 30,
	) {
		parent::__construct($lockTimeout);
	}


	protected function fetch(string $key): ?string
	{
		$data = $this->redis->command('GET', $this->prefix . $key);

		return is_string($data) ? $data : null;
	}


	protected function store(string $key, string $data, int $lifetime): void
	{
		$this->redis->command('SET', $this->prefix . $key, $data, 'EX', (string) $lifetime);
	}


	protected function touch(string $key, string $data, int $lifetime): void
	{
		// klíč mezitím vypršel (EXPIRE vrátí 0) - uložit znovu, jinak by se nezměněná data ztratila
		if ($this->redis->command('EXPIRE', $this->prefix . $key, (string) $lifetime) === 0) {
			$this->store($key, $data, $lifetime);
		}
	}


	protected function exists(string $key): bool
	{
		return $this->redis->command('EXISTS', $this->prefix . $key) === 1;
	}


	protected function remove(string $key): void
	{
		$this->redis->command('DEL', $this->prefix . $key);
	}


	public function deleteExpired(): int
	{
		return 0;
	}


	protected function acquireLock(string $key, int $timeout): bool
	{
		$token = bin2hex(random_bytes(16));
		$lockKey = $this->lockKey($key);
		$acquired = $this->retryUntil(
			fn(): bool => $this->redis->command('SET', $lockKey, $token, 'NX', 'PX', (string) ($this->lockTtl * 1000)) === 'OK',
			$timeout,
		);

		if ($acquired) {
			$this->lockToken = $token;
		}

		return $acquired;
	}


	protected function releaseLock(string $key): void
	{
		if ($this->lockToken === null) {
			return;
		}

		$this->redis->command('EVAL', self::UnlockScript, '1', $this->lockKey($key), $this->lockToken);
		$this->lockToken = null;
	}


	private function lockKey(string $key): string
	{
		return $this->prefix . 'lock:' . $key;
	}

}
