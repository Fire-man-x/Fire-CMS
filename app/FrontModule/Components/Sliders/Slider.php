<?php
declare(strict_types=1);

namespace App\FrontModule\Components\Sliders;

use App\Components\BaseControl;
use App\Components\FileManager\FileManager;
use App\Components\FileManager\TPresenter;
use App\Model\Database\Files;
use App\Plugins\Sliders\Model;

/**
 * Class SliderControl
 */
class Slider extends BaseControl
{
	use TPresenter;

	private \App\Model\Database\Sliders $modelSliders;

	private \App\Model\Database\SliderItems $modelSliderItems;

	private Files $modelFiles;

	private string $language;

	private ?string $templateFile = null;


	/**
	 * Sliders
	 */
	public function __construct(FileManager $fileManager, \App\Model\Database\Sliders $modelSliders, \App\Model\Database\SliderItems $modelSliderItems, Files $modelFiles)//, \Nette\Localization\Translator $translator)
	{
		parent::__construct($fileManager);
		$this->modelSliders = $modelSliders;
		$this->modelSliderItems = $modelSliderItems;
		$this->modelFiles = $modelFiles;
	}


	public function setLanguage($language)
	{
		$this->language = $language;
		return $this;
	}



	public function customTemplate($template): void
	{
		$this->templateFile = $template?$template:__DIR__ . '/Slider.latte';
	}

	/**
	 * Render function
	 */
	public function render($location): void
	{
		$this->customTemplate($this->templateFile);

		$this->template->setFile($this->templateFile);

		$slider = $this->modelSliders->findAll()->where("location", $location)->fetch();
		if(!$slider){
			throw new \Nette\InvalidArgumentException("Slider with location '$location' doesn't exist.");
		}

		$this->template->slider = $slider;
		$sliderItems = $this->modelSliderItems->findById($slider->id)
			->select($this->modelSliderItems->getTableName().".*")
			->select("file.*")
			->where("languageId", $this->language)
			->order("position")
			->fetchAll();
		foreach ($sliderItems as &$item){
			$item->file = $this->modelFiles->toFileEntity($item);
		}

		$this->template->sliderItems = $sliderItems;
		$this->template->render();
	}

}