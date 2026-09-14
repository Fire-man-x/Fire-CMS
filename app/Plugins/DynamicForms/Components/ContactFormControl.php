<?php
declare(strict_types=1);

namespace App\Plugins\DynamicForms\Components;

use App\Components\BaseControl;
use App\Components\FileManager\FileManager;
use App\Plugins\DynamicForms\Forms\ContactFormFactory;
use App\Plugins\DynamicForms\Model\DynamicForms;
use Nette\Localization\Translator;

/**
 * Class ContactFormControl
 */
class ContactFormControl extends BaseControl
{
	private ContactFormFactory $contactFormFactory;

	private Translator $translator;

	private DynamicForms $dynamicFormsModel;

	private string $templateFile;

	private ?string $formNameFromTemplate = null;

	private ?string $language = null;


	/**
	 * ContactForm constructor.
	 */
	public function __construct(FileManager $fileManager, ContactFormFactory $contactFormFactory, Translator $translator, DynamicForms $dynamicFormsModel)
	{
		parent::__construct($fileManager);
		$this->contactFormFactory = $contactFormFactory;
		$this->translator = $translator;
		$this->dynamicFormsModel = $dynamicFormsModel;
	}

	/**
	 * Custom template setter
	 */
	public function customTemplate(string $template = null): void
	{
		$this->templateFile = $template ?: __DIR__ . '/ContactFormControl.latte';
	}


	public function setFormNameFromTemplate(string $formNameFromTemplate): void
	{
		$this->formNameFromTemplate = $formNameFromTemplate;
	}


	public function setLanguage(string $language): void
	{
		$this->language = $language;
	}


	/**
	 * Add Contact form
	 */
	protected function createComponentContactForm(): \Nette\Application\UI\Form
	{
		$this->contactFormFactory->setLanguage($this->language);
		$form = $this->contactFormFactory->create(null, $this->formNameFromTemplate);
		$form->setTranslator($this->translator);
		$form->onSuccess[] = function ($form) {
			//$form->getPresenter()->flashMessage(SUCCESS_SAVE, FLASH_SUCCESS);
			$form->getPresenter()->redirect('this');
		};

		return $form;
	}

	/**
	 * Render function
	 */
	public function render($formNameFromTemplate = null): void
	{
		$this->customTemplate();
		$this->setFormNameFromTemplate($formNameFromTemplate);

		//todo: mozna nejak zjednodusit, dat na jedno misto
		$dynamicForm = $this->dynamicFormsModel->findByTemplateName($formNameFromTemplate)->fetch();
		if(!$dynamicForm)
		{
			echo $this->translator->translate("Dynamic form with name '%s' not exist.", $formNameFromTemplate);
			return;
		}
		$dynamicFormTranslation = $this->dynamicFormsModel->findTranslationBy($dynamicForm->dynamic_form_id, $this->language)->fetch();

		//to template
		$this->template->formTitle = $dynamicFormTranslation->title;

		$this->template->setFile($this->templateFile);

		$this->template->render();
	}

}