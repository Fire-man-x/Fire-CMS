<?php
declare(strict_types=1);

namespace App\Forms;

use Nette\Application\UI\Form;
use Nette\Forms\Rendering\TwitterBootstrapRenderer;
use Nette\SmartObject;

abstract class BaseFormPartFactory
{
	use SmartObject;

	private Form $form;


	/**
	 * Base form
	 */
	public function __construct(Form $form)
	{
		$this->form = $form;
	}


	protected function createPart($containerName, $editId = null): Form
	{
		if ($this->isModal() && !is_null($editId)) {
			throw new \InvalidArgumentException("Can not be 'modal' and setted 'editId'.");
		}

		if(!is_null($editId)){
			$this->setEditId($editId);
		}

		$form = new Form;
		$form->setRenderer(new TwitterBootstrapRenderer());

		if ($this->isModal()) {
			$form->addHidden("editId", $this->getEditId());
			$self = $this;
			$form->onValidate[] = function ($form, $values) use ($self) {
				if(!empty($values->editId)){
					$self->setEditId($values->editId);
				}
				unset($values->editId);
			};
		}

		return $form;
	}


	/**
	 * Set default values to modal form
	 */
	public function setDefaultValues(Form $form, int $editId)
	{
		if (!$this->isModal()) {
			throw new \InvalidArgumentException("Can not use in non 'modal' mode.");
		}
	}
}
