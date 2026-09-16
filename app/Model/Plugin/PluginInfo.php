<?php
declare(strict_types=1);

namespace App\Model\Plugin;

/**
 * Describes one plugin found on disk (under app/Plugins or theme/Plugins) and
 * whether it is currently included in theme/config/plugins.neon.
 */
final class PluginInfo
{
	/** Grid row id - unique across both plugin locations. */
	public readonly string $id;


	public function __construct(
		public readonly string $name,
		public readonly string $location,
		public readonly bool $active,
	) {
		$this->id = $location . '::' . $name;
	}
}
