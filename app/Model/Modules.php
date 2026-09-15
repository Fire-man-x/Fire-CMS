<?php
declare(strict_types=1);

namespace App\Model;

use Nette\Database\Explorer;
use Nette\Utils\ArrayHash;

/**
 * Modules Model
 */
class Modules extends BaseModel implements IList
{


	public function __construct(Explorer $database)
	{
		parent::__construct($database);

		$this->setTableName('modules');
		$this->setColumnId('module_id');
	}


	/**
	 * Inserts new
	 */
	public function insert(ArrayHash $data): int
	{
		throw new \Nette\InvalidStateException("Not implemented yet.");
	}


	/**
	 * Update
	 */
	public function update(int $id, array $data): ?bool
	{
		throw new \Nette\InvalidStateException("Not implemented yet.");
	}


	/**
	 * Delete
	 */
	public function delete(int $id): ?int
	{
		throw new \Nette\InvalidStateException("Not implemented yet.");
	}


	/**
	 * Get all modules
	 */
	public function getAllModules(): \Nette\Database\Table\Selection
	{
		return $this->findAll()
				->where("name IS NOT NULL")
				->where("privilege IS NULL")
				->order("title");
	}


	/**
	 * @return array|\Nette\Database\Table\Selection
	 */
	public function getList()
	{
		return $this->getAllModules()->fetchPairs("module_id", "title");
	}


	public function getListWithName(): array|\Nette\Database\Table\Selection
	{
		return $this->getAllModules()->fetchPairs("module_id", "name");
	}


	/**
	 * Get all other privileges
	 */
	public function getAllOtherPrivileges(): \Nette\Database\Table\Selection
	{
		return $this->findAll()
				->where("privilege IS NOT NULL")
				->where("name IS NULL")
				->order("title");
	}

}
