<?php
declare(strict_types=1);

namespace App\DI;

use Nette\Application\UI\Presenter;
use Nette\ComponentModel\IComponent;

interface IPluginComponentFactory
{
	public function createComponent(Presenter $presenter): IComponent;
}
