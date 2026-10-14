<?php
declare(strict_types=1);

namespace App\AdminModule\Forms;

use App\Model\Database\DynamicForms;
use App\Service\LanguageService;
use Nette\Application\UI\Form;
use Nette\Localization\Translator;

class DynamicFormItemFormFactory extends AdminFormFactory
{
	/**
	 * DynamicFormId
	 */
	public int $dynamicFormId;


	public function __construct(
		private readonly Translator $translator,
		private readonly LanguageService $languages,
		private readonly DynamicForms $dynamicFormsModel,
	) {
		parent::__construct();
	}


	public function setDynamicFormId(int $dynamicFormId): static
	{
		$this->dynamicFormId = $dynamicFormId;
		return $this;
	}


	public function create(int|string|null $editId = null): AdminForm
	{
		$form = parent::create($editId);

		//name
		$nameControl = $form->data->addText("name", "Input name")
			->setRequired(VALIDATE_REQUIRED);
		$nameControl->addRule(Form::Pattern, '\'%label\' can contains only this chars "a-z" or "_".', '[a-z_]+');

		//translation - locales[languageId]
		$moreLanguages = count($this->languages->getActiveLanguages())>1;
		foreach($this->languages->getActiveLanguages() as $activeLanguage)
		{
			$languageContainer = $form->locales->addContainer($activeLanguage['languageId']);

			$label = 'Label';
			if($moreLanguages)
			{
				$label = $this->translator->translate($label).' ('.$activeLanguage['shortcut'].')';
			}
			$labelControl = $languageContainer->addText("label", $label)
				->setRequired(VALIDATE_REQUIRED);
			if($moreLanguages)
			{
				$labelControl->setTranslator(null);
			}
		}

		//type
		$form->data->addSelect("type", "Type", array(
			"text" => "Text",
			"email" => "Email",
			"textarea" => "Textarea",
			"integer" => "Integer",
			"select" => "Select"
		));

		//required
		$form->data->addCheckbox("required", "Required");
		
		/*
		//edit mode
		if ($this->isEditMode()) {
			$defaults = $this->metasService->getStructureByColumnId($this->type, $language, $this->getEditId(), true);
			$values = array();
			foreach ($defaults as $default){
				$values[$default["id"]] = $default["value"];
			}

			$form->setValues($values);
		}*/
		$form->buttons->addSubmit('send', 'Save');

		$form->onValidate[] = array($this, 'formValidate');
		$form->onSuccess[] = array($this, 'formSucceeded');
		return $form;
	}


	public function formValidate(AdminForm $form, AdminFormValues $values): void
	{
		$name = is_string($values->data['name']) ? $values->data['name'] : '';
		$duplicityExist = $this->dynamicFormsModel->testIfItemNameExist($this->dynamicFormId, $name, $this->isEditMode() ? (string) $this->getEditId() : null);
		if($duplicityExist)
		{
			$form->addError((string) $form->getTranslator()?->translate("Cannot insert duplicate input name '%s'", $name), false);
			$form->getPresenter()->flashMessage(FAIL_SAVE, FLASH_FAILED);
		}
	}


	public function formSucceeded(AdminForm $form, AdminFormValues $values): void
	{
		$name = is_string($values->data['name']) ? $values->data['name'] : '';

		if ($this->isEditMode()) {
			//editId je u položky její název (string) - BaseFormFactory::setEditId() by číselný název převedl na int
			$this->dynamicFormsModel->updateItem($this->dynamicFormId, (string) $this->getEditId(), (array) $values->data);
			$this->dynamicFormsModel->updateItemTranslation($this->dynamicFormId, $name, $values->locales); //cannot use editId (because can be changed in form)
		} else {
			$this->dynamicFormsModel->insertItem($this->dynamicFormId, (array) $values->data);
			$this->dynamicFormsModel->updateItemTranslation($this->dynamicFormId, $name, $values->locales); //cannot use editId (because can be changed in form)
		}
	}


	/**
	 * Set default values to modal form
	 */
	public function setDefaultValues(Form $form, int|string $editId): void
	{
		parent::setDefaultValues($form, (int) $editId);

		$defaults = $this->dynamicFormsModel->getItem($this->dynamicFormId, $editId);
		$translations = $this->dynamicFormsModel->getItemTranslation($this->dynamicFormId, $editId);
		$defaults ??= array();
		$defaults['locales'] = array();
		foreach($translations ?? [] as $languageId => $translation)
		{
			foreach($translation['items'] ?: [] as $inputName => $translationItem)
			{
				if($inputName == $editId)
				{
					//set to
					$defaults['locales'][$languageId] = $translationItem;
				}
			}
		}
		$form->setDefaults($defaults);
	}

}
