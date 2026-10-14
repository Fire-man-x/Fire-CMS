<?php
declare(strict_types=1);

namespace App\AdminModule\Forms;

use App\Model\Database\Sliders;
use Nette\Application\UI\Form;
use Nette\Utils\Strings;

class SliderFormFactory extends AdminFormFactory
{
	public function __construct(private readonly Sliders $model)
	{
		parent::__construct();
	}


	public function create(int|string|null $editId = null): AdminForm
	{
		$form = parent::create($editId);

		$form->data->addText('name', 'Title')
			->setRequired(VALIDATE_REQUIRED);

		$form->data->addText('location', 'Template location')
			->setRequired(VALIDATE_REQUIRED);

		$form->data->addText('duration', 'Display duration')
			->setRequired(VALIDATE_REQUIRED)
			->addRule(Form::Integer, VALIDATE_FORMAT);

		$form->data->addText('speed', 'Animation speed')
			->setRequired(VALIDATE_REQUIRED)
			->addRule(Form::Integer, VALIDATE_FORMAT);

		$form->data->addCheckbox('navigation', 'Show navigation');

		$form->data->addCheckbox('manual', 'Manual');

		$form->buttons->addSubmit('send', 'Save');

		$form->onSuccess[] = array($this, 'formSucceeded');
		return $form;
	}


	public function formSucceeded(AdminForm $form, AdminFormValues $values): void
	{
		unset($values->editId);

		$location = $values->data['location'];
		$values->data['location'] = str_replace("-", "_", Strings::webalize(is_string($location) ? $location : ''));

		if ($this->isEditMode()) {
			$this->model->update((int) $this->getEditId(), (array) $values->data);
		} else {
			$this->model->insert($values->data);
		}
	}


	/**
	 * Set default values to modal form
	 */
	public function setDefaultValues(Form $form, int $editId): void
	{
		parent::setDefaultValues($form, $editId);

		$defaults = $this->model->getById($editId);
		if (!$defaults) {
			throw new \InvalidArgumentException("Can not edit item with id '" . $editId . "'");
		}

		$form->setDefaults($defaults);
	}

}
