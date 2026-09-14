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


	public function __construct(FormFactory $factory, Translator $translator, LanguageService $languages, DynamicForms $model)
	{
		parent::__construct($factory);
		$this->translator = $translator;
		$this->languages = $languages;
		$this->model = $model;
	}


	public function create(int|string $editId = null): Form
	{
		$form = parent::create($editId);

		$templateNameControl = $form->addText('template_name', 'Name in template')
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
			$languageContainer = $translationContainer->addContainer($activeLanguage['language_id']);
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

			//submit_message
			$label = 'Form submit message';
			if($moreLanguages)
			{
				$label = $this->translator->translate($label).' ('.$activeLanguage['shortcut'].')';
			}
			$labelControl = $languageContainer->addText("submit_message", $label);
			if($moreLanguages)
			{
				$labelControl->setTranslator(null);
			}
		}

		//where_to_send
		$controlWhereToSend = $form->addSelect('where_to_send', 'Where to send', array('email' => 'Email'))
			->setPrompt(PROMPT_VALUE);
		//after_send_informations
		$controlAfterSendInformation = $form->addText('after_send_informations_email', 'Email')
			->setType('email');
		$controlAfterSendInformation->addConditionOn($controlWhereToSend, Form::EQUAL, 'email')
				->addRule(Form::FILLED, VALIDATE_REQUIRED)
				->addRule(Form::EMAIL, VALIDATE_FORMAT);
		$controlAfterSendInformation->getLabelPrototype()->addAttributes(array('id' => $controlAfterSendInformation->getHtmlId().'-label'));

		//add toggle
		$controlWhereToSend->addCondition(Form::EQUAL, 'email')
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
		/*$exist = $this->model->findByTemplateName($values->template_name, $this->isEditMode() ? $this->getEditId() : null)->fetch();

		if ($exist) {
			$form['template_name']->addError($form->getTranslator()->translate(VALIDATE_EXIST), false);

			$form->getPresenter()->flashMessage(FAIL_SAVE, FLASH_FAILED);
		}*/
	}


	public function formSucceeded($form, $values): void
	{
		unset($values->editId);

		$translations = $values->translation;
		unset($values->translation);

		$values->after_send_informations = serialize(array(
			"send_to" => $values->after_send_informations_email
		));
		unset($values->after_send_informations_email);

		if ($this->isEditMode()) {
			$this->model->update($this->getEditId(), $values);
			foreach($translations as $language => $translation)
			{
				$this->model->updateTranslation((int) $this->getEditId(), $language, $translation);
			}
		} else {
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

		$defaults = $this->model->findById($editId)->fetch();
		$translations = $this->model->getAllWithTranslation()->where('dynamic_form_id', $editId);

		if($defaults)
		{
			$defaults = $defaults->toArray();
			//todo: sjednotit na jedno misto
			$afterSendInformations = @unserialize($defaults['after_send_informations']);
			$defaults['after_send_informations_email'] = isset($afterSendInformations['send_to']) ? $afterSendInformations['send_to'] : null;
			$defaults['translation'] = $translations->fetchAssoc('language_id');
		}

		$form->setDefaults($defaults);
	}

}
