<?php
declare(strict_types=1);

namespace App\Forms;

use App\Model;
use Nette\Application\UI\Form;


class LanguageFormFactory extends BaseFormFactory
{

	private Model\Languages $model;


	public function __construct(Model\Languages $model)
	{
		parent::__construct();
		$this->model = $model;
	}


	public function create(int|string $editId = null): Form
	{
		$form = parent::create($editId);

		$form->addCheckbox('active', 'Active');

		$form->addCheckbox('default', 'Default');

		$form->addText('languageId', 'Id')
			->setRequired(VALIDATE_REQUIRED)
			->getControlPrototype()->maxlength(2);
		$form['languageId']->setDefaultValue($editId);

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
		// výchozí jazyk musí existovat vždy - zrušit ho jde jen nastavením jiného jazyka jako výchozího
		if ($this->isEditMode() && !$values->default && $this->model->getById($this->getEditId())?->default) {
			$form['default']->addError('The default language cannot be unset. Set another language as default instead.');
		}

		// výchozí jazyk musí být aktivní - nový výchozí se aktivuje sám (Languages::setDefault()), stávající
		// výchozí nejde deaktivovat
		if ($this->isEditMode() && !$values->active && $this->model->getById($this->getEditId())?->default) {
			$form['active']->addError('The default language cannot be deactivated.');
		}

		//only if type is disabled by ajax
		/*if($form['type']->hasErrors()){
			unset($form['type']);
		}*/
	}


	public function formSucceeded($form, $values)
	{
		unset($values->editId);

		// `default` se neukládá přímo - Languages::setDefault() zruší výchozí u ostatních jazyků (jinak by
		// po uložení formuláře se zaškrtnutým "Default" byly výchozí dva jazyky)
		$isDefault = (bool) $values->default;
		unset($values->default);

		if ($this->isEditMode()) {
			$this->model->update($this->getEditId(), (array) $values);
		} else {
			$this->model->insert($values);
		}

		if ($isDefault) {
			$this->model->setDefault((string) $values->languageId);
		}
	}


	/**
	 * Set default values to modal form
	 * @param int|string $editId ID jazyka je jeho kód (`languageId`, např. "en"), ne číslo
	 */
	public function setDefaultValues(Form $form, int|string $editId)
	{
		if (!$this->isModal()) {
			throw new \InvalidArgumentException("Can not use in non 'modal' mode.");
		}

		$defaults = $this->model->getById($editId);

		/*if ($defaults?->default) {
			//$form["type"]->setDisabled(true);
		}*/

		$form->setDefaults($defaults);
	}

}
