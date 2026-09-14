<?php declare(strict_types = 1);

namespace App\Router\DI;

use App\Router\RouterProvider;
use Nette\Application\Routers\RouteList;
use Nette\DI\CompilerExtension;
use Nette\DI\Definitions\Definition;
use Nette\DI\Definitions\ServiceDefinition;
use Nette\DI\Definitions\Statement;
use Nette\Schema\Expect;
use Nette\Schema\Schema;

final class RouterProviderExtension extends CompilerExtension
{

	/** Výchozí priorita routerů pluginů (App\Plugins\*) bez explicitního nastavení. */
	private const PluginPriority = 0;

	/** Výchozí priorita ostatních routerů bez explicitního nastavení. */
	private const DefaultPriority = -100;

	public function getConfigSchema(): Schema
	{
		return Expect::structure([
			// mapa  Trida\Routeru => priorita  (vyšší číslo = dřív = vyšší přednost)
			'priorities' => Expect::arrayOf(Expect::int(), Expect::string())->default([]),
		]);
	}

	public function loadConfiguration(): void
	{
		$builder = $this->getContainerBuilder();

		$builder->addDefinition($this->prefix('list'))
			->setFactory(RouteList::class);

		/*$builder->addDefinition($this->prefix('localised'))
			->setFactory(LocalisedRouter::class);

		$builder->addDefinition($this->prefix('domain'))
			->setFactory(DomainRouter::class);*/
	}

	public function beforeCompile(): void
	{
		$builder = $this->getContainerBuilder();

		/** @var ServiceDefinition $routerDef */
		$routerDef = $builder->getDefinition($this->prefix('list'));

		$routes = $builder->findByType(RouterProvider::class);

		// Routery se do RouteListu přidávají v pořadí podle priority (vyšší = dřív,
		// tedy vyšší přednost při hledání shody). Prioritu lze nastavit:
		//   1) tagem na službě:   tags: [router: [priority: 100]]
		//   2) v konfiguraci rozšíření:   router: priorities: { App\Router\AdminRouter: 100 }
		// Bez nastavení: routery pluginů (App\Plugins\*) = 0, ostatní = -100.
		// Při shodné prioritě se zachová pořadí registrace služeb.
		uasort($routes, fn (Definition $a, Definition $b): int
			=> $this->resolvePriority($b) <=> $this->resolvePriority($a));

		foreach ($routes as $route) {
			$routerDef->addSetup('add', [new Statement([$route, 'create'])]);
		}
	}

	private function resolvePriority(Definition $def): int
	{
		$tag = $def->getTag('router');
		if (is_array($tag) && isset($tag['priority'])) {
			return (int) $tag['priority'];
		}

		if (is_int($tag)) {
			return $tag;
		}

		$type = (string) $def->getType();
		$config = (array) $this->getConfig();
		$priorities = (array) ($config['priorities'] ?? []);
		if (isset($priorities[$type])) {
			return (int) $priorities[$type];
		}

		return str_starts_with($type, 'App\\Plugins\\')
			? self::PluginPriority
			: self::DefaultPriority;
	}

}
