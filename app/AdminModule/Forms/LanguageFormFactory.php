<?php
declare(strict_types=1);

namespace App\AdminModule\Forms;

use App\Model;
use Nette\Application\UI\Form;
use Nette\Forms\Controls\Checkbox;


class LanguageFormFactory extends AdminFormFactory
{
	public function __construct(private readonly Model\Database\Languages $model)
	{
		parent::__construct();
	}


	public function create(int|string|null $editId = null): AdminForm
	{
		$form = parent::create($editId);

		$form->data->addCheckbox('active', 'Active');

		$form->data->addCheckbox('default', 'Default');

		$languageIdControl = $form->data->addText('languageId', 'Id')
			->setRequired(VALIDATE_REQUIRED)
			->setDefaultValue($editId);
		$languageIdControl->getControlPrototype()->maxlength(2);

		$form->data->addText('name', 'Name')
			->setRequired(VALIDATE_REQUIRED)
			->getControlPrototype()->maxlength(20);

		$form->data->addText('shortcut', 'Shortcut')
			->setRequired(VALIDATE_REQUIRED)
			->getControlPrototype()->maxlength(2);

		$form->buttons->addSubmit('send', 'Save');

		$form->onValidate[] = array($this, 'formValidate');
		$form->onSuccess[] = array($this, 'formSucceeded');
		return $form;
	}


	public function formValidate(AdminForm $form, AdminFormValues $values): void
	{
		// výchozí jazyk musí existovat vždy - zrušit ho jde jen nastavením jiného jazyka jako výchozího
		$isDefaultLanguage = $this->isEditMode() && $this->model->getById((string) $this->getEditId())?->default;

		$defaultControl = $form->data['default'];
		if ($isDefaultLanguage && !$values->data->default && $defaultControl instanceof Checkbox) {
			$defaultControl->addError('The default language cannot be unset. Set another language as default instead.');
		}

		// výchozí jazyk musí být aktivní - nový výchozí se aktivuje sám (Languages::setDefault()), stávající
		// výchozí nejde deaktivovat
		$activeControl = $form->data['active'];
		if ($isDefaultLanguage && !$values->data->active && $activeControl instanceof Checkbox) {
			$activeControl->addError('The default language cannot be deactivated.');
		}

		//only if type is disabled by ajax
		/*if($form['type']->hasErrors()){
			unset($form['type']);
		}*/
	}


	public function formSucceeded(AdminForm $form, AdminFormValues $values): void
	{
		unset($values->editId);

		// `default` se neukládá přímo - Languages::setDefault() zruší výchozí u ostatních jazyků (jinak by
		// po uložení formuláře se zaškrtnutým "Default" byly výchozí dva jazyky)
		$isDefault = (bool) $values->data->default;
		unset($values->data->default);

		if ($this->isEditMode()) {
			$this->model->update((string) $this->getEditId(), (array) $values->data);
		} else {
			$this->model->insert($values->data);
		}

		if ($isDefault) {
			$this->model->setDefault((string) $values->data->languageId);
		}
	}


	/**
	 * Set default values to modal form
	 * @param int|string $editId ID jazyka je jeho kód (`languageId`, např. "en"), ne číslo
	 */
	public function setDefaultValues(Form $form, int|string $editId): void
	{
		if (!$this->isModal()) {
			throw new \InvalidArgumentException("Can not use in non 'modal' mode.");
		}

		$defaults = $this->model->getById($editId);
		if (!$defaults) {
			throw new \InvalidArgumentException("Can not edit item with id '" . $editId . "'");
		}

		/*if ($defaults?->default) {
			//$form["type"]->setDisabled(true);
		}*/

		$form->setDefaults($defaults);
	}

}
