<?php
declare(strict_types=1);

namespace App\Model;

use Nette\Database\Explorer;
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
	 * Primary key column_id
	 */
	private string $columnId;

	protected Explorer $database;


	public function __construct(Explorer $database)
	{
		$this->database = $database;
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
		if(isset($this->columnId)){
			return $this->columnId;
		} else {
			throw new \InvalidArgumentException("Column ID is not defined.");
		}
	}

	/**
	 * Set primary key column name
	 */
	protected function setColumnId(string $columnId): void
	{
		$this->columnId = $columnId;
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
		$this->getTable()->insert($data);
		return $this->database->query("SELECT LAST_INSERT_ID()")->fetchField();
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
