<?php
declare(strict_types=1);

namespace App\Forms;

use App\Model;
use App\Model\Users;
use Latte\Engine;
use Nette\Application\UI\Form;
use Nette\Database\SqlLiteral;
use Nette\Forms\Rendering\TwitterBootstrapRenderer;
use Nette\Http\Request;
use Nette\Localization\Translator;
use Nette\Mail\Message;
use Nette\Mail\SendmailMailer;
use Nette\Mail\SmtpMailer;
use Nette\Security\AuthenticationException;
use Nette\Security\Passwords;
use Nette\Utils\ArrayHash;
use Nette\Utils\Random;


class UserFormFactory extends BaseFormFactory
{
	private int $userId;

	private Model\Users $users;

	private Model\UserManager $userManager;

	private Model\Roles $roles;

	private Translator $translator;

	private Request $httpRequest;

	private Passwords $passwords;


	public function __construct(FormFactory $factory, Model\Users $users, Model\UserManager $userManager, Translator $translator, Model\Roles $roles, Request $httpRequest, Passwords $passwords)
	{
		parent::__construct($factory);
		$this->users = $users;
		$this->userManager = $userManager;
		$this->translator = $translator;
		$this->roles = $roles;
		$this->httpRequest = $httpRequest;
		$this->passwords = $passwords;
	}

	public function create(int|string $editId = null): Form
	{
		$form = parent::create($editId);

		$form->addGroup("Informations");
		$form->addCheckbox('active', 'Active');

		$form->addText('username', 'Username')
			->setRequired(VALIDATE_REQUIRED);

		$form->addText('email', 'Email')
			->setRequired(VALIDATE_REQUIRED);

		$form->addSelect('roleId', $this->translator->translate('Role in system'), $this->roles->getListWithName())
			->setTranslator(null);

		$form->addGroup("Personal informations");
		$form->addText('nickname', 'Nickname');

		$form->addText('firstName', 'First name');

		$form->addText('surname', 'Surname');

		$form->setCurrentGroup();
		$form->addSubmit('save', 'Save');

		if($this->isEditMode()){
			$values = $this->users->getById($this->getEditId())?->toArray();
			$form->setDefaults($values);
		}

		$form->onSuccess[] = array($this, 'formSucceeded');
		return $form;
	}

	public function formSucceeded(Form $form, ArrayHash $values)
	{
		unset($values->editId);

		$values->nickname = "";

		if($this->isEditMode()){
			$this->users->update($this->getEditId(), (array) $values);
		}else{
			$username = $values->username;
			$email = $values->email;
			$password = Random::generate();
			unset($values->username, $values->email);
			$form->getPresenter()->id = $this->userManager->add($username, $email, $password, $values);
		}
	}


	public function createNewPassword(int $userId): Form
	{
		$this->userId = $userId;

		$form = new Form;
		$form->setRenderer(new TwitterBootstrapRenderer());

		$form->addPassword('old_password', 'Old password')
			->setRequired('Please enter your password.');

		$form->addPassword('new_password', 'New password')
			->setRequired('Please enter your password.');

		$form->addPassword('new_password_again', 'New password again')
			->setRequired('Please enter your password.')
			->addRule(Form::EQUAL, 'Passwords are not same', $form['new_password']);

		$form->addSubmit('send', 'Save');

		$form->onSuccess[] = array($this, 'formNewPasswordSucceeded');
		return $form;
	}


	public function formNewPasswordSucceeded($form, $values)
	{
		try {
			$this->users->findAll()
				->where("id",  $this->userId)
				->update(array(
						Users::COLUMN_PASSWORD_HASH => $this->passwords->hash($values->new_password)
				)
			);
		} catch (AuthenticationException $e) {
			$form->addError($e->getMessage());
		}
	}


	public function createRecoveryPassword(int $userId): Form
	{
		$this->userId = $userId;

		$form = new Form;
		$form->setRenderer(new TwitterBootstrapRenderer());

		$form->addText('username', 'Username')
			->setRequired(VALIDATE_REQUIRED)
			->setHtmlAttribute("autofocus");

		$form->addSubmit('send', 'Save');

		$form->onSuccess[] = array($this, 'formNewPasswordSucceeded');
		return $form;
	}


	public function formRecoveryPasswordSucceeded(Form $form, $values)
	{
		$findedUser = $this->users->findAll()->where("username", $values->username);

		if ($findedUser) {
			$email = $findedUser->email;

			$message = new Message();
			$message->setFrom($this->sender);
			$message->setSubject($this->subject);
			$message->addTo($email);
			if (is_file($this->templatePath)) {
				$latte = new Engine();
				$params = array(
					'url' => $this->generateResetUrl($email)
				);
				$message->setHtmlBody($latte->renderToString($this->templatePath, $params));
			} else {
				$message->setBody("Odkaz pro reset hesla: " . $this->generateResetUrl($email));
			}
			if ($this->smtp) {
				$mailer = new SmtpMailer(
					host: $this->smtp[0],
					username: $this->smtp[1],
					password: $this->smtp[2],
					encryption: isset($this->smtp[3]) ? $this->smtp[3] : '',
				);
				$mailer->send($message);
			} else {
				$mailer = new SendmailMailer();
				$mailer->send($message);
			}
		} else {
			$form->addError("User with username '$values->username' doesn't exist.");
		}


		/*
		try {
			$this->users->getAll()
				->where("id",  $this->userId)
				->update(array(
					Users::COLUMN_PASSWORD_HASH => Passwords::hash($values->new_password)
				)
			);
		} catch (AuthenticationException $e) {
			$form->addError($e->getMessage());
		}
		 */
	}


	/**
	 * @param $userId
	 */
	protected function generateResetUrl($userId): string {
		$baseUrl = $this->httpRequest->getUrl()->getHostUrl();
		$token = Random::generate(24);
		$signal = $this->link("this", array('token' => $token));
		$this->users->update($userId, array(
			'recoveryPasswordTime' => new SqlLiteral("NOW()"),
			'recoveryPasswordToken' => $token
		));
		return $baseUrl . $signal;
	}


	/**
	 * Set default values to modal form
	 */
	public function setDefaultValues(Form $form, int $editId)
	{
		throw new \InvalidArgumentException("Is not implemented yet.");
	}

}
