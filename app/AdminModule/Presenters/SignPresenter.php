<?php
declare(strict_types=1);

namespace App\AdminModule\Presenters;

use App\Forms;
use App\Security\FacebookLogin;
use Nette;
use Nette\Application\Attributes\Persistent;


class SignPresenter extends BasePresenter
{
	/**
	 * Backlink
	 */
	#[Persistent]
	public string $backlink = '';

	/**  @inject */
	public Forms\SignInFormFactory $signInFactory;

	/** @inject */
	public Forms\SignUpFormFactory $signUpFactory;

	/** @inject */
	public FacebookLogin $facebookLogin;


	/**
	 * Sign-in form factory.
	 */
	protected function createComponentSignInForm(): Nette\Application\UI\Form
	{
		$form = $this->signInFactory->create(function () {
			//$this->flashMessage('Successfully logged in');
			$this->restoreRequest($this->backlink);
			$this->redirect('Default:');
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
			$this->redirect('Default:');
		});
		$form->setTranslator($this->translator);

		return $form;
	}


	public function actionOut(): void
	{
		$this->getUser()->logout(true);
	}


	/**
	 * Facebook redirect
	 */
	public function actionFacebook(): void
	{
		$this->facebookLogin->authenticate($this, $this->link('//:Admin:Sign:facebookAuthorize'));
	}


	/**
	 * Facebook authorize
	 */
	/**
	 * @return void
	 * @throws Nette\Application\AbortException
	 * @throws Nette\Application\UI\InvalidLinkException
	 * @throws Nette\Security\AuthenticationException
	 */
	public function actionFacebookAuthorize(): void
	{
		try
		{
			$identity = $this->facebookLogin->authorize(
				$this->link('//:Admin:Sign:facebookAuthorize'),
				$this->getHttpRequest()->getQuery()
			);
			$this->user->login($identity);
			$this->flashMessage('Successfully logged in');
			$this->redirect('Default:');
		}
		catch(Nette\Security\AuthenticationException $e)
		{
			$this->flashMessage($e->getMessage());
			$this->redirect('in');
		}
	}

}
