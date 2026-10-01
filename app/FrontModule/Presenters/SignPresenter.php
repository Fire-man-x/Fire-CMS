<?php
declare(strict_types=1);

namespace App\FrontModule\Presenters;

use App\Forms;
use Nette;


class SignPresenter extends BasePresenter
{
	/** @inject */
	public Forms\SignInFormFactory $signInFactory;

	/** @inject */
	public Forms\SignUpFormFactory $signUpFactory;


	/**
	 * Sign-in form factory.
	 */
	protected function createComponentSignInForm(): Nette\Application\UI\Form
	{
		$form = $this->signInFactory->create(function () {
			$this->redirect('Homepage:default');
		});
		$form->setTranslator($this->translator);

		return $form;
	}


	/**
	 * Sign-up form factory.
	 */
	protected function createComponentSignUpForm(): Nette\Application\UI\Form
	{
		$form = $this->signUpFactory->create(function () {
			$this->flashMessage("Registration was successful", FLASH_SUCCESS);
			$this->redirect('Homepage:default');
		});
		$form->setTranslator($this->translator);

		return $form;
	}


	public function actionOut(): void
	{
		$this->getUser()->logout();
	}

}
