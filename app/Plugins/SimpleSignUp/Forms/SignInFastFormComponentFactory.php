<?php
declare(strict_types=1);

namespace App\Plugins\SimpleSignUp\Forms;

use App\DI\IPluginComponentFactory;
use Nette\Application\UI\Presenter;
use Nette\ComponentModel\IComponent;
use Nette\Localization\Translator;

class SignInFastFormComponentFactory implements IPluginComponentFactory
{
	private SignInFastFormFactory $factory;

	private Translator $translator;


	public function __construct(SignInFastFormFactory $factory, Translator $translator)
	{
		$this->factory = $factory;
		$this->translator = $translator;
	}


	public function createComponent(Presenter $presenter): IComponent
	{
		$form = $this->factory->create(
			function () use ($presenter) {
				$presenter->redirectUrl('/fotogalerie');
			},
			function () use ($presenter) {
				$presenter->redirectUrl('/fotogalerie');
			}
		);
		$form->setTranslator($this->translator);

		return $form;
	}
}
