<?php
declare(strict_types=1);

namespace App\Model;

use Nette\Database\Explorer;
use Nette\Database\Table\ActiveRow;
use Nette\Database\Table\Selection;
use Nette\Utils\ArrayHash;

/**
 * Languages Model
 */
class Languages extends BaseModel
{

	public function __construct(Explorer $database)
	{
		parent::__construct($database);

		$this->setTableName('firecms_languages');
		$this->setColumnId('languageId');
		$this->setForeignKeyColumn('languageId');
	}


	/**
	 * Inserts new
	 */
	public function insert(ArrayHash $data): int
	{
		$data->position = $this->getNextPosition();
		$this->getTable()->insert($data);
		return (int) $this->database->getInsertId();
	}

	/**
	 * Find row by id
	 */
	public function findById(int|string  $id): Selection
	{
		return $this->getTable()->where($this->getColumnId(), $id);
	}


	/**
	 * Find by id
	 */
	public function getById(int|string $id): ?ActiveRow
	{
		return $this->findById($id)->fetch();
	}


	/**
	 * Update
	 */
	public function update(int|string $id, array $data): ?bool
	{
		return $this->getById($id)?->update($data);
	}


	/**
	 * Delete
	 */
	public function delete(int|string $id): ?int
	{
		return $this->findById($id)
			->where("default", 0)
			->delete();
	}


	/**
	 * Get next position
	 */
	protected function getNextPosition(): int
	{
		return $this->findAll()->select("IFNULL(MAX(position),0)+1 AS position")->fetchField();
	}

}
