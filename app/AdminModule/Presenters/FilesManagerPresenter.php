<?php
declare(strict_types=1);

namespace App\AdminModule\Presenters;

use AlesWita\Components\VisualPaginator;
use App\Components\FileManager\Files\HashFileEntity;
use App\Components\FileManager\Files\HashImageEntity;
use App\Components\FileManager\Request\ImageRequest;
use App\Components\FileManager\Storages\HashFileStorage;
use App\Forms\CategoryFormFactory;
use App\Forms\FilesManagerFileNameFormFactory;
use App\Forms\FilesManagerUploadFormFactory;
use App\Model\FileFolders;
use App\Model\Files;
use App\Service\LanguageService;
use Nette;
use Nette\Application\Attributes\Persistent;


class FilesManagerPresenter extends BasePresenter
{

	/**
	 * Id
	 */
	#[Persistent]
	public int $id = 1;

	/**
	 * Window mode
	 */
	#[Persistent]
	public bool $windowMode = false;

	/**
	 * Is from wysiwyg
	 */
	#[Persistent]
	public bool $fromWysiwyg = false;

	/**
	 * Save items to which language?
	 */
	#[Persistent]
	public ?string $toLanguage = null;

	/**
	 * How to sort files by?
	 */
	#[Persistent]
	public ?string $orderBy = null;


	/**
	 * Category parent
	 */
	private ?int $parent = null;

	/**
	 * Files model
	 * @inject
	 */
	public Files $filesModel;

	/**
	 * Files model
	 * @inject
	 */
	public HashFileStorage $filesStorage;

	/** @inject */
	public CategoryFormFactory $categoryFactory;

	/** @inject */
	public FilesManagerUploadFormFactory $filesManagerUploadFormFactory;

	/** @inject */
	public FilesManagerFileNameFormFactory $filesManagerFileNameFormFactory;

	/** @inject */
	public FileFolders $fileFoldersModel;

	/** @inject */
	public \App\Components\FilesManagerMenu\FilesManagerMenu $filesManagerMenu;


	protected function startup(): void
	{
		parent::startup();

		$this->addBreadCrumbLink("Files manager", $this->link(":Admin:FilesManager:default", array("id" => null)) );

		//set default id
		$folderInfo = $this->fileFoldersModel->getById($this->id);
		if(!$folderInfo){
			throw new \Nette\Application\BadRequestException("Item with id '$this->id' doesn't exist.");
		}
		$this->template->folderInfo = $folderInfo;
	}


	public function actionDefault(): void
	{
		//datasource for images
		$source = $this->filesModel->findAll()->where("fileFolderId", $this->id);
		switch($this->orderBy)
		{
			case 'nameAsc':
				$source->order('originalName ASC');
				break;
			case 'nameDesc':
				$source->order('originalName DESC');
				break;
			case 'sizeAsc':
				$source->order('size ASC');
				break;
			case 'sizeDesc':
				$source->order('size DESC');
				break;
			case 'dateDesc':
				$source->order('createDate DESC');
				break;
			case 'dateAsc':
			default:
				$source->order('createDate ASC');
				break;
		}
		$itemsCount = $source->count();
		$this["paginator"]->setItemCount($itemsCount);
		$source->limit($this["paginator"]->getItemsPerPage(), $this["paginator"]->getOffset());
		$this->template->showPaginator = $itemsCount > $this["paginator"]->getItemsPerPage();

		$this->template->files = array();
		foreach ($source->fetchAll() as $file){
			$this->template->files[] = $this->filesModel->toFileEntity($file);
		}
	}


	public function renderDefault(): void
	{
	}


	/**
	 * Sign-up form factory.
	 */
	protected function createComponentUploadForm(): Nette\Application\UI\Form
	{
		$form = $this->filesManagerUploadFormFactory->create();
		$form->setTranslator($this->translator);

		return $form;
	}


	/**
	 * Sign-up form factory.
	 */
	protected function createComponentFileNameForm(): Nette\Application\UI\Form
	{
		$this->filesManagerFileNameFormFactory->asModal();
		$form = $this->filesManagerFileNameFormFactory->create();
		$form->setTranslator($this->translator);
		$form->getElementPrototype()->addClass("ajax");


		$form->onSuccess[] = function($form){
			$form->getPresenter()->redrawControl("files");
		};

		return $form;
	}


	/**
	 * Files paginator factory.
	 * @return VisualPaginator
	 */
	protected function createComponentPaginator()
	{

		$vp = new VisualPaginator();
		$vp->setItemsPerPage(20);
		$vp->setTranslator($this->translator);

		return $vp;
	}


	/**
	 * FilesManager menu
	 */
	protected function createComponentFilesManagerMenu(): \Nette\Application\UI\Control
	{
		$control =  $this->filesManagerMenu;
		$control->setActiveCategory($this->parent ? $this->parent : $this->id);

		return $control;
	}


	/**
	 * File info handler
	 */
	public function handleFileInfo($hash): void
	{
		$fileInfo = $this->filesModel->findAll()->where("diskName", $hash)->fetch();
		if ($fileInfo) {
			$this->template->fileInfo = $fileInfo;
			$this->filesManagerFileNameFormFactory->setEditId($fileInfo->id);
			$this->filesManagerFileNameFormFactory->setDefaultValues($this['fileNameForm'], $fileInfo->id);
			$this->redrawControl("fileInfo");
		}
	}


	/**
	 * Order by set handler
	 */
	public function handleSetOrderBy($orderBy = null): void
	{
		$this->redirect('this', array('orderBy'=>$orderBy));
	}


	/**
	 * Delete file handler
	 * @todo: pri smazani hlavnich u clanku, udelat jine hlavni
	 */
	public function handleDelete($hash): void
	{
		$fileInfo = $this->filesModel->findByHash($hash)->fetch();
		if (!$fileInfo) {
			throw new \InvalidArgumentException("File with hash '$hash' not found.");
		}
		$file = new HashFileEntity();
		$file->setHash($fileInfo->diskName);
		$file->setExtension($fileInfo->extension);

		$this->fileManager->remove($file);
		$this->filesModel->deleteByHash($hash);

		$this->template->fileInfo = null;
		if ($this->isAjax()) {
			$this->redrawControl("fileInfo");
		} else {
			$this->redirect('this');
		}
	}


	/**
	 * Bulk editing delete file handler
	 * @todo: pri smazani hlavnich u clanku, udelat jine hlavni
	 */
	public function handleBulkEditingDelete(array $files): void
	{
		$filesInfo = $this->filesModel->getById($files);
		if (!$filesInfo) {
			throw new \InvalidArgumentException("File with id not found.");
		}
		foreach ($filesInfo as $fileInfo){
			$file = new HashFileEntity();
			$file->setHash($fileInfo->diskName);
			$file->setExtension($fileInfo->extension);

			$this->fileManager->remove($file);

		}
		//in model
		$this->filesModel->delete($files);

		$this->template->fileInfo = null;
		/*if ($this->isAjax()) {
			$this->redrawControl("fileInfo");
		} else {
			$this->redirect('this');
		}*/
		$this->flashMessage(SUCCESS_DELETE, FLASH_SUCCESS);
		$this->redirect('this');
	}


	/**
	 * Bulk moving of files delete file handler
	 * @throws \InvalidArgumentException
	 */
	public function handleChangeFolderOfFiles(int $toFolder, array $files): void
	{
		if(empty($files))
		{
			return;
		}
		$filesInfo = $this->filesModel->findByIds($files);
		if (!$filesInfo->count()) {
			throw new \InvalidArgumentException("File with id not found.");
		}

		$this->filesModel->changeFilesFolder($toFolder, $files);

		$this->template->fileInfo = null;

		if ($this->isAjax()) {
			$this->redrawControl("fileInfo");
			$this->redrawControl("files");
			$this->redrawControl("filesManagerMenuFolders"); //puvodni snippetArea nefungovala
		} else {
			$this->redirect('this');
		}
	}


	/**
	 * Bulk moving of files delete file handler
	 * @throws \InvalidArgumentException
	 */
	public function handleRepairImageOrientation(int $fileId): void
	{
		$fileRow = $this->filesModel->getById($fileId);
		if(!$fileRow)
		{
			throw new Nette\InvalidArgumentException("File with file id '$fileId' not exist.");
		}

		$imageEntity = new HashImageEntity();
		$imageEntity->setId($fileRow->id);
		$imageEntity->setName($fileRow->diskName);
		$imageEntity->setExtension($fileRow->extension);
		$imageEntity->setSize($fileRow->size);
		$imageEntity->setHash($fileRow->diskName);

		$configParameter = $this->context->getParameters();
		//todo: nejak upravit - presunout do storage
		$filePath = $configParameter['wwwDir'].'/files/'.$this->filesStorage->createCacheDirectoryPath($imageEntity->getHash()).'.'.$imageEntity->getExtension();

		//skip if is not jpg
		$fileInfo = new \SplFileInfo($filePath);
		if(!file_exists($filePath) || ($fileInfo->getExtension() != 'jpg' && $fileInfo->getExtension() != 'JPG' && $fileInfo->getExtension() != 'jpeg'))
		{
			return;
		}

		$exif = exif_read_data($filePath);
		if($exif && isset($exif['Orientation']))
		{
			$orientation = $exif['Orientation'];
			if($orientation != 1)
			{
				$img = imagecreatefromjpeg($filePath);
				$deg = 0;
				switch($orientation)
				{
					case 3:
						$deg = 180;
						break;
					case 6:
						$deg = 270;
						break;
					case 8:
						$deg = 90;
						break;
				}
				if($deg)
				{
					$img = imagerotate($img, $deg, 0);
				}

				imagejpeg($img, $filePath, 95);
			}
		}

		//remove cache
		$this->filesStorage->removeCache($imageEntity);

		//invalidate
		$this->redrawControl('files');
	}


	/**
	 * Bulk moving of files delete file handler
	 * @throws \InvalidArgumentException
	 */
	public function handleRotateImage(int $fileId, string $rotateDirection): void
	{
		$fileRow = $this->filesModel->getById($fileId);
		if(!$fileRow)
		{
			throw new Nette\InvalidArgumentException("File with file id '$fileId' not exist.");
		}

		$imageEntity = new HashImageEntity();
		$imageEntity->setId($fileRow->id);
		$imageEntity->setName($fileRow->diskName);
		$imageEntity->setExtension($fileRow->extension);
		$imageEntity->setSize($fileRow->size);
		$imageEntity->setHash($fileRow->diskName);

		$configParameter = $this->context->getParameters();
		$imageRequest = new ImageRequest($imageEntity);
		//todo: nejak upravit
		$filePath = $configParameter['wwwDir'].'/files/'.$this->filesStorage->createCacheDirectoryPath($imageEntity->getHash()).'.'.$imageEntity->getExtension();

		//skip if is not jpg
		$fileInfo = new \SplFileInfo($filePath);
		if(!file_exists($filePath) || ($fileInfo->getExtension() != 'jpg' && $fileInfo->getExtension() != 'JPG' && $fileInfo->getExtension() != 'jpeg'))
		{
			return;
		}

		$image = Nette\Utils\Image::fromFile($filePath);
		if($rotateDirection == 'left')
		{
			$image->rotate(90, 0);
		}
		elseif($rotateDirection == 'right')
		{
			$image->rotate(-90, 0);
		}

		$image->save($filePath, 90);

		//remove cache
		$this->filesStorage->removeCache($imageEntity);

		//invalidate
		$this->redrawControl('file-'.$imageEntity->getId());
		$this->redrawControl('files');
	}


	/**
	 * Get correct icon for file
	 */
	public function getIconOfFile(string $fileExtension): string
	{
		switch ($fileExtension) {
			case "pdf":
				return $fileExtension;

			default:
				return "text";
		}
	}

}
