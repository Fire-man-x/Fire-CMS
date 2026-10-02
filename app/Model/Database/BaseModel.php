<?php
declare(strict_types=1);

namespace App\Model\Database;

use Nette\Database\Explorer;
use Nette\Database\SqlLiteral;
use Nette\Database\Table\ActiveRow;
use Nette\Database\Table\Selection;
use Nette\SmartObject;
use Nette\Utils\ArrayHash;

/**
 * Base Model
 */
abstract class BaseModel
{
	use SmartObject;

	/**
	 * Table name
	 */
	private string $tableName;

	/**
	 * Primary key column (AUTO_INCREMENT sloupce se v DB jmenují `id`)
	 */
	private string $columnId = 'id';

	/**
	 * Název sloupce, pod kterým na primární klíč této tabulky odkazují ostatní tabulky
	 * (překladové/vazební tabulky), např. `articleId` pro `firecms_articles.id`
	 */
	private string $foreignKeyColumn;

	protected Explorer $database;


	public function __construct(Explorer $database)
	{
		$this->database = $database;
	}


	/**
	 * Identifikátor pro ručně psané SQL obalený podle databáze připojení (MariaDB `x`, PostgreSQL "x"),
	 * `tabulka.sloupec` po částech. Nette Explorer obalený identifikátor nepřepisuje ani nevykládá jako odkaz
	 * na navázanou tabulku (`tabulka.sloupec` v select()/where() by jinak zkusil spojit jako JOIN).
	 */
	public function delimite(string $identifier): string
	{
		$driver = $this->database->getConnection()->getDriver();

		return implode('.', array_map($driver->delimite(...), explode('.', $identifier)));
	}


	/**
	 * Vloží řádek do $table, jen pokud tam ještě není řádek se stejnými hodnotami $keyColumns (náhrada MySQL
	 * INSERT IGNORE, který PostgreSQL nezná). Zachytit chybu duplicity nejde - v PostgreSQL by nechala
	 * probíhající transakci v chybovém stavu (vkládání komentáře/štítku běží v transakci).
	 * @param array<string, mixed>|ArrayHash $data
	 * @param list<string> $keyColumns sloupce unikátního/primárního klíče vazby, např. ['articleId', 'tagId']
	 */
	protected function insertIfNotExists(string $table, array|ArrayHash $data, array $keyColumns): void
	{
		$data = (array) $data;
		$existing = $this->database->table($table);
		foreach ($keyColumns as $column) {
			$existing->where($column, $data[$column] ?? null);
		}
		if ($existing->count('*') === 0) {
			$this->database->table($table)->insert($data);
		}
	}


	/**
	 * Změní řádky této tabulky podle primárního klíče - každý řádek obsahuje klíč a měněné sloupce (náhrada
	 * MySQL INSERT ... ON DUPLICATE KEY UPDATE, který PostgreSQL nezná; řádky musí existovat)
	 * @param iterable<array<string, mixed>> $rows např. [['id' => 3, 'parentId' => 1, 'position' => 2], ...]
	 */
	protected function updateRowsById(iterable $rows): void
	{
		$column = $this->getColumnId();
		foreach ($rows as $row) {
			$id = $row[$column];
			unset($row[$column]);
			if ($row !== []) {
				$this->getTable()->where($column, $id)->update($row);
			}
		}
	}


	/**
	 * Get table name
	 */
	public function getTableName(): string
	{
		if(isset($this->tableName)){
			return $this->tableName;
		} else {
			throw new \InvalidArgumentException("Table name is not defined.");
		}
	}

	/**
	 * Set table name
	 */
	protected function setTableName(string $tableName): void
	{
		$this->tableName = $tableName;
	}


	/**
	 * Get primary key column name
	 */
	public function getColumnId(): string
	{
		return $this->columnId;
	}

	/**
	 * Set primary key column name
	 */
	protected function setColumnId(string $columnId): void
	{
		$this->columnId = $columnId;
	}


	/**
	 * Get foreign key column name - sloupec, kterým na tuto tabulku odkazují ostatní tabulky
	 */
	public function getForeignKeyColumn(): string
	{
		if(isset($this->foreignKeyColumn)){
			return $this->foreignKeyColumn;
		} else {
			throw new \InvalidArgumentException("Foreign key column is not defined.");
		}
	}

	/**
	 * Set foreign key column name
	 */
	protected function setForeignKeyColumn(string $foreignKeyColumn): void
	{
		$this->foreignKeyColumn = $foreignKeyColumn;
	}



	/**
	 * Get table
	 */
	protected function getTable(): Selection
	{
		return $this->database->table($this->getTableName());
	}


	/**
	 * Get all rows
	 */
	public function findAll(): Selection
	{
		return $this->getTable();
	}


	/**
	 * Find row by id
	 */
	public function findById(int $id): Selection
	{
		return $this->getTable()->where($this->getColumnId(), $id);
	}


	/**
	 * Find all rows by ids
	 * @param array<int,int|string> $ids
	 */
	public function findByIds(array $ids): Selection
	{
		return $this->getTable()->where($this->getColumnId(), $ids);
	}


	/**
	 * Inserts new
	 */
	public function insert(ArrayHash $data): int
	{
		// prázdný řádek (např. štítek, vše ostatní je v překladech): MariaDB bere "() VALUES ()", PostgreSQL
		// ne - "(id) VALUES (DEFAULT)" projde na obou a skutečné ID Nette níže stejně zjistí z databáze
		// (SQLite v testech DEFAULT ve VALUES nezná - fixture data tam vkládejte přes Explorer::query())
		if (count($data) === 0) {
			$data = ArrayHash::from([$this->getColumnId() => new SqlLiteral('DEFAULT')]);
		}

		// Selection::insert() vrátí vložený řádek a ID si zjistí podle databáze (MariaDB LAST_INSERT_ID(),
		// PostgreSQL currval() sekvence identity sloupce) - SELECT LAST_INSERT_ID() PostgreSQL nezná
		// (array): Nette podle typu argumentu rozlišuje návratovou hodnotu (pole = vložený řádek), ArrayHash
		// se za běhu chová stejně, jen to PHPStan neví
		$row = $this->getTable()->insert((array) $data);
		$primary = $row instanceof ActiveRow ? $row->getPrimary(false) : null;

		// tabulka bez automaticky číslovaného klíče (vazební/překladová, klíč např. languageId) ID nemá
		return is_numeric($primary) ? (int) $primary : 0;
	}


	/**
	 * Get by id
	 */
	public function getById(int $id): ?ActiveRow
	{
		return $this->findById($id)->fetch();
	}


	/**
	 * Update
	 */
	public function update(int $id, array $data): ?bool
	{
		return $this->getById($id)?->update($data);
	}


	/**
	 * Delete
	 */
	public function delete(int $id): ?int
	{
		return $this->getById($id)?->delete();
	}

}
