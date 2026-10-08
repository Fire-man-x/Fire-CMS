<?php
declare(strict_types=1);

namespace App\FrontModule\Presenters;

use App\Components\ViewCounter;
use App\FileStorage\Exceptions\InvalidThumbnailException;
use App\FileStorage\Request\FileRequest;
use App\FileStorage\Storages\StorageRegistry;
use App\Model;
use League\Flysystem\FilesystemException;
use Nette\Application\Attributes\Persistent;
use Nette\Application\BadRequestException;
use Nette\Utils\UnknownImageFileException;

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
	public StorageRegistry $storages;


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
	 * Funguje pro každé úložiště z `fileStorage: <název>:` - originál určuje jeho klíč, ne záznam v DB.
	 *
	 * @param string $path klíč originálu v úložišti
	 */
	public function actionThumbnail(string $storage, string $path, string $thumbnail): void
	{
		$fileStorage = $this->storages->find($storage)
			?? throw new BadRequestException("Storage '$storage' doesn't exist.");

		try {
			$response = $fileStorage->thumbnail($path, $thumbnail);
		} catch (InvalidThumbnailException $e) {
			throw new BadRequestException($e->getMessage(), 404, $e);
		} catch (FilesystemException $e) {
			throw new BadRequestException("Image '$path' is missing in the storage '$storage'.", 404, $e);
		} catch (UnknownImageFileException $e) {
			throw new BadRequestException("File '$path' in the storage '$storage' is not an image.", 404, $e);
		}
		$this->sendResponse($response);
	}


	/**
	 * Náhled vyžádaný přímo na jeho adrese v úložišti s directThumbnails - web server sem pošle jen požadavek
	 * na soubor, který na disku není. Náhled vytvoří a uloží, další požadavky obslouží web server.
	 *
	 * @param string $path klíč náhledu v úložišti (cesta z URL za publicUrl úložiště)
	 */
	public function actionMissingThumbnail(string $storage, string $path): void
	{
		$fileStorage = $this->storages->find($storage)
			?? throw new BadRequestException("Storage '$storage' doesn't exist.");

		try {
			$response = $fileStorage->thumbnailFromPath($path);
		} catch (InvalidThumbnailException $e) {
			throw new BadRequestException($e->getMessage(), 404, $e);
		} catch (FilesystemException $e) {
			throw new BadRequestException("Thumbnail '$path' in the storage '$storage' can't be created.", 404, $e);
		} catch (UnknownImageFileException $e) {
			throw new BadRequestException("Original of '$path' in the storage '$storage' is not an image.", 404, $e);
		}
		$this->sendResponse($response);
	}

}
