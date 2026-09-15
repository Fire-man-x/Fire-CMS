<?php
declare(strict_types=1);

namespace App\DI;

interface IPluginServiceLocator
{
	public function get(string $name): object;

	/**
	 * Admin menu entries registered by currently active plugins (services tagged "presenter.menu").
	 * @return PluginMenuItem[]
	 */
	public function getList(): array;
}
