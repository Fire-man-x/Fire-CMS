<?php
declare(strict_types=1);

namespace App\Model;

use Nette\Utils\ArrayHash;

/**
 * Options Model
 */
class Options extends BaseModel
{


	public function __construct(\Nette\Database\Explorer $database)
	{
		parent::__construct($database);

		$this->setTableName('options');
		$this->setColumnId('key');
	}


	/**
	 * Find by id
	 */
	public function findById(int $id): \Nette\Database\Table\Selection
	{
		throw new \LogicException("Not implemented yet");
	}


	/**
	 * Get by key
	 */
	public function getByKey(string $key): string
	{
		$result = $this->getTable()->where($this->getColumnId(), $key)->fetch();
		if(!$result){
			throw new \InvalidArgumentException("Argument '$key' not found.");
		}

		return $result->value;
	}


	public function insert(ArrayHash $data): int
	{
		throw new \InvalidArgumentException("Cannot use method insert in Options model. Use 'useOption' method instead.");
	}


	public function update(int $id, array $data): void{
		throw new \InvalidArgumentException("Cannot use method update in Options model. Use 'useOption' method instead.");
	}


	/**
	 * Use option
	 * @param string $key
	 * @param string $value
	 */
	public function useOption($key, $value)
	{
		$params = array(
			"key" => $key,
			"value" => $value
		);
		$this->database->query("INSERT INTO `" . $this->getTableName() . "` ? ON DUPLICATE KEY UPDATE ? ", $params, $params);
	}

}
