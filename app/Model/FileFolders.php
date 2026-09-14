<?php
declare(strict_types=1);

namespace App\Model;

use Nette\Database\Explorer;
use Nette\Database\SqlLiteral;
use Nette\Utils\ArrayHash;

/**
 * FileFolders Model
 */
class FileFolders extends BaseModel
{


	public function __construct(Explorer $database)
	{
		parent::__construct($database);

		$this->setTableName('file_folders');
		$this->setColumnId('file_folder_id');
	}


	/**
	 * Inserts new
	 */
	public function insert(ArrayHash $data): int
	{
		$data->position = $this->getNextPosition();
		return parent::insert($data);
	}


	/**
	 * Delete
	 */
	public function delete(int $id): void{
		//update all childs
		$info = $this->findById($id)->fetch();
		$this->getAll()
			->where("parent_id", $id)
			->update(array(
			"parent_id" => $info->parent_id
		));

		//finally delete
		parent::delete($id);
	}


	/**
	 * Get next position
	 * @return int
	 */
	protected function getNextPosition(): int
	{
		return $this->getAll()->select("IFNULL(MAX(position),0)+1 AS position")->fetchField();
	}


	/**
	 * Update tree positions
	 */
	public function updateTreePositions(array $treePositions): void
	{
		// search for columns to update
		$keys = array_keys(array_values($treePositions)[0]);

		// join keys for update statement
		$updateStatement = array();
		foreach ($keys as $key){
			$updateStatement[$key] = new SqlLiteral("VALUES($key)");
		}

		foreach ($treePositions as &$treePosition){
			$treePosition["name"] = "";//must be, should not be updated
		}

		$this->database->query("INSERT INTO `" . $this->getTableName() . "` ? ON DUPLICATE KEY UPDATE ? ", $treePositions, $updateStatement);
	}

}
