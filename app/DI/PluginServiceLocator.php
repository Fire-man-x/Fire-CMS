<?php
declare(strict_types=1);

namespace App\DI;

use Nette\DI\Container;
use Nette\DI\MissingServiceException;

/**
 * Looks up plugin services tagged "presenter.plugin" by name (same contract the generated
 * Nette DI locator used to provide) and additionally enumerates admin menu entries tagged
 * "presenter.menu" via getList() - Nette's "implement + tagged" locator generator only
 * supports get($name)/create($name) style methods, never a "give me all of them" method,
 * so this is a hand-written replacement instead of a generated one.
 */
final class PluginServiceLocator implements IPluginServiceLocator
{
	private const ServiceTag = 'presenter.plugin';
	private const MenuTag = 'presenter.menu';


	public function __construct(private Container $container)
	{
	}


	public function get(string $name): object
	{
		foreach ($this->container->findByTag(self::ServiceTag) as $serviceName => $tagValue) {
			if ($tagValue === $name) {
				return $this->container->getService($serviceName);
			}
		}

		throw new MissingServiceException("Service '$name' is not defined.");
	}


	/**
	 * @return PluginMenuItem[]
	 */
	public function getList(): array
	{
		$items = [];
		foreach ($this->container->findByTag(self::MenuTag) as $serviceName => $tagValue) {
			$items[] = $this->container->getService($serviceName);
		}

		return $items;
	}
}
