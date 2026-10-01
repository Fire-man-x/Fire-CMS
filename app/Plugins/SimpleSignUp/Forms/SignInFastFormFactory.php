<?php
declare(strict_types=1);

namespace App\Plugins\SimpleSignUp\Forms;

use App\Forms\FormFactory;
use App\Plugins\SimpleSignUp\SimpleAuthenticator;
use Nette;
use Nette\Application\UI\Form;
use Nette\Security\User;


class SignInFastFormFactory
{
	use Nette\SmartObject;

	private FormFactory $factory;

	private User $user;

	private SimpleAuthenticator $authenticator;


	public function __construct(FormFactory $factory, User $user, SimpleAuthenticator $authenticator)
	{
		$this->factory = $factory;
		$this->user = $user;
		$this->authenticator = $authenticator;
	}


	public function create(callable $onSuccess, callable $onError): Form
	{
		$form = $this->factory->create();

		$form->addPassword('password', 'Password')
			->setRequired('Please enter your password.');

		$form->addSubmit('send', 'Sign in');

		$form->onSuccess[] = function (Form $form, $values) use ($onSuccess) {
			try {
				$this->user->setAuthenticator($this->authenticator);
				$this->user->setExpiration('14 days');
				$this->user->login('guest', $values->password);
			} catch (Nette\Security\AuthenticationException $e) {
				$form->addError('The username or password you entered is incorrect.');
				return;
			}
			$onSuccess();
		};
		$form->onError[] = $onError;
		return $form;
	}

}
