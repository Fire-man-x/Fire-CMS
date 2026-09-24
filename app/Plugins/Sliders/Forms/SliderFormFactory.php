<?php
declare(strict_types=1);

namespace App\Plugins\Sliders\Forms;

use App\Forms\BaseFormFactory;
use App\Forms\FormFactory;
use App\Plugins\Sliders\Model\Sliders;
use Nette\Application\UI\Form;

class SliderFormFactory extends BaseFormFactory
{
	public function __construct(private Sliders $model)
	{
		parent::__construct();
	}


	public function create(int|string $editId = null): Form
	{
		$form = parent::create($editId);

		$form->addText('name', 'Title')
			->setRequired(VALIDATE_REQUIRED);

		$form->addText('location', 'Template location')
			->setRequired(VALIDATE_REQUIRED);

		$form->addText('duration', 'Display duration')
			->setRequired(VALIDATE_REQUIRED)
			->addRule(Form::INTEGER, VALIDATE_FORMAT);

		$form->addText('speed', 'Animation speed')
			->setRequired(VALIDATE_REQUIRED)
			->addRule(Form::INTEGER, VALIDATE_FORMAT);

		$form->addCheckbox('navigation', 'Show navigation');

		$form->addCheckbox('manual', 'Manual');

		$form->addSubmit('send', 'Save');

		$form->onSuccess[] = array($this, 'formSucceeded');
		return $form;
	}


	public function formSucceeded($form, $values): void
	{
		unset($values->editId);

		$values->location = str_replace("-", "_", \Nette\Utils\Strings::webalize($values->location));

		if ($this->isEditMode()) {
			$this->model->update($this->getEditId(), (array) $values);
		} else {
			$this->model->insert($values);
		}
	}


	/**
	 * Set default values to modal form
	 */
	public function setDefaultValues(Form $form, int $editId): void
	{
		$defaults = $this->model->getById($editId);

		$form->setDefaults($defaults);
	}

}
