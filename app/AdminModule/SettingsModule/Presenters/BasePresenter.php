<?php
declare(strict_types=1);

namespace App\AdminModule\SettingsModule\Presenters;

use Nette\Application\Attributes\Persistent;

/**
 * Settings presenter.
 */
abstract class BasePresenter extends \App\AdminModule\Presenters\BasePresenter
{

	/**
	 * Id
	 */
	#[Persistent]
	public ?int $id = null;


	protected function startup(): void
	{
		parent::startup();

		$this->addBreadCrumbLink("Settings", $this->link(":Admin:Settings:Default:default", array("id" => null)));
	}

}
