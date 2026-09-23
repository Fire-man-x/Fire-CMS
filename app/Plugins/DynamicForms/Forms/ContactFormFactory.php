<?php
declare(strict_types=1);

namespace App\Plugins\DynamicForms\Forms;

use App\Model\Settings;
use App\Modules\CommentsModule\Comment;
use App\Plugins\DynamicForms\Model\DynamicForms;
use App\Forms\BaseFormFactory;
use App\Forms\FormFactory;
use Nette\Application\UI\Form;
use Nette\Forms\Container;
use Nette\Mail\IMailer;
use Nette\Mail\Message;
use Nette\NotImplementedException;

//use ContactsModel;


class ContactFormFactory extends BaseFormFactory
{

	private DynamicForms $model;

	private Settings $modelOptions;

	private Comment $commentService;

	private IMailer $mailer;

	private string $language;


	/**
	 * ContactFormFactory constructor.
	 */
	public function __construct(FormFactory $factory, DynamicForms $model, Settings $modelOptions, Comment $commentService, IMailer $mailer)
	{
		parent::__construct($factory);
		$this->model = $model;
		$this->modelOptions = $modelOptions;
		$this->mailer = $mailer;
		$this->commentService = $commentService;
	}


	public function setLanguage(string $language): void
	{
		$this->language = $language;
	}


	public function create(int|string $editId = null, $formName = null): Form
	{
		$form = parent::create($editId);

		//language
		if($this->language == null)
		{
			throw new \InvalidArgumentException("Language is not set, use setLanguage() method.");
		}

		//after attached to presenter, if form is sended
		$form->onAnchor[] = function (Container $container) use ($formName, $form) {

			//try change form name from submited from
			if(!$formName && $form->isSubmitted())
			{
				$formName = $form->getHttpData($form::DATA_TEXT, 'formName');
			}

			//dynamic form
			$dynamicForm = $this->model->findByTemplateName($formName)->fetch();
			if(!$dynamicForm)
			{
				throw new \InvalidArgumentException("Dynamic form with name '$formName' not exist.");
			}

			//formName
			$form->addHidden('formName', $formName);

			//dynamic form items
			$items = $this->model->getItems($dynamicForm->id);
			foreach($items as $item)
			{
				$itemTranslation = $this->model->getItemTranslation($dynamicForm->id, $item['name'], $this->language);

				$control = null;
				$caption = null;
				if(isset($itemTranslation[$this->language]['items'][$item['name']]['label']))
				{
					$caption = $itemTranslation[$this->language]['items'][$item['name']]['label'];
				}
				//name
				switch($item['type'])
				{
					case 'textarea':
						$control = $form->addTextArea($item['name'], $caption);
						break;
					case 'email':
						$control = $form->addText($item['name'], $caption);
						$control->setType('email');
						$control->addRule(Form::EMAIL, VALIDATE_FORMAT);
						$control->setRequired(VALIDATE_REQUIRED);
						break;
					case 'input':
					default:
						$control = $form->addText($item['name'], $caption);
						break;
				}

				//required
				if($item['required'])
				{
					$control->setRequired(VALIDATE_REQUIRED);
				}

				//disable translator
				$control->setTranslator(null);
			}

			$form->addSubmit('send', 'Send');
		};

		//antispam
		$form->addAntiSpam("spamControl", 5, 60)
			->setOmitted(); //lockTime, resendTime

		$form->onSuccess[] = array($this, 'formSucceeded');
		return $form;
	}


	public function formSucceeded(Form $form, array $values): void
	{
		unset($values->editId);

		$dynamicForm = $this->model->findByTemplateName($values->formName)->select("*")->fetch();

		//todo: sjednotit na jedno misto
		$afterSendInformations = @unserialize($dynamicForm['afterSendInformations']);
		$afterSendInformations = isset($afterSendInformations['send_to']) ? $afterSendInformations['send_to'] : null;

		//create mail body
		$body = '';
		foreach($values as $inputName => $value)
		{
			if($inputName == 'formName')
			{
				continue;
			}

			$itemTranslation = $this->model->getItemTranslation($dynamicForm->id, $inputName, $this->language);
			if(isset($itemTranslation[$this->language]['items'][$inputName]['label']))
			{
				$caption = $itemTranslation[$this->language]['items'][$inputName]['label'];
				$body .= $caption.': '.$value."\n";
			}
		}

		$translation = $this->model->findTranslationBy($dynamicForm->id, $this->language)->fetch();
		if(isset($dynamicForm->whereToSend) && $dynamicForm->whereToSend == 'email')
		{
			$mainEmail = $this->modelOptions->getByKey('main_email', $this->language);

			$message = new Message();
			$message->setFrom($mainEmail);
			$message->addReplyTo($afterSendInformations);
			$message->addTo($afterSendInformations); //contains mail
			$message->setSubject($form->getTranslator()->translate('Informations from form'). ' '.$translation->title);
			$message->setHtmlBody(nl2br(trim($body)));

			$this->mailer->send($message);
		}

		if($translation->submitMessage)
		{
			$form->getPresenter()->flashMessage($translation->submitMessage, FLASH_SUCCESS);
		}


		//todo: dodelat ukladani do komentaru
		//$this->commentService->insert('contact_form', $dynamicForm->id, $values);
	}


	/**
	 * Set default values to modal form
	 */
	public function setDefaultValues(Form $form, int $editId): void
	{
		throw new NotImplementedException();

		parent::setDefaultValues($form, $editId);

		$defaults = $this->model->getById($editId)->fetch();

		$form->setDefaults($defaults);
	}

}
