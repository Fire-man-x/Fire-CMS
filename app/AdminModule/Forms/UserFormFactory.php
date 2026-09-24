<?php
declare(strict_types=1);

namespace App\AdminModule\Forms;

use App\AdminModule\SettingsModule\Presenters\UsersPresenter;
use App\Forms\BaseFormFactory;
use App\Model\Exceptions\DuplicateEmailException;
use App\Model\Exceptions\DuplicateNameException;
use App\Model;
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
use Nette\Utils\Random;


class UserFormFactory extends AdminFormFactory
{
	private int $userId;


	public function __construct(
		private Model\Users $users,
		private Model\UserManager $userManager,
		private Translator $translator,
		private Model\Roles $roles,
		private Request $httpRequest
	)
	{
		parent::__construct();
	}


	public function create(int|string $editId = null): AdminForm
	{
		/** @var AdminForm $form */
		$form = new AdminForm($editId);


		/*$group = $form->addGroup('Informations', true);
		$group->setOption('description', null);
		$group->add($form->data);*/
		$form->data->addCheckbox('active', 'Active');

		$form->data->addText('username', 'Username')
			->setRequired(VALIDATE_REQUIRED);

		$form->data->addText('email', 'Email')
			->setRequired(VALIDATE_REQUIRED);

		$form->data->addSelect('roleId', $this->translator->translate('Role in system'), $this->roles->getListWithName())
			->setTranslator(null);

		/*$group = $form->addGroup('Personal informations', true);
		$group->setOption('description', 'Nickname and name shown around the admin - not used to sign in.');
		$group->add($form->data);*/
		$form->data->addText('nickname', 'Nickname');

		$form->data->addText('firstName', 'First name');

		$form->data->addText('surname', 'Surname');

		$form->setCurrentGroup();
		$form->buttons->addSubmit('save', 'Save');

		if($this->isEditMode()){
			$values = $this->users->getById($this->getEditId());
			if($values) {
				$form->setDefaults($values);
			}
		}

		$form->onSuccess[] = array($this, 'formSucceeded');
		return $form;
	}

	public function formSucceeded(AdminForm $form, AdminFormValues $values): void
	{
		unset($values->editId);

		if($this->isEditMode()){
			try{
				$this->userManager->update($this->getEditId(), (array) $values->data);
			}catch(DuplicateEmailException|DuplicateNameException $e){
				$form->addError($e->getMessage());
			}
		}else{
			$username = $values->data->username;
			$email = $values->data->email;
			$password = Random::generate();
			unset($values->data->username, $values->data->email);
			$presenter = $form->getPresenter();
			if($presenter instanceof UsersPresenter) {
				try{
					$presenter->id = $this->userManager->add($username, $email, $password, $values->data);
				}catch(DuplicateEmailException|DuplicateNameException $e){
					$form->addError($e->getMessage());
				}
			}
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
			->addRule(Form::Equal, 'Passwords are not same', $form['new_password']);

		$form->addSubmit('send', 'Save');

		$form->onSuccess[] = array($this, 'formNewPasswordSucceeded');
		return $form;
	}


	public function formNewPasswordSucceeded($form, $values): void
	{
		try {
			$this->userManager->updatePassword($this->userId, $values->new_password);
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
		$findedUser = $this->users->findByName($values->username)->fetch();

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


	protected function generateResetUrl(int $userId): string {
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
