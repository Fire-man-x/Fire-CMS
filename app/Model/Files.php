<?php
declare(strict_types=1);

namespace App\Model;

use App\Components\Filemanager\Files\HashFileEntity;
use App\Components\Filemanager\Files\HashImageEntity;
use App\Components\Filemanager\Files\IFile;
use App\Components\IViewCounter;
use Nette\Database\Explorer;
use Nette\Database\SqlLiteral;
use Nette\Utils\ArrayHash;

/**
 * Files Model
 */
class Files extends BaseModel implements IViewCounter
{

	public function __construct(Explorer $database)
	{
		parent::__construct($database);

		$this->setTableName('firecms_files');
		$this->setColumnId('file_id');
	}


	/**
	 * Inserts new
	 */
	public function insert(ArrayHash $data): int
	{
		$data->create_date = new SqlLiteral("NOW()");
		return parent::insert($data);
	}


	/**
	 * Find by string
	 */
	public function findByHash(string $hash): \Nette\Database\Table\Selection
	{
		return $this->getTable()->where("disk_name", $hash);
	}


	/**
	 * Delete
	 */
	public function deleteByHash(string $hash): void
	{
		$this->findByHash($hash)
			->delete();
	}


	/**
	 * Move files to other folder
	 * @param array<int,int> $files
	 */
	public function changeFilesFolder(int $toFolder, array $files): void
	{
		$this->findByIds($files)->update(array("file_folder_id"=>$toFolder));
	}


	/**
	 * Convert to FileEntity
	 * @param \Nette\Database\Table\ActiveRow|array $file
	 * @return IFile|HashFileEntity|HashImageEntity
	 */
	public function toFileEntity($file)
	{
		if($file["is_image"]){
			$fileEntity = new HashImageEntity();
		} else {
			$fileEntity = new HashFileEntity();
		}
		$fileEntity->setId($file["file_id"]);
		$fileEntity->setName($file["new_name"] ? $file["new_name"] : $file["original_name"]);
		$fileEntity->setHash($file["disk_name"]);
		$fileEntity->setExtension($file["extension"]);
		$fileEntity->setMimeType($file["mime_type"]);
		$fileEntity->setSize($file["size"]);

		return $fileEntity;
	}


	/**
	 * Add view count to counter
	 */
	public function addViewCount(int $fileHash, string $language): void
	{
		$data = array(
			"view_count" => new SqlLiteral("view_count+1")
		);
		$this->findByHash($fileHash)
			->update($data);
	}

}
