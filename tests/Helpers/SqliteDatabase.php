<?php

declare(strict_types=1);

namespace Tests\Helpers;

use Nette\Caching\Storages\MemoryStorage;
use Nette\Database\Connection;
use Nette\Database\Explorer;
use Nette\Database\Structure;

/**
 * Pomocník pro integrační testy nad Nette Database: vytvoří izolovaný
 * Explorer proti in-memory SQLite databázi, nikdy ne proti reálné/vývojářské DB.
 *
 * Pozor: `App\Model\BaseModel::insert()` používá MySQL-specifické
 * `SELECT LAST_INSERT_ID()`, které SQLite nezná — přes tento Explorer proto
 * nelze testovat kód volající `BaseModel::insert()`. Pro přípravu testovacích
 * dat používejte přímé INSERTy přes `Explorer::query()`.
 */
final class SqliteDatabase
{
	/**
	 * @param list<literal-string> $ddl CREATE TABLE příkazy (syntaxe SQLite)
	 */
	public static function create(array $ddl): Explorer
	{
		$connection = new Connection('sqlite::memory:');
		$structure = new Structure($connection, new MemoryStorage());
		$explorer = new Explorer($connection, $structure);

		foreach ($ddl as $statement) {
			$explorer->query($statement);
		}

		return $explorer;
	}
}
