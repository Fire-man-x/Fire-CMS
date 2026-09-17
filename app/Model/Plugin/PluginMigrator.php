<?php
declare(strict_types=1);

namespace App\Model\Plugin;

use Nette\Database\Explorer;
use Nette\Neon\Neon;
use Nextras\Migrations\Configurations\Configuration;
use Nextras\Migrations\Engine\Runner;
use Nextras\Migrations\Entities\Group;
use Nextras\Migrations\IConfiguration;
use Nextras\Migrations\IDriver;

/**
 * Runs a single plugin's migrations right when it gets enabled in the Plugins
 * admin, without waiting for the next DI container recompile to pick up its
 * config.plugin.neon (a plugin just added to theme/config/plugins.neon isn't
 * part of the *currently running* compiled container yet - see
 * docs/AI-Context/gotchas.md). Conversely, when a plugin gets disabled, runs
 * its `data/deactivate.sql` (if it ships one, see deactivate()) - a
 * destructive operation the Plugins admin gates behind a confirmation dialog.
 *
 * Migration discovery only understands the `migrations: groups: <name>:
 * {...}` NEON section style (used by DynamicForms and PetHotel). A plugin
 * that registers its group via a tagged Nextras\Migrations\Entities\Group
 * service instead (see docs/Architecture/plugins.md) isn't picked up here -
 * its migrations still need a manual `bin/console migrations:continue` after
 * enabling it.
 */
final class PluginMigrator
{
	public function __construct(
		private readonly string $rootDir,
		private readonly IDriver $driver,
		private readonly IConfiguration $configuration,
		private readonly Explorer $database,
	) {
	}


	public function hasMigrations(PluginInfo $plugin): bool
	{
		return $this->readGroups($plugin) !== [];
	}


	/**
	 * @return int number of migration files executed
	 * @throws \RuntimeException on migration failure
	 */
	public function migrate(PluginInfo $plugin): int
	{
		$pluginGroups = $this->readGroups($plugin);
		if (!$pluginGroups) {
			return 0;
		}

		$printer = new PluginMigrationPrinter();
		$config = new Configuration(
			[...$this->configuration->getGroups(), ...$pluginGroups],
			$this->configuration->getExtensionHandlers(),
		);

		$runner = new Runner($this->driver, $printer);
		$runner->run(Runner::MODE_CONTINUE, $config);

		if ($printer->getError() !== null) {
			throw new \RuntimeException($printer->getError());
		}

		return $printer->getExecutedCount();
	}


	/**
	 * Whether the plugin ships a data/deactivate.sql - i.e. whether disabling
	 * it is destructive (see deactivate()). Drives the Plugins admin's
	 * confirmation dialog.
	 */
	public function hasDeactivateScript(PluginInfo $plugin): bool
	{
		return is_file($this->getDeactivateScriptPath($plugin));
	}


	/**
	 * Runs the plugin's own data/deactivate.sql, e.g. `theme/Plugins/PetHotel/
	 * data/deactivate.sql` - a plain SQL file the plugin author ships,
	 * conventionally DROP TABLE statements for whatever its migrations
	 * created, but entirely up to the plugin (it may also clean up rows it
	 * seeded into shared core tables like `roles`/`modules`). Does nothing if
	 * the plugin doesn't have one.
	 *
	 * Also clears the plugin's own rows from the migrations bookkeeping
	 * table, so re-enabling it later re-runs its migrations from scratch
	 * instead of them being skipped as "already executed".
	 *
	 * @return bool whether a deactivate.sql was found and executed
	 * @throws \RuntimeException on a database error
	 */
	public function deactivate(PluginInfo $plugin): bool
	{
		$path = $this->getDeactivateScriptPath($plugin);
		if (!is_file($path)) {
			return false;
		}

		try {
			$this->driver->setupConnection();
			$this->driver->beginTransaction();
			$this->driver->loadFile($path);
			$this->driver->commitTransaction();
		} catch (\Throwable $e) {
			$this->driver->rollbackTransaction();
			throw new \RuntimeException($e->getMessage(), 0, $e);
		}

		foreach ($this->readGroups($plugin) as $group) {
			$this->database->getConnection()->query('DELETE FROM `migrations` WHERE `group` = ?', $group->name);
		}

		return true;
	}


	private function getDeactivateScriptPath(PluginInfo $plugin): string
	{
		return $this->rootDir . '/' . $plugin->location . '/' . $plugin->name . '/data/deactivate.sql';
	}


	/**
	 * @return list<Group>
	 */
	private function readGroups(PluginInfo $plugin): array
	{
		$configFile = $this->rootDir . '/' . $plugin->location . '/' . $plugin->name . '/config.plugin.neon';
		if (!is_file($configFile)) {
			return [];
		}

		$content = file_get_contents($configFile);
		if ($content === false) {
			return [];
		}

		$data = Neon::decode($content);
		if (!is_array($data) || !isset($data['migrations']) || !is_array($data['migrations'])) {
			return [];
		}

		$groupsConfig = $data['migrations']['groups'] ?? null;
		if (!is_array($groupsConfig)) {
			return [];
		}

		$groups = [];
		foreach ($groupsConfig as $name => $groupConfig) {
			if (!is_array($groupConfig) || !is_string($groupConfig['directory'] ?? null)) {
				continue;
			}

			if (($groupConfig['enabled'] ?? true) === false) {
				continue;
			}

			$dependencies = [];
			foreach ((array) ($groupConfig['dependencies'] ?? []) as $dependency) {
				if (is_string($dependency)) {
					$dependencies[] = $dependency;
				}
			}

			$group = new Group();
			$group->name = (string) $name;
			$group->enabled = true;
			$group->directory = $this->expandPath($groupConfig['directory']);
			$group->dependencies = $dependencies;
			$group->generator = null;
			$groups[] = $group;
		}

		return $groups;
	}


	private function expandPath(string $path): string
	{
		return strtr($path, [
			'%rootDir%' => $this->rootDir,
			'%appDir%' => $this->rootDir . '/app',
			'%wwwDir%' => $this->rootDir . '/www',
		]);
	}
}
