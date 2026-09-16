<?php
declare(strict_types=1);

namespace App\DI;

use App\Model\Plugin\PluginRepository;
use Nette\Application\IPresenter;
use Nette\DI\CompilerExtension;

/**
 * Nette's built-in ApplicationExtension scans the whole app/ tree (via
 * RobotLoader) and registers every discovered presenter class as a DI service
 * so a later compiler pass (InjectExtension) can verify, at *compile* time,
 * that all of its "@inject" properties are autowirable. That scan has no
 * notion of theme/config/plugins.neon: a disabled plugin's presenter class is
 * still found on disk, its "@inject" properties can't be satisfied (the
 * plugin's own config.plugin.neon - and therefore its services - isn't
 * included), and the whole container fails to compile instead of just that
 * plugin's pages 404ing (which App\Application\PresenterFactory already
 * handles at routing time).
 *
 * This extension removes those disabled-plugin presenter definitions again
 * before InjectExtension gets to validate them. It must run after
 * ApplicationExtension (which adds the definitions) and before InjectExtension
 * (which validates them); Nette's Compiler always runs InjectExtension last
 * (see Nette\DI\Compiler::processExtensions()), so registering this as a
 * normal extension - after "application" in config.neon's `extensions:` - is
 * enough to guarantee the right order.
 */
final class PluginPresenterGateExtension extends CompilerExtension
{
	public function __construct(
		private readonly string $rootDir,
	) {
	}


	public function beforeCompile(): void
	{
		$builder = $this->getContainerBuilder();
		$pluginRepository = new PluginRepository($this->rootDir);

		foreach ($builder->findByType(IPresenter::class) as $name => $def) {
			$class = (string) $def->getType();
			if (preg_match('~\\\\Plugins\\\\([^\\\\]+)\\\\~', $class, $m) && !$pluginRepository->isActive($m[1])) {
				$builder->removeDefinition($name);
			}
		}
	}
}
