<?php
declare(strict_types=1);

namespace App\AdminModule\Forms;

use App\AdminModule\Presenters\FilesManagerPresenter;
use App\Model;
use Nette\Application\UI\Form;


class FilesManagerFolderFormFactory extends AdminFormFactory
{

	private ?int $parentFolder = null;


	public function __construct(private Model\Database\FileFolders $model)
	{
		parent::__construct();
	}


	/**
	 * Parent folder id
	 */
	public function setParentFolder(int $parentFolder): void
	{
		$this->parentFolder = $parentFolder;
	}


	public function create(int|string $editId = null): AdminForm
	{
		$form = parent::create($editId);

		$form->data->addText("name", "Name")
			->setRequired();

		if($this->parentFolder){
			$form->data->addHidden("parentId", $this->parentFolder);
		}

		$form->buttons->addSubmit('send', 'Save');

		$form->onSuccess[] = array($this, 'formSucceeded');

		return $form;
	}


	public function formSucceeded(AdminForm $form, AdminFormValues $values): void
	{
		unset($values->editId);

		/*if($this->parentFolder){
			$values->parentId = $this->parentFolder;
		}*/


		if ($this->isEditMode()) {
			$this->model->update($this->getEditId(), (array) $values->data);
		} else {
			$id = $this->model->insert($values->data);

			$presenter = $form->getPresenter();
			if($presenter instanceof FilesManagerPresenter){
				$presenter->id = $id;
			}
		}

		$form->getPresenter()->flashMessage(SUCCESS_SAVE, FLASH_SUCCESS);
	}


	/**
	 * Set default values to modal form
	 */
	public function setDefaultValues(Form $form, int $editId): void
	{
		parent::setDefaultValues($form, $editId);

		$defaults = array();
		if($editId){
			$defaults = $this->model->getById($editId);

			if ($defaults->default) {
				//$form["type"]->setDisabled(true);
				throw new \InvalidArgumentException("Can not edit default value.");
			}
		}

		$form->setDefaults($defaults);
	}


}
