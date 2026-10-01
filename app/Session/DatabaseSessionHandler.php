<?php
declare(strict_types=1);

namespace App\Session;

/**
 * Společný základ handlerů, které ukládají sessions mimo soubory (MySQL, PostgreSQL, Redis) - viz
 * App\Session\DatabaseSessionHandler\*. Který se použije, se volí v theme.neon projektu (`sessionHandler: driver:`,
 * App\Session\DI\SessionHandlerExtension), bez volby zůstává výchozí PHP handler (soubory).
 *
 * - ID session se ukládá jen jako SHA-256 hash - z výpisu DB/Redisu nebo zálohy nejde převzít cizí přihlášení.
 * - Zámek na session po dobu požadavku (od read() do close()) jako u souborů, jinak by si souběžné požadavky
 *   (AJAX) navzájem přepisovaly data. Nepodaří-li se zámek získat do $lockTimeout sekund, session_start() skončí
 *   výjimkou.
 * - Životnost bere z `session.gc_maxlifetime`, které Nette nastavuje podle `session: expiration` při startu session.
 * - Prošlé sessions maže deleteExpired() (volá ho gc() i cron úloha App\Session\GarbageCollectorTask - na serverech
 *   s `session.gc_probability = 0`, např. Debian, PHP gc() sám nikdy nezavolá).
 */
abstract class DatabaseSessionHandler implements \SessionHandlerInterface, \SessionUpdateTimestampHandlerInterface
{
	/** Životnost, pokud `session.gc_maxlifetime` není nastavené (výchozí hodnota PHP) */
	private const int DefaultLifetime = 1440;

	/** Hash ID session, na které teď držíme zámek */
	private ?string $lockedKey = null;


	public function __construct(
		protected readonly int $lockTimeout = 30,
	) {
	}


	/**
	 * Uložená data session, NULL = neexistuje nebo prošla
	 */
	abstract protected function fetch(string $key): ?string;


	abstract protected function store(string $key, string $data, int $lifetime): void;


	/**
	 * Prodlouží platnost beze změny dat (PHP volá místo write(), když se data nezměnila - session.lazy_write)
	 */
	abstract protected function touch(string $key, string $data, int $lifetime): void;


	abstract protected function exists(string $key): bool;


	abstract protected function remove(string $key): void;


	/**
	 * Zamkne session, čeká nejvýš $timeout sekund
	 */
	abstract protected function acquireLock(string $key, int $timeout): bool;


	abstract protected function releaseLock(string $key): void;


	/**
	 * Smaže prošlé sessions, vrací jejich počet (úložiště s vlastní expirací - Redis - vrací 0)
	 */
	abstract public function deleteExpired(): int;


	public function open(string $path, string $name): bool
	{
		return true;
	}


	public function read(string $id): string|false
	{
		$key = $this->hashId($id);
		$this->lock($key);

		return $this->fetch($key) ?? '';
	}


	public function write(string $id, string $data): bool
	{
		$this->store($this->hashId($id), $data, $this->getLifetime());

		return true;
	}


	public function updateTimestamp(string $id, string $data): bool
	{
		$this->touch($this->hashId($id), $data, $this->getLifetime());

		return true;
	}


	/**
	 * Strict mode (Nette zapíná `session.use_strict_mode`): neznámé ID PHP nepřevezme a vygeneruje nové
	 */
	public function validateId(string $id): bool
	{
		return $this->exists($this->hashId($id));
	}


	public function destroy(string $id): bool
	{
		$key = $this->hashId($id);
		$this->remove($key);
		if ($this->lockedKey === $key) {
			$this->unlock();
		}

		return true;
	}


	public function close(): bool
	{
		$this->unlock();

		return true;
	}


	public function gc(int $max_lifetime): int|false
	{
		// životnost je uložená u každé session (podle session.gc_maxlifetime při zápisu), $max_lifetime se nepoužije
		return $this->deleteExpired();
	}


	protected function getLifetime(): int
	{
		$lifetime = (int) ini_get('session.gc_maxlifetime');

		return $lifetime > 0 ? $lifetime : self::DefaultLifetime;
	}


	/**
	 * Opakuje $attempt, dokud neuspěje nebo nevyprší $timeout sekund - pro úložiště bez blokujícího zámku
	 *
	 * @param \Closure(): bool $attempt
	 */
	protected function retryUntil(\Closure $attempt, int $timeout): bool
	{
		$deadline = microtime(true) + $timeout;
		do {
			if ($attempt()) {
				return true;
			}
			usleep(50_000);
		} while (microtime(true) < $deadline);

		return false;
	}


	private function hashId(string $id): string
	{
		return hash('sha256', $id);
	}


	private function lock(string $key): void
	{
		if ($this->lockedKey === $key) {
			return;
		}

		// session_regenerate_id() čte nové ID, zatímco starou session ještě držíme
		$this->unlock();

		if (!$this->acquireLock($key, $this->lockTimeout)) {
			throw new \RuntimeException("Session lock was not acquired within {$this->lockTimeout} s (another request holds the session).");
		}

		$this->lockedKey = $key;
	}


	private function unlock(): void
	{
		if ($this->lockedKey !== null) {
			$this->releaseLock($this->lockedKey);
			$this->lockedKey = null;
		}
	}

}
