<?php
declare(strict_types=1);

namespace App\Session\DI;

use App\Session\DatabaseSessionHandler\MysqlSessionHandler;
use App\Session\DatabaseSessionHandler\PostgreSqlSessionHandler;
use App\Session\DatabaseSessionHandler\RedisConnection;
use App\Session\DatabaseSessionHandler\RedisSessionHandler;
use App\Session\GarbageCollectorTask;
use Nette\Database\Connection;
use Nette\DI\CompilerExtension;
use Nette\DI\Definitions\Reference;
use Nette\DI\Definitions\ServiceDefinition;
use Nette\DI\Definitions\Statement;
use Nette\DI\InvalidConfigurationException;
use Nette\Http\Session;
use Nette\Schema\Expect;
use Nette\Schema\Schema;

/**
 * Volba úložiště sessions (App\Session\DatabaseSessionHandler\*). Registrované v config.neon jádra, nastavuje se
 * v theme.neon projektu. Bez `driver` se nic nemění - výchozí PHP handler (soubory v `session: savePath`).
 *
 * ```neon
 * sessionHandler:
 *     driver: mysql                 # mysql | postgresql | redis
 *     connection: @database.default # připojení ze sekce database: (app/config), bez vyplnění hlavní připojení
 * ```
 *
 * Další volby a Redis viz getConfigSchema() a docs/Architecture/sessions.md.
 */
final class SessionHandlerExtension extends CompilerExtension
{

	public function getConfigSchema(): Schema
	{
		return Expect::structure([
			// mysql | postgresql | redis, NULL = výchozí PHP handler
			'driver' => Expect::anyOf(null, 'mysql', 'postgresql', 'redis'),
			// MySQL/PostgreSQL: připojení ze sekce database: - @database.<název> (nebo služba @database.<název>.connection),
			// NULL = autowired Nette\Database\Connection (hlavní připojení)
			'connection' => Expect::string()->nullable(),
			'table' => Expect::string('system_sessions'),
			// kolik sekund čekat na zámek session, kterou drží jiný požadavek
			'lockTimeout' => Expect::int(30)->min(1),
			'redis' => Expect::structure([
				// host, tls://host nebo cesta k unix socketu
				'host' => Expect::string('127.0.0.1'),
				'port' => Expect::int(6379),
				'username' => Expect::string()->nullable(),
				'password' => Expect::string()->nullable(),
				'database' => Expect::int(0)->min(0),
				// každý projekt na sdíleném Redisu svůj prefix (nebo database)
				'prefix' => Expect::string('firecms:session:'),
				'timeout' => Expect::anyOf(Expect::float(), Expect::int())->default(2.0),
				// po kolika sekundách zámek sám vyprší (pád požadavku)
				'lockTtl' => Expect::int(60)->min(1),
			]),
		]);
	}


	public function loadConfiguration(): void
	{
		$config = $this->getSettings();
		if ($config->driver === null) {
			return;
		}

		$builder = $this->getContainerBuilder();
		$handler = $builder->addDefinition($this->prefix('handler'))
			->setAutowired(false);

		if ($config->driver === 'redis') {
			$handler->setFactory(RedisSessionHandler::class, [
				'redis' => new Statement(RedisConnection::class, [
					'host' => $config->redis->host,
					'port' => $config->redis->port,
					'username' => $config->redis->username,
					'password' => $config->redis->password,
					'database' => $config->redis->database,
					'timeout' => (float) $config->redis->timeout,
				]),
				'prefix' => $config->redis->prefix,
				'lockTtl' => $config->redis->lockTtl,
				'lockTimeout' => $config->lockTimeout,
			]);

			return;
		}

		// připojení (`connection`) se doplní v beforeCompile(), až budou zaregistrované služby databáze
		$handler->setFactory($config->driver === 'mysql' ? MysqlSessionHandler::class : PostgreSqlSessionHandler::class, [
			'table' => $config->table,
			'lockTimeout' => $config->lockTimeout,
		]);

		// prošlé sessions v DB maže cron (App\Cron\CronRunner najde všechny App\Cron\CronTask sám)
		$builder->addDefinition($this->prefix('garbageCollector'))
			->setFactory(GarbageCollectorTask::class, [$this->prefix('@handler')]);
	}


	public function beforeCompile(): void
	{
		$config = $this->getSettings();
		if ($config->driver === null) {
			return;
		}

		$builder = $this->getContainerBuilder();

		if ($config->driver !== 'redis' && $config->connection !== null) {
			$handler = $builder->getDefinition($this->prefix('handler'));
			if ($handler instanceof ServiceDefinition) {
				$handler->setArgument('connection', new Reference($this->resolveConnection($config->connection)));
			}
		}

		$session = $builder->getDefinitionByType(Session::class);
		if (!$session instanceof ServiceDefinition) {
			throw new InvalidConfigurationException('Session service (Nette\Http\Session) is not a service definition, cannot set its handler.');
		}
		$session->addSetup('setHandler', [$this->prefix('@handler')]);
	}


	/**
	 * `@database.default` (připojení ze sekce database:) -> služba `database.default.connection`. Přijme i přímo název
	 * služby typu Nette\Database\Connection (`@database.default.connection`).
	 */
	private function resolveConnection(string $connection): string
	{
		$builder = $this->getContainerBuilder();
		$name = ltrim($connection, '@');

		foreach ([$name . '.connection', $name] as $candidate) {
			if ($builder->hasDefinition($candidate)
				&& is_a((string) $builder->getDefinition($candidate)->getType(), Connection::class, true)
			) {
				return $candidate;
			}
		}

		throw new InvalidConfigurationException("Option '{$this->name} › connection' must reference a connection from the database: section (e.g. @database.default), '$connection' given.");
	}


	/**
	 * Konfigurace s typy pro PHPStan (hodnoty už ověřilo getConfigSchema())
	 *
	 * @return object{driver: 'mysql'|'postgresql'|'redis'|null, connection: ?string, table: string, lockTimeout: int, redis: object{host: string, port: int, username: ?string, password: ?string, database: int, prefix: string, timeout: float|int, lockTtl: int}}
	 */
	private function getSettings(): object
	{
		/** @var object{driver: 'mysql'|'postgresql'|'redis'|null, connection: ?string, table: string, lockTimeout: int, redis: object{host: string, port: int, username: ?string, password: ?string, database: int, prefix: string, timeout: float|int, lockTtl: int}} $config */
		$config = $this->config;

		return $config;
	}

}
