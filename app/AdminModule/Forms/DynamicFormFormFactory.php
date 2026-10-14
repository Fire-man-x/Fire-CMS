<?php
declare(strict_types=1);

namespace App\AdminModule\Forms;

use App\Model\Database\DynamicForms;
use App\Service\LanguageService;
use Nette\Application\UI\Form;
use Nette\Forms\Control;
use Nette\Localization\Translator;
use Nette\Utils\ArrayHash;


class DynamicFormFormFactory extends AdminFormFactory
{
	public static string $allLanguages = "All languages";

	/**
	 * Types
	 * @var array<string, string>
	 */
	public static array $types = array(
		'article' => 'Article',
		'category' => 'Category',
		'file' => 'File'
	);


	public function __construct(
		private readonly Translator $translator,
		private readonly LanguageService $languages,
		private readonly DynamicForms $model,
	) {
		parent::__construct();
	}


	public function create(int|string|null $editId = null): AdminForm
	{
		$form = parent::create($editId);

		$templateNameControl = $form->data->addText('templateName', 'Name in template')
			->setRequired(VALIDATE_REQUIRED);
		$templateNameControl->addRule(Form::Pattern, '\'%label\' can contains only this chars "a-z" or "_".', '[a-z_]+');
		$templateNameControl->addRule(function (Control $item) use ($form): bool
		{
			$editId = $form->isSubmitted() ? $form->getUntrustedValues(AdminFormValues::class)->editId ?? null : null;
			if(!empty($editId))
			{
				$this->setEditId($editId);
			}

			$exist = $this->model->findByTemplateName($item->getValue(), $this->isEditMode() ? $this->getEditId() : null)->fetch();
			if ($exist) {
				return false;
			}
			else{
				return true;
			}
		}, VALIDATE_EXIST);

		//translation - locales[languageId]
		$moreLanguages = count($this->languages->getActiveLanguages())>1;
		foreach($this->languages->getActiveLanguages() as $activeLanguage)
		{
			$languageContainer = $form->locales->addContainer($activeLanguage['languageId']);
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
		$controlWhereToSend = $form->data->addSelect('whereToSend', 'Where to send', array('email' => 'Email'))
			->setPrompt(PROMPT_VALUE);
		//afterSendInformations
		$controlAfterSendInformation = $form->data->addText('afterSendInformationsEmail', 'Email')
			->setHtmlType('email');
		$controlAfterSendInformation->addConditionOn($controlWhereToSend, Form::Equal, 'email')
				->addRule(Form::Filled, VALIDATE_REQUIRED)
				->addRule(Form::Email, VALIDATE_FORMAT);
		$afterSendInformationId = (string) $controlAfterSendInformation->getHtmlId();
		$controlAfterSendInformation->getLabelPrototype()->addAttributes(array('id' => $afterSendInformationId.'-label'));

		//add toggle
		$controlWhereToSend->addCondition(Form::Equal, 'email')
			->toggle($afterSendInformationId)
			->toggle($afterSendInformationId.'-label');

		//submit
		$form->buttons->addSubmit('send', 'Save');

		$form->onValidate[] = array($this, 'formValidate');
		$form->onSuccess[] = array($this, 'formSucceeded');
		$form->onError[] = function(AdminForm $form): void {
			foreach($form->getErrors() as $error)
			{
				$form->getPresenter()->flashMessage($error, FLASH_FAILED);
			}
		};
		return $form;
	}


	public function formValidate(AdminForm $form, AdminFormValues $values): void
	{
		//condition is used on control
		/*$exist = $this->model->findByTemplateName($values->templateName, $this->isEditMode() ? $this->getEditId() : null)->fetch();

		if ($exist) {
			$form->data['templateName']->addError($form->getTranslator()->translate(VALIDATE_EXIST), false);

			$form->getPresenter()->flashMessage(FAIL_SAVE, FLASH_FAILED);
		}*/
	}


	public function formSucceeded(AdminForm $form, AdminFormValues $values): void
	{
		$data = $values->data;

		$data->afterSendInformations = serialize(array(
			"send_to" => $data->afterSendInformationsEmail
		));
		unset($data->afterSendInformationsEmail);

		if ($this->isEditMode()) {
			$this->model->update((int) $this->getEditId(), (array) $data);
			foreach($values->locales as $language => $translation)
			{
				$this->model->updateTranslation((int) $this->getEditId(), (string) $language, ArrayHash::from($translation));
			}
		} else {
			//autor formuláře - DynamicFormsPresenter podle něj hlídá oprávnění "edit" u vlastních záznamů
			$data->createdBy = $form->getPresenter()->getUser()->getId();
			$insertId = $this->model->insert($data);

			foreach($values->locales as $language => $translation)
			{
				$this->model->insertTranslation($insertId, (string) $language, ArrayHash::from($translation));
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

		if(!$defaults)
		{
			throw new \InvalidArgumentException("Can not edit item with id '" . $editId . "'");
		}

		$defaults = $defaults->toArray();
		//todo: sjednotit na jedno misto
		$afterSendInformations = is_string($defaults['afterSendInformations']) ? @unserialize($defaults['afterSendInformations'], ['allowed_classes' => false]) : false;
		$defaults['afterSendInformationsEmail'] = is_array($afterSendInformations) ? ($afterSendInformations['send_to'] ?? null) : null;
		$defaults['locales'] = $translations->fetchAssoc('languageId');

		$form->setDefaults($defaults);
	}

}
