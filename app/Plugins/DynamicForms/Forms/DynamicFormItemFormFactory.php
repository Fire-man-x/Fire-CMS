<?php
declare(strict_types=1);

namespace App\Plugins\DynamicForms\Forms;

use App\Plugins\DynamicForms\Model\DynamicForms;
use App\Forms\BaseFormFactory;
use App\Forms\FormFactory;
use App\Service\LanguageService;
use Nette\Application\UI\Form;
use Nette\Localization\Translator;

class DynamicFormItemFormFactory extends BaseFormFactory
{

	private Translator $translator;

	private LanguageService $languages;

	private DynamicForms $dynamicFormsModel;

	/**
	 * DynamicFormId
	 */
	public int $dynamicFormId;


	public function __construct(Translator $translator, LanguageService $languages, DynamicForms $dynamicFormsModel)
	{
		parent::__construct();
		$this->translator = $translator;
		$this->languages = $languages;
		$this->dynamicFormsModel = $dynamicFormsModel;
	}


	public function setDynamicFormId($dynamicFormId)
	{
		$this->dynamicFormId = $dynamicFormId;
		return $this;
	}


	public function create(int|string $editId = null): Form
	{
		$form = parent::create($editId);

		//name
		$nameControl = $form->addText("name", "Input name")
			->setRequired(VALIDATE_REQUIRED);
		$nameControl->addRule(Form::PATTERN, '\'%label\' can contains only this chars "a-z" or "_".', '[a-z_]+');

		//translation
		$translationContainer = $form->addContainer('translation');
		$moreLanguages = count($this->languages->getActiveLanguages())>1;
		foreach($this->languages->getActiveLanguages() as $activeLanguage)
		{
			$languageContainer = $translationContainer->addContainer($activeLanguage['languageId']);

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
		$form->addSelect("type", "Type", array(
			"text" => "Text",
			"email" => "Email",
			"textarea" => "Textarea",
			"integer" => "Integer",
			"select" => "Select"
		));

		//required
		$form->addCheckbox("required", "Required");
		
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
		$form->addSubmit('send', 'Save');

		$form->onValidate[] = array($this, 'formValidate');
		$form->onSuccess[] = array($this, 'formSucceeded');
		return $form;
	}


	public function formValidate(Form $form, $values): void
	{
		$duplicityExist = $this->dynamicFormsModel->testIfItemNameExist($this->dynamicFormId, $values["name"], $this->isEditMode() ? (string) $this->getEditId() : null);
		if($duplicityExist)
		{
			$form->addError($form->getTranslator()->translate("Cannot insert duplicate input name '%s'", $values["name"]), false);
			$form->getPresenter()->flashMessage(FAIL_SAVE, FLASH_FAILED);
		}
	}


	public function formSucceeded($form, $values): void
	{
		unset($values->editId);

		//values
		$translation = $values->translation;
		unset($values->translation);

		if ($this->isEditMode()) {
			//editId je u položky její název (string) - BaseFormFactory::setEditId() by číselný název převedl na int
			$this->dynamicFormsModel->updateItem($this->dynamicFormId, (string) $this->getEditId(), (array) $values);
			$this->dynamicFormsModel->updateItemTranslation($this->dynamicFormId, $values->name, (array) $translation); //cannot use editId (because can be changed in form)
		} else {
			$this->dynamicFormsModel->insertItem($this->dynamicFormId, (array) $values);
			$this->dynamicFormsModel->updateItemTranslation($this->dynamicFormId, $values->name, (array) $translation); //cannot use editId (because can be changed in form)
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
		$defaults['translation'] = array();
		foreach($translations as $languageId => $translation)
		{
			foreach($translation['items'] ?: [] as $inputName => $translationItem)
			{
				if($inputName == $editId)
				{
					//set to
					$defaults['translation'][$languageId] = $translationItem;
				}
			}
		}
		bdump($defaults);

		$form->setDefaults($defaults);
	}

}
