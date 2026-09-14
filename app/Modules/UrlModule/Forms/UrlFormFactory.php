<?php
declare(strict_types=1);

namespace App\Modules\UrlModule\Forms;

use App\Modules\UrlModule\Model;
use App\Forms\BaseFormFactory;
use App\Forms\FormFactory;
use Nette\Application\UI\Form;


class UrlFormFactory extends BaseFormFactory
{

	public function __construct(FormFactory $factory, private Model $model)
	{
		parent::__construct($factory);
	}


	public function create(int|string $editId = null): Form
	{
		$form = parent::create($editId);

		$form->addCheckbox('active', 'Active');

		$form->addCheckbox('default', 'Default');

		$form->addText('name', 'Name')
			->setRequired(VALIDATE_REQUIRED)
			->getControlPrototype()->maxlength(20);

		$form->addText('shortcut', 'Shortcut')
			->setRequired(VALIDATE_REQUIRED)
			->getControlPrototype()->maxlength(2);

		$form->addSubmit('send', 'Save');

		$form->onValidate[] = array($this, 'formValidate');
		$form->onSuccess[] = array($this, 'formSucceeded');
		return $form;
	}


	public function formValidate($form, $values)
	{
		//only if type is disabled by ajax
		/*if($form['type']->hasErrors()){
			unset($form['type']);
		}*/
	}


	public function formSucceeded($form, $values)
	{
		unset($values->editId);

		if ($this->isEditMode()) {
			$this->model->update($this->getEditId(), $values);
		} else {
			$this->model->insert($values);
		}
	}


	/**
	 * Set default values to modal form
	 */
	public function setDefaultValues(Form $form, int $editId)
	{
		parent::setDefaultValues($form, $editId);

		$defaults = $this->model->findById($editId)->fetch();

		$form->setDefaults($defaults);
	}

}
