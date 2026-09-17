<?php
declare(strict_types=1);

namespace App\FrontModule\Presenters;

use App\Components\ViewCounter;
use App\Model;
use Nette;
use Nette\Application\Attributes\Persistent;

class FilesPresenter extends BasePresenter
{

	/**
	 * Hash
	 */
	#[Persistent]
	public string $hash;

	/** @inject */
	public Model\Files $filesModel;

	/** @inject */
	public ViewCounter $viewCounter;

	/** @inject */
	public \App\Components\FileManager\FileManager $fileManager;


	/** Get files, increment if viewed, download */
	public function actionDefault($hash): void
	{
		$fileInfo = $this->filesModel->findByHash($this->hash)->fetch();
		if (!$fileInfo) {
			throw new Nette\Application\BadRequestException("File with hash '$this->hash' doesn't exist.");
		}

		//viewCounter
		$this->viewCounter->itemViewed(ViewCounter::TYPE_FILE, $this->hash, $this->language);

		$fileEntity = $this->filesModel->toFileEntity($fileInfo);
		$fileRequest = \App\Components\FileManager\Requests\FileRequest::fromFile($fileEntity);
		$response = $this->fileManager->download($fileRequest);
		$this->sendResponse($response);
	}

	/**
	 * Generate images thumbnail
	 */
	public function actionGenerateImageThumbnail($imagePath): void
	{
		$imageHash = substr($imagePath,strrpos($imagePath, '/')+1, 40);
		$imageDimensionsToExtension = substr($imagePath,strpos($imagePath, '.')+1);
		$imageDimensions = substr($imageDimensionsToExtension,0, strpos($imageDimensionsToExtension, '.'));
		$fileInfo = $this->filesModel->findByHash($imageHash)->fetch();
		if (!$fileInfo) {
			throw new Nette\Application\BadRequestException("File with hash '$this->hash' doesn't exist.");
		}

		$fileEntity = $this->filesModel->toFileEntity($fileInfo);
		$fileRequest = new \App\Components\FileManager\Macro\ImageRequest($fileEntity, $imageDimensions);

		//this create thumbnail
		$response = $this->fileManager->fetch($fileRequest);
		$this->redirect("this");
	}

}
