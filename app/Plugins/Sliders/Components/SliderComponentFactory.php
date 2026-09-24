<?php
declare(strict_types=1);

namespace App\Plugins\Sliders\Components;

use App\DI\IPluginComponentFactory;
use Nette\Application\UI\Presenter;
use Nette\ComponentModel\IComponent;

class SliderComponentFactory implements IPluginComponentFactory
{
	public function __construct(private Slider $slider)
	{
	}


	public function createComponent(Presenter $presenter): IComponent
	{
		$this->slider->setLanguage($presenter->editLocale);

		return $this->slider;
	}
}
