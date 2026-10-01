<?php
declare(strict_types=1);

namespace App\FrontModule\Components\LoginLinkControl;

use Nette\Application\UI\Control;

/**
 * Class LoginLinkControl
 *
 * LoginLinkControl Component
 *
 */
class LoginLinkControl extends Control
{
	private string $templateFile;

	private \Nette\Localization\Translator $translator;


	/**
	 * LoginLinkControl Component
	 */
	public function __construct(\Nette\Localization\Translator $translator)
	{
		$this->translator = $translator;
	}

	public function customTemplate(string $template = null): void
	{
		$this->templateFile = $template ?: __DIR__ . '/LoginLinkControl.latte';
	}


	/**
	 * Render function
	 */
	public function render(): void
	{
		$this->customTemplate();

		$this->template->setFile($this->templateFile);
		$this->template->setTranslator($this->translator);

		$this->template->render();
	}

}
