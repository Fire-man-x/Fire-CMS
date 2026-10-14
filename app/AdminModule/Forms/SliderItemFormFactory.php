<?php
declare(strict_types=1);

namespace App\AdminModule\Forms;

use App\Model\Database\SliderItems;
use Nette\Application\UI\Form;

class SliderItemFormFactory extends AdminFormFactory
{
	private int $sliderId;

	private string $language;


	public function __construct(private readonly SliderItems $model)
	{
		parent::__construct();
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


	public function create(int|string|null $editId = null): AdminForm
	{
		$form = parent::create($editId);

		$form->data->addText('url', 'URL')
			->setHtmlType("url");

		$form->data->addText('text', 'Text');

		$form->data->addHidden("languageId");

		$form->buttons->addSubmit('send', 'Save');

		$form->onSuccess[] = array($this, 'formSucceeded');
		return $form;
	}


	public function formSucceeded(AdminForm $form, AdminFormValues $values): void
	{
		unset($values->editId);


		if ($this->isEditMode()) {
			$this->model->findById($this->getSliderId())
				->where("languageId", $values->data['languageId'])
				->where("position", $this->getEditId())
				->update((array) $values->data);
		} else {
			$values->data['sliderId'] = $this->getSliderId();

			$this->model->insert($values->data);
		}
	}


	/**
	 * Set default values to modal form
	 */
	public function setDefaultValues(Form $form, int $editId): void
	{
		parent::setDefaultValues($form, $editId);

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
