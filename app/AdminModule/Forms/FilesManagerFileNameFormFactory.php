<?php
declare(strict_types=1);

namespace App\AdminModule\Forms;

use App\Model;
use Nette\Application\UI\Form;


class FilesManagerFileNameFormFactory extends AdminFormFactory
{

	public function __construct(private Model\Database\Files $model)
	{
		parent::__construct();
	}


	public function create(int|string $editId = null): AdminForm
	{
		$form = parent::create($editId);

		$form->data->addText("newName", "Name")
			//->setRequired()
			->setTranslator(null);

		/*if ($this->isEditMode()) {
			$data = $this->model->findById($this->getEditId())->fetch();
			if (!$data) {
				throw new \InvalidArgumentException("Can not edit item with id '" . $this->getEditId() . "'");
			}
			$form->setDefaults($data);
		}*/

		$form->buttons->addSubmit('send', 'Save');

		$form->onSuccess[] = array($this, 'formSucceeded');

		return $form;
	}


	public function formSucceeded(AdminForm $form, AdminFormValues $values): void
	{
		unset($values->editId);

		if ($this->isEditMode()) {
			$this->model->update($this->getEditId(), (array) $values->data);
		} else {
			throw new \InvalidArgumentException("Can not insert file name.");
			//$id = $this->model->insert($values);
			//$form->getPresenter()->id = $id;
		}

		$form->getPresenter()->flashMessage(SUCCESS_SAVE, FLASH_SUCCESS);
	}


	/**
	 * Set default values to modal form
	 */
	public function setDefaultValues(Form $form, int $editId): void
	{
		parent::setDefaultValues($form, $editId);

		$defaults = $this->model->getById($editId);
		if ($defaults) {
			$defaults = $defaults->toArray();
			/*if(!$defaults["newName"]){
				$defaults["newName"] = $defaults["originalName"];
			}*/
			$form->data["newName"]->getControlPrototype()->placeholder($defaults["originalName"]);

			$form->setDefaults($defaults);
		}
	}


}
