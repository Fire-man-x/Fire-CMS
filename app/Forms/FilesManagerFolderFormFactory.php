<?php
declare(strict_types=1);

namespace App\Forms;

use App\Model;
use Nette\Application\UI\Form;


class FilesManagerFolderFormFactory extends BaseFormFactory
{

	private ?int $parentFolder = null;


	public function __construct(FormFactory $factory, private Model\FileFolders $model)
	{
		parent::__construct($factory);
	}


	/**
	 * Parent folder id
	 */
	public function setParentFolder(int $parentFolder)
	{
		$this->parentFolder = $parentFolder;
	}


	public function create(int|string $editId = null): Form
	{
		$form = parent::create($editId);

		$form->addText("name", "Name")
			->setRequired();

		if($this->parentFolder){
			$form->addHidden("parentId", $this->parentFolder);
		}

		$form->addSubmit('send', 'Save');

		$form->onSuccess[] = array($this, 'formSucceeded');

		return $form;
	}


	public function formSucceeded(Form $form, $values)
	{
		unset($values->editId);

		/*if($this->parentFolder){
			$values->parentId = $this->parentFolder;
		}*/


		if ($this->isEditMode()) {
			$this->model->update($this->getEditId(), (array) $values);
		} else {
			$id = $this->model->insert($values);
			$form->getPresenter()->id = $id;
		}

		$form->getPresenter()->flashMessage(SUCCESS_SAVE, FLASH_SUCCESS);
	}


	/**
	 * Set default values to modal form
	 */
	public function setDefaultValues(Form $form, int $editId)
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
