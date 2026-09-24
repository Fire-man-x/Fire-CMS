<?php
declare(strict_types=1);

namespace App\AdminModule\Forms;

use App\Forms\BaseFormFactory;
use App\Forms\BaseFormPartFactory;
use Nette\SmartObject;

abstract class AdminFormFactory extends BaseFormFactory
{
	use SmartObject;


	/**
	 * Base form factory
	 */
	public function __construct()
	{
		parent::__construct();
	}


	protected function create(int|string|null $editId = null): AdminForm
	{
		if ($this->isModal() && !is_null($editId)) {
			throw new \InvalidArgumentException("Can not be 'modal' and set 'editId' in constructor.");
		}

		if(!is_null($editId)){
			$this->setEditId($editId);
		}

		$form = $this->factory->create();

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

		foreach ($this->formParts as $formPart){
			/* @var $formPart BaseFormPartFactory */
			$formPart->createPart($formPart);
		}

		return $form;
	}

}
