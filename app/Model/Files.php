<?php
declare(strict_types=1);

namespace App\Model;

use App\Components\FileManager\Files\HashFileEntity;
use App\Components\FileManager\Files\HashImageEntity;
use App\Components\IViewCounter;
use Nette\Database\Explorer;
use Nette\Database\SqlLiteral;
use Nette\Database\Table\ActiveRow;
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
		$this->setForeignKeyColumn('fileId');
	}


	/**
	 * Inserts new
	 */
	public function insert(ArrayHash $data): int
	{
		$data->createDate = new SqlLiteral("NOW()");
		return parent::insert($data);
	}


	/**
	 * Find by string
	 */
	public function findByHash(string $hash): \Nette\Database\Table\Selection
	{
		return $this->getTable()->where("diskName", $hash);
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
		$this->findByIds($files)->update(array("fileFolderId"=>$toFolder));
	}


	/**
	 * Convert to FileEntity
	 */
	public function toFileEntity(ActiveRow $file): HashFileEntity|HashImageEntity
	{
		if($file["isImage"]){
			$fileEntity = new HashImageEntity();
		} else {
			$fileEntity = new HashFileEntity();
		}
		$fileEntity->setId($file["id"]);
		$fileEntity->setName($file["newName"] ? $file["newName"] : $file["originalName"]);
		$fileEntity->setHash($file["diskName"]);
		$fileEntity->setExtension($file["extension"]);
		$fileEntity->setMimeType($file["mimeType"]);
		$fileEntity->setSize($file["size"]);

		return $fileEntity;
	}


	/**
	 * Add view count to counter
	 */
	public function addViewCount(int|string $fileHash, string $language): void
	{
		$data = array(
			"viewCount" => new SqlLiteral("viewCount+1")
		);
		$this->findByHash($fileHash)
			->update($data);
	}

}
