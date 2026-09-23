<?php
declare(strict_types=1);

namespace App\Plugins\Sliders\Forms;

use App\Forms\BaseFormFactory;
use App\Forms\FormFactory;
use App\Model\Modules;
use App\Plugins\Sliders\Model\SliderItems;
use Nette\Application\UI\Form;

class SliderItemFormFactory extends BaseFormFactory
{
	private int $sliderId;

	private string $language;


	public function __construct(FormFactory $factory, private SliderItems $model)
	{
		parent::__construct($factory);
	}


	/**
	 * Slider setter
	 */
	public function setSliderId(int $sliderId): static
	{
		$this->sliderId = $sliderId;
		return $this;
	}


	/**
	 * Slider getter
	 */
	private function getSliderId(): int
	{
		if (!isset($this->sliderId)) {
			throw new \Nette\InvalidArgumentException("Slider is not setted.");
		}
		return $this->sliderId;
	}


	/**
	 * Language setter
	 */
	public function setLanguage(string $language): static
	{
		$this->language = $language;
		return $this;
	}


	/**
	 * Language getter
	 */
	private function getLanguage(): string
	{
		if (!isset($this->language)) {
			throw new \Nette\InvalidArgumentException("Language is not setted.");
		}
		return $this->language;
	}


	public function create(int|string $editId = null): Form
	{
		$form = parent::create($editId);

		$form->addText('url', 'URL')
			->setType("url");

		$form->addText('text', 'Text');

		$form->addHidden("languageId");

		$form->addSubmit('send', 'Save');

		$form->onSuccess[] = array($this, 'formSucceeded');
		return $form;
	}


	public function formSucceeded($form, $values): void
	{
		unset($values->editId);


		if ($this->isEditMode()) {
			$this->model->findById($this->getSliderId())
				->where("languageId", $values->languageId)
				->where("position", $this->getEditId())
				->update($values);
		} else {
			$values->sliderId = $this->getSliderId();

			$this->model->insert($values);
		}
	}


	/**
	 * Set default values to modal form
	 */
	public function setDefaultValues(Form $form, int $editId): void
	{
		$defaults = $this->model
			->findById($this->getSliderId())
			->where("languageId", $this->getLanguage())
			->where("position", $editId)
			->fetch();

		if ($defaults) {
			$form->setDefaults($defaults);
		}
	}

}
