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

		$this->setTableName('firecms_fileFolders');
		$this->setForeignKeyColumn('fileFolderId');
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
	public function delete(int $id): ?int
	{
		//update all childs
		$info = $this->getById($id);
		if($info) {
			$this->findAll()
				->where("parentId", $id)
				->update(["parentId" => $info->parentId]);
		}

		//finally delete
		return parent::delete($id);
	}


	/**
	 * Get next position
	 * @return int
	 */
	protected function getNextPosition(): int
	{
		return $this->findAll()->select("IFNULL(MAX(position),0)+1 AS position")->fetchField();
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
