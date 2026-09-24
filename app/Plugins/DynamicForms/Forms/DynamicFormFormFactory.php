<?php
declare(strict_types=1);

namespace App\Plugins\DynamicForms\Forms;

use App\Plugins\DynamicForms\Model\DynamicForms;
use App\Forms\BaseFormFactory;
use App\Forms\FormFactory;
use App\Service\LanguageService;
use Nette\Application\UI\Form;
use Nette\Localization\Translator;


class DynamicFormFormFactory extends BaseFormFactory
{
	public static string $allLanguages = "All languages";

	private Translator $translator;

	private DynamicForms $model;

	private LanguageService $languages;

	/**
	 * Types
	 */
	public static array $types = array(
		'article' => 'Article',
		'category' => 'Category',
		'file' => 'File'
	);


	public function __construct(Translator $translator, LanguageService $languages, DynamicForms $model)
	{
		parent::__construct();
		$this->translator = $translator;
		$this->languages = $languages;
		$this->model = $model;
	}


	public function create(int|string $editId = null): Form
	{
		$form = parent::create($editId);

		$templateNameControl = $form->addText('templateName', 'Name in template')
			->setRequired(VALIDATE_REQUIRED);
		$templateNameControl->addRule(Form::PATTERN, '\'%label\' can contains only this chars "a-z" or "_".', '[a-z_]+');
		$templateNameControl->addRule(function ($item) use ($form)
		{
			if($form->isSubmitted() && !empty($form->getUntrustedValues()->editId))
			{
				$this->setEditId($form->getUntrustedValues()->editId);
			}

			$exist = $this->model->findByTemplateName($item->value, $this->isEditMode() ? $this->getEditId() : null)->fetch();
			if ($exist) {
				return false;
			}
			else{
				return true;
			}
		}, VALIDATE_EXIST);

		//translation
		$translationContainer = $form->addContainer('translation');
		$moreLanguages = count($this->languages->getActiveLanguages())>1;
		foreach($this->languages->getActiveLanguages() as $activeLanguage)
		{
			$languageContainer = $translationContainer->addContainer($activeLanguage['languageId']);
			//title
			$label = 'Title';
			if($moreLanguages)
			{
				$label = $this->translator->translate($label).' ('.$activeLanguage['shortcut'].')';
			}
			$labelControl = $languageContainer->addText("title", $label)
				->setRequired(VALIDATE_REQUIRED);
			if($moreLanguages)
			{
				$labelControl->setTranslator(null);
			}

			//submitMessage
			$label = 'Form submit message';
			if($moreLanguages)
			{
				$label = $this->translator->translate($label).' ('.$activeLanguage['shortcut'].')';
			}
			$labelControl = $languageContainer->addText("submitMessage", $label);
			if($moreLanguages)
			{
				$labelControl->setTranslator(null);
			}
		}

		//whereToSend
		$controlWhereToSend = $form->addSelect('whereToSend', 'Where to send', array('email' => 'Email'))
			->setPrompt(PROMPT_VALUE);
		//afterSendInformations
		$controlAfterSendInformation = $form->addText('afterSendInformationsEmail', 'Email')
			->setHtmlType('email');
		$controlAfterSendInformation->addConditionOn($controlWhereToSend, Form::Equal, 'email')
				->addRule(Form::Filled, VALIDATE_REQUIRED)
				->addRule(Form::Email, VALIDATE_FORMAT);
		$controlAfterSendInformation->getLabelPrototype()->addAttributes(array('id' => $controlAfterSendInformation->getHtmlId().'-label'));

		//add toggle
		$controlWhereToSend->addCondition(Form::Equal, 'email')
			->toggle($controlAfterSendInformation->getHtmlId())
			->toggle($controlAfterSendInformation->getHtmlId().'-label');

		//submit
		$form->addSubmit('send', 'Save');

		$form->onValidate[] = array($this, 'formValidate');
		$form->onSuccess[] = array($this, 'formSucceeded');
		$form->onError[] = function($form){
			foreach($form->getErrors() as $error)
			{
				$form->getPresenter()->flashMessage($error, FLASH_FAILED);
			}
		};
		return $form;
	}


	public function formValidate(Form $form, array $values): void
	{
		//condition is used on control
		/*$exist = $this->model->findByTemplateName($values->templateName, $this->isEditMode() ? $this->getEditId() : null)->fetch();

		if ($exist) {
			$form['templateName']->addError($form->getTranslator()->translate(VALIDATE_EXIST), false);

			$form->getPresenter()->flashMessage(FAIL_SAVE, FLASH_FAILED);
		}*/
	}


	public function formSucceeded($form, $values): void
	{
		unset($values->editId);

		$translations = $values->translation;
		unset($values->translation);

		$values->afterSendInformations = serialize(array(
			"send_to" => $values->afterSendInformationsEmail
		));
		unset($values->afterSendInformationsEmail);

		if ($this->isEditMode()) {
			$this->model->update($this->getEditId(), (array) $values);
			foreach($translations as $language => $translation)
			{
				$this->model->updateTranslation((int) $this->getEditId(), $language, $translation);
			}
		} else {
			//autor formuláře - DynamicFormsPresenter podle něj hlídá oprávnění "edit" u vlastních záznamů
			$values->createdBy = $form->getPresenter()->getUser()->getId();
			$insertId = $this->model->insert($values);

			foreach($translations as $language => $translation)
			{
				$this->model->insertTranslation($insertId, $language, $translation);
			}
		}
	}


	/**
	 * Set default values to modal form
	 */
	public function setDefaultValues(Form $form, int $editId): void
	{
		parent::setDefaultValues($form, $editId);

		$defaults = $this->model->getById($editId);
		$translations = $this->model->getAllWithTranslation()->where($this->model->getForeignKeyColumn(), $editId);

		if($defaults)
		{
			$defaults = $defaults->toArray();
			//todo: sjednotit na jedno misto
			$afterSendInformations = @unserialize($defaults['afterSendInformations']);
			$defaults['afterSendInformationsEmail'] = isset($afterSendInformations['send_to']) ? $afterSendInformations['send_to'] : null;
			$defaults['translation'] = $translations->fetchAssoc('languageId');
		}

		$form->setDefaults($defaults);
	}

}
