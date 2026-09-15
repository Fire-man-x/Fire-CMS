<?php
declare(strict_types=1);

namespace App\Components\FilesManagerMenu;

use App\Attributes\Privilege;
use App\Attributes\Resource;
use App\Attributes\Secured;
use App\Forms\FilesManagerFolderFormFactory;
use App\Model\FileFolders;
use App\Model\Files;
use Nette\Application\Attributes\Persistent;
use Nette\Application\ForbiddenRequestException;
use Nette\Application\UI\Control;
use Nette\Application\UI\Form;
use Nette\Utils\ArrayHash;


/**
 * Class FilesManagerMenu
 *
 * FilesManagerMenu Component
 */
class FilesManagerMenu extends Control
{
	#[Persistent]
	public int $parentFolder;

	private FileFolders $fileFoldersModel;

	private Files $filesModel;

	private FilesManagerFolderFormFactory $filesManagerFolderFormFactory;

	/**
	 * @public for template
	 */
	public \Nette\Localization\Translator $translator;

	private string $templateFile;

	private array $fileFolders;

	private int $activeItem;


	/**
	 * FileFoldersMenu
	 */
	public function __construct(FileFolders $fileFoldersModel, Files $filesModel, FilesManagerFolderFormFactory $filesManagerFolderFormFactory, \Nette\Localization\Translator $translator)
	{
		$this->fileFoldersModel = $fileFoldersModel;
		$this->filesModel = $filesModel;
		$this->filesManagerFolderFormFactory = $filesManagerFolderFormFactory;
		$this->translator = $translator;
	}


	/**
	 * Sign-up form factory.
	 */
	protected function createComponentFolderNameForm(): Form
	{
		$this->filesManagerFolderFormFactory->asModal();
		$this->filesManagerFolderFormFactory->setParentFolder($this->parentFolder);
		$form = $this->filesManagerFolderFormFactory->create();
		$form->setTranslator($this->translator);
		//$form->getElementPrototype()->addClass("ajax");
		$self = $this;
		$form->onSuccess[] = function($form) use ($self){

			if($self->presenter->isAjax()){
				$self->redrawControl("folders");
			} else {
				$self->redirect('this');
			}
		};

		return $form;
	}


	public function setActiveCategory($activeItem)
	{
		$this->activeItem = $activeItem;
		return $this;
	}


	public function customTemplate(string $template = null): void
	{
		$this->templateFile = $template ?: __DIR__ . '/FilesManagerMenu.latte';
	}


	/**
	 * Render function
	 */
	public function render()
	{
		$this->customTemplate();

		$this->template->setFile($this->templateFile);

		//remover parent folder from url
		/* nefunguje
		if(!$this->presenter->isAjax()){
			$this->parentFolder = null;
		}*/

		$this->fileFolders = $this->createTree($this->fileFoldersModel->findAll()->order("position")->fetchAll());
		$this->template->fileFolders = $this->fileFolders;
		$this->template->activeItem = $this->activeItem;

		$this->template->setTranslator($this->translator);
		$this->template->render();
	}


	/**
	 * Create tree
	 */
	private function createTree($items, $parent=null, $level=0)
	{
		$tree = array();
		foreach ($items as $itemId => $item){
			if($item->parent_id == $parent){
				$fileFolder = $item->toArray();
				unset($items[$itemId]);
				$fileFolder['childs'] = $this->createTree($items, $fileFolder['file_folder_id'], $level+1);
				//@todo: moznost zrychlit jednim dotazem
				$fileFolder['itemCount'] = $this->filesModel->findAll()->where("file_folder_id", $fileFolder['file_folder_id'])->count();
				$tree[] = ArrayHash::from($fileFolder, false);
			}
		}
		return $tree;
	}


	/**
	 * Decode tree to array
	 */
	private function treeToArray(&$toArray, $items, $parent=null, $level=0)
	{
		foreach ($items as $position => $item){
			$toArray[] = array(
				"file_folder_id"=>$item["id"],
				"parent_id"=>$parent,
				"position"=>$position,
				"level"=>$level
			);

			if(isset($item["children"])){
				$this->treeToArray($toArray, $item["children"], $item["id"], $level+1);
			}
		}
	}


	/**
	 * Sort menu handler
	 */
	public function handleSortMenu(array $items)
	{
		$updateItems = array();
		$this->treeToArray($updateItems, $items);

		$this->fileFoldersModel->updateTreePositions($updateItems);

		$this->presenter->terminate();
	}


	/**
	 * Add Folders handler
	 */
	#[Secured]
	#[Resource('Files')]
	#[Privilege('add')]
	public function handleAddFolder(int $parentFolder): void
	{
		$this->filesManagerFolderFormFactory->resetEditMode();
		$this->filesManagerFolderFormFactory->setParentFolder($parentFolder);

		$this->redrawControl("folderNameForm");
	}


	/**
	 * Add Folders handler
	 */
	#[Secured]
	#[Resource('Files')]
	#[Privilege('edit')]
	public function handleEditFolder($folder_id)
	{
		$this->filesManagerFolderFormFactory->setEditId($folder_id);
		$this->filesManagerFolderFormFactory->setDefaultValues($this["folderNameForm"], $folder_id);

		$this->redrawControl("folderNameForm");
	}


	/**
	 * Delete folder handler
	 */
	#[Secured]
	#[Resource('Files')]
	#[Privilege('delete')]
	public function handleRemoveFolder($folder_id)
	{
		$folderInfo = $this->fileFoldersModel->getById($folder_id);
		if($folderInfo && $folderInfo->default){
			throw new ForbiddenRequestException("You have not permissions to remove folder.");
		}
		$defaultId = 1;
		$redirectToId = null;
		if($this->getPresenter()->id == $folder_id){
			$redirectToId = $folderInfo->parent_id != null ? $folderInfo->parent_id : $defaultId;
		}

		$this->filesModel->findAll()
			->where("file_folder_id", $folder_id)
			->update(array(
			"file_folder_id" => $folderInfo->parent_id != null ? $folderInfo->parent_id : $defaultId
		));

		$this->fileFoldersModel->delete($folder_id);

		$this->flashMessage(SUCCESS_DELETE, FLASH_SUCCESS);
		/*if($this->getPresenter()->isAjax()){
			$this->getPresenter()->id = $redirectToId;
			$this->getPresenter()->payload->redtodi = $redirectToId;
			$this->redrawControl("folders");
		} else {
			$this->redirect("this", array("id"=>22));
		}*/
		if(isset($redirectToId)){
			$this->getPresenter()->redirect("this", array("id"=>$redirectToId));
		} else {
			$this->redirect('this');
		}
	}


}