<?php
declare(strict_types=1);

namespace App\DI;

interface IPluginComponentLocator
{
	public function get(string $name): ?IPluginComponentFactory;
}
