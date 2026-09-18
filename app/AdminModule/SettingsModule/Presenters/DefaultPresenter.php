<?php
declare(strict_types=1);

namespace App\AdminModule\SettingsModule\Presenters;



use App\Attributes\Privilege;
use App\Attributes\Resource;
use App\Attributes\Secured;
use App\Forms\SettingFormFactory;

/**
 * Settings Default presenter.
 */
#[Secured]
#[Resource('Settings')]
#[Privilege('view')]
class DefaultPresenter extends BasePresenter
{

	/** @inject */
	public SettingFormFactory $factory;



	/**
	 * Setting form factory.
	 */
	protected function createComponentSettingForm(): \Nette\Application\UI\Form
	{
		if($this->id){
			$this->factory->setEditId($this->id);
		}

		$this->factory->asModal();
		$form = $this->factory->create();
		$form->setTranslator($this->translator);
		$form->onSuccess[] = function ($form) {
			$form->getPresenter()->flashMessage(SUCCESS_SAVE, FLASH_SUCCESS);
			$form->getPresenter()->redirect('this');
		};

		return $form;
	}
}
