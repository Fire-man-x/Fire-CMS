<?php
declare(strict_types=1);

namespace App\Forms;

use App\Components\Menu\Model\Menus;
use Nette\Application\UI\Form;


class MenuFormFactory extends BaseFormFactory
{

	private Menus $model;


	public function __construct(FormFactory $factory, Menus $model)
	{
		parent::__construct($factory);
		$this->model = $model;
	}


	public function create(int|string $editId = null): Form
	{
		$form = parent::create($editId);

		$form->addCheckbox('active', 'Active');

		$form->addText('name', 'Name')
			->setRequired(VALIDATE_REQUIRED);

		$form->addText('location', 'Template location')
			->setRequired(VALIDATE_REQUIRED);

		$form->addSubmit('send', 'Save');

		$form->onSuccess[] = array($this, 'formSucceeded');

		return $form;
	}


	public function formSucceeded(Form $form, $values)
	{
		unset($values->editId);

		//santized location
		$values->location = $this->model->getLocation(empty($values->location) ? $values->title : $values->location, $this->getEditId());

		if ($this->isEditMode()) {
			$this->model->update($this->getEditId(), (array) $values);
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

		$defaults = $this->model->getById($editId);
		$this->setEditId($editId);

		if(!$defaults){
			throw new \InvalidArgumentException("Can not edit item with id '".$editId."'");
		}

		$form->setDefaults($defaults);
	}

}
