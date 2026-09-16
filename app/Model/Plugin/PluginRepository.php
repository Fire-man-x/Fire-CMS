<?php
declare(strict_types=1);

namespace App\Model\Plugin;

use Nette\Neon\Neon;

/**
 * Scans app/Plugins and theme/Plugins for installable plugins (any directory
 * containing a config.plugin.neon) and toggles them on/off by rewriting the
 * "includes" section of theme/config/plugins.neon - the same file
 * app/bootstrap.php loads into the DI container on every request.
 */
final class PluginRepository
{
	/** Directories (relative to %rootDir%) scanned for plugins, in display order. */
	private const PluginLocations = ['app/Plugins', 'theme/Plugins'];


	public function __construct(
		private readonly string $rootDir,
	) {
	}


	/**
	 * @return PluginInfo[]
	 */
	public function findAll(): array
	{
		$enabled = $this->readIncludes();
		$plugins = [];

		foreach (self::PluginLocations as $location) {
			foreach ($this->findPluginNames($location) as $name) {
				$plugins[] = new PluginInfo(
					name: $name,
					location: $location,
					active: in_array($this->toIncludePath($location, $name), $enabled, true),
				);
			}
		}

		return $plugins;
	}


	/**
	 * Whether a plugin of the given directory name is enabled in
	 * theme/config/plugins.neon, regardless of which location it lives in.
	 */
	public function isActive(string $name): bool
	{
		$enabled = $this->readIncludes();

		foreach (self::PluginLocations as $location) {
			if (in_array($this->toIncludePath($location, $name), $enabled, true)) {
				return true;
			}
		}

		return false;
	}


	public function activate(string $id): void
	{
		[$location, $name] = $this->parseId($id);
		$includePath = $this->toIncludePath($location, $name);
		$includes = $this->readIncludes();

		if (!in_array($includePath, $includes, true)) {
			$includes[] = $includePath;
			$this->writeIncludes($includes);
		}
	}


	public function deactivate(string $id): void
	{
		[$location, $name] = $this->parseId($id);
		$includePath = $this->toIncludePath($location, $name);
		$includes = $this->readIncludes();

		$filtered = array_values(array_filter(
			$includes,
			static fn (string $path): bool => $path !== $includePath,
		));

		if ($filtered !== $includes) {
			$this->writeIncludes($filtered);
		}
	}


	/**
	 * @return array{0: string, 1: string} [$location, $name]
	 */
	private function parseId(string $id): array
	{
		$parts = explode('::', $id, 2);
		if (count($parts) !== 2) {
			throw new \InvalidArgumentException("Invalid plugin id '$id'.");
		}

		return $parts;
	}


	/**
	 * @return string[] plugin directory names found under the given location
	 */
	private function findPluginNames(string $location): array
	{
		$absoluteDir = $this->rootDir . '/' . $location;
		if (!is_dir($absoluteDir)) {
			return [];
		}

		$names = [];
		foreach (glob($absoluteDir . '/*', GLOB_ONLYDIR) ?: [] as $directory) {
			if (is_file($directory . '/config.plugin.neon')) {
				$names[] = basename($directory);
			}
		}

		sort($names);

		return $names;
	}


	private function toIncludePath(string $location, string $name): string
	{
		return '%rootDir%/' . $location . '/' . $name . '/config.plugin.neon';
	}


	private function getPluginsConfigFile(): string
	{
		return $this->rootDir . '/theme/config/plugins.neon';
	}


	/**
	 * @return string[]
	 */
	private function readIncludes(): array
	{
		$file = $this->getPluginsConfigFile();
		if (!is_file($file)) {
			return [];
		}

		$data = Neon::decode(file_get_contents($file));

		return $data['includes'] ?? [];
	}


	/**
	 * @param string[] $includes
	 */
	private function writeIncludes(array $includes): void
	{
		$file = $this->getPluginsConfigFile();
		$data = is_file($file) ? Neon::decode(file_get_contents($file)) : [];
		$data['includes'] = $includes;

		file_put_contents($file, Neon::encode($data, Neon::BLOCK));
	}
}
