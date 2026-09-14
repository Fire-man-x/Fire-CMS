<?php
declare(strict_types=1);

namespace App\Forms;

use App\Model;
use Nette;
use Nette\Application\UI\Form;


class SignUpFormFactory
{
	use Nette\SmartObject;

	const PASSWORD_MIN_LENGTH = 7;

	private FormFactory $factory;

	private Model\UserManager $userManager;


	public function __construct(FormFactory $factory, Model\UserManager $userManager)
	{
		$this->factory = $factory;
		$this->userManager = $userManager;
	}


	public function create(callable $onSuccess): Form
	{
		$form = $this->factory->create();
		$form->addText('username', 'Pick a username')
			->setRequired('Please pick a username.')
			->setHtmlAttribute("autofocus");

		$form->addText('email', 'Your e-mail')
			->setType("email")
			->setRequired('Please enter your e-mail.')
			->addRule($form::EMAIL);

		$form->addPassword('password', 'Create a password')
			->setOption('description', sprintf('at least %d characters', self::PASSWORD_MIN_LENGTH))
			->setRequired('Please create a password.')
			->addRule($form::MIN_LENGTH, null, self::PASSWORD_MIN_LENGTH);

		$form->addSubmit('send', 'Sign up');

		$form->onSuccess[] = function (Form $form, $values) use ($onSuccess) {
			try {
				$this->userManager->add($values->username, $values->email, $values->password, \Nette\Utils\ArrayHash::from(array(
					"active" => false,
					"first_name" => "",
					"surname" => ""
				)));
			} catch (Model\DuplicateNameException $e) {
				$form->addError('Username is already taken.');
				return;
			}
			$onSuccess();
		};
		return $form;
	}

}
