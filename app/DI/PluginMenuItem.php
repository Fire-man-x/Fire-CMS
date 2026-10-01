<?php
declare(strict_types=1);

namespace App\DI;

/**
 * One entry in the admin "plugins" menu. Registered by a plugin's own config.plugin.neon
 * as a service tagged "presenter.menu"; collected via IPluginServiceLocator::getList().
 */
final class PluginMenuItem
{
	public function __construct(
		public string $title,
		public string $link,
		public ?string $resource = null,
	) {
	}
}
