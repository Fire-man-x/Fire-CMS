<?php
declare(strict_types=1);

namespace App\DI;

interface IPluginServiceLocator
{
	public function get(string $name): object;
}
