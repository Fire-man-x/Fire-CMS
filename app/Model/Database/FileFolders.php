<?php
declare(strict_types=1);

namespace App\Model\Database;

use Nette\Database\Explorer;
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
		return (int) $this->findAll()->max("position") + 1;
	}


	/**
	 * Update tree positions
	 */
	public function updateTreePositions(array $treePositions): void
	{
		// řádky [id, parentId, position, level] existují, mění se jen pozice (dřív INSERT ... ON DUPLICATE KEY UPDATE)
		$this->updateRowsById($treePositions);
	}

}
