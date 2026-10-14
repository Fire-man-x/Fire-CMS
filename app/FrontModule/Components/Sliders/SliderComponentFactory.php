<?php
declare(strict_types=1);

namespace App\FrontModule\Components\Sliders;

use App\DI\IPluginComponentFactory;
use App\FrontModule\Presenters\BasePresenter;
use Nette\Application\UI\Presenter;
use Nette\ComponentModel\IComponent;

class SliderComponentFactory implements IPluginComponentFactory
{
	public function __construct(private Slider $slider)
	{
	}


	public function createComponent(Presenter $presenter): IComponent
	{
		if($presenter instanceof BasePresenter) {
			$this->slider->setLanguage($presenter->locale);
		}

		return $this->slider;
	}
}
