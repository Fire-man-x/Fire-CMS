<?php
declare(strict_types=1);

namespace App\Forms;

use App\Model;
use Nette\Application\UI\Form;
use Nette\Localization\Translator;
use Nette\Utils\ArrayHash;


class FilesManagerFileNameFormFactory extends BaseFormFactory
{

	public function __construct(FormFactory $factory, private Translator $translator, private Model\Files $model)
	{
		parent::__construct($factory);
	}


	public function create(int|string $editId = null): Form
	{
		$form = parent::create((int) $editId);

		$form->addText("newName", $this->translator->translate("Name"))
			//->setRequired()
			->setTranslator(null);

		/*if ($this->isEditMode()) {
			$data = $this->model->findById($this->getEditId())->fetch();
			if (!$data) {
				throw new \InvalidArgumentException("Can not edit item with id '" . $this->getEditId() . "'");
			}
			$form->setDefaults($data);
		}*/

		$form->addSubmit('send', 'Save');

		$form->onSuccess[] = array($this, 'formSucceeded');

		return $form;
	}


	public function formSucceeded(Form $form, ArrayHash $values)
	{
		unset($values->editId);

		if ($this->isEditMode()) {
			$this->model->update($this->getEditId(), (array) $values);
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
	public function setDefaultValues(Form $form, int $editId)
	{
		parent::setDefaultValues($form, $editId);

		$defaults = $this->model->getById($editId);
		if ($defaults) {
			$defaults = $defaults->toArray();
			/*if(!$defaults["newName"]){
				$defaults["newName"] = $defaults["originalName"];
			}*/
			$form["newName"]->getControlPrototype()->placeholder($defaults["originalName"]);

			$form->setDefaults($defaults);
		}
	}


}
