<?php
declare(strict_types=1);

namespace App\Plugins\DynamicForms\Components;

use App\DI\IPluginComponentFactory;
use Nette\Application\UI\Presenter;
use Nette\ComponentModel\IComponent;

class ContactFormComponentFactory implements IPluginComponentFactory
{
	private ContactFormControl $control;


	public function __construct(ContactFormControl $control)
	{
		$this->control = $control;
	}


	public function createComponent(Presenter $presenter): IComponent
	{
		$this->control->setLanguage($presenter->editLocale);

		return $this->control;
	}
}
