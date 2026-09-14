<?php
declare(strict_types=1);

namespace App\Model;

use Nette\Database\Explorer;
use Nette\Utils\ArrayHash;

/**
 * Languages Model
 */
class Languages extends BaseModel
{

	public function __construct(Explorer $database)
	{
		parent::__construct($database);

		$this->setTableName('languages');
		$this->setColumnId('language_id');
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
	 * Delete
	 */
	public function delete(int $id): void
	{
		$this->findById($id)
			->where("default", 0)
			->delete();
	}


	/**
	 * Get next position
	 */
	protected function getNextPosition(): int
	{
		return $this->getAll()->select("IFNULL(MAX(position),0)+1 AS position")->fetchField();
	}

}
