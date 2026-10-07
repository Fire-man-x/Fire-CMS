<?php
declare(strict_types=1);

namespace App\FrontModule\Presenters;

use App\Components\ViewCounter;
use App\FileStorage\Exceptions\InvalidThumbnailException;
use App\FileStorage\Files\HashImageEntity;
use App\FileStorage\Request\FileRequest;
use App\FileStorage\Storages\FlysystemStorage;
use App\Model;
use League\Flysystem\FilesystemException;
use Nette\Application\Attributes\Persistent;
use Nette\Application\BadRequestException;

class FilesPresenter extends BasePresenter
{

	/**
	 * Hash
	 */
	#[Persistent]
	public string $hash;

	/** @inject */
	public Model\Database\Files $filesModel;

	/** @inject */
	public ViewCounter $viewCounter;

	/** @inject */
	public \App\FileStorage\FileManager $fileManager;

	/** @inject */
	public FlysystemStorage $storage;


	/** Get files, increment if viewed, download */
	public function actionDefault($hash): void
	{
		$fileInfo = $this->filesModel->findByHash($this->hash)->fetch();
		if (!$fileInfo) {
			throw new BadRequestException("File with hash '$this->hash' doesn't exist.");
		}

		//viewCounter
		$this->viewCounter->itemViewed(ViewCounter::TYPE_FILE, $this->hash, $this->language);

		$fileEntity = $this->filesModel->toFileEntity($fileInfo);
		$fileRequest = FileRequest::fromFile($fileEntity);
		try {
			$response = $this->fileManager->download($fileRequest);
		} catch (FilesystemException $e) {
			throw new BadRequestException("File with hash '$this->hash' is missing in the storage.", 404, $e);
		}
		$this->sendResponse($response);
	}


	/**
	 * Generátor náhledů: vytvoří povolený náhled obrázku při prvním zobrazení, uloží ho do úložiště
	 * a pošle. Další vykreslení šablony už odkazuje přímo na uložený náhled (viz FlysystemStorage::link()).
	 */
	public function actionThumbnail(string $hash, string $thumbnail): void
	{
		$fileInfo = $this->filesModel->findByHash($hash)->fetch();
		$fileEntity = $fileInfo ? $this->filesModel->toFileEntity($fileInfo) : null;
		if (!$fileEntity instanceof HashImageEntity) {
			throw new BadRequestException("Image with hash '$hash' doesn't exist.");
		}

		try {
			$response = $this->storage->thumbnail($fileEntity, $thumbnail);
		} catch (InvalidThumbnailException $e) {
			throw new BadRequestException($e->getMessage(), 404, $e);
		} catch (FilesystemException $e) {
			throw new BadRequestException("Image with hash '$hash' is missing in the storage.", 404, $e);
		}
		$this->sendResponse($response);
	}

}
