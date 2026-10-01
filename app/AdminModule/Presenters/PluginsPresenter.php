<?php
declare(strict_types=1);

namespace App\AdminModule\Presenters;

use App\Attributes\Privilege;
use App\Attributes\Resource;
use App\Attributes\Secured;
use App\Model\Plugin\PluginMigrator;
use App\Model\Plugin\PluginRepository;
use Contributte\Datagrid\Column\Action\Confirmation\CallbackConfirmation;
use Contributte\Datagrid\Datagrid;

/**
 * Plugins presenter - lists plugins found under app/Plugins and theme/Plugins
 * and lets an admin enable/disable them by rewriting theme/config/plugins.neon.
 */
#[Secured]
#[Resource('Plugins')]
#[Privilege('view')]
class PluginsPresenter extends BasePresenter
{

	/** @inject */
	public PluginRepository $pluginRepository;

	/** @inject */
	public PluginMigrator $pluginMigrator;


	public function startup(): void
	{
		parent::startup();

		$this->addBreadCrumbLink("Plugins", $this->link(":Admin:Plugins:default"));
	}


	/**
	 * Plugins grid
	 */
	protected function createComponentPluginsGrid(string $name): Datagrid
	{
		$source = array_map(
			static fn ($plugin) => [
				'id' => $plugin->id,
				'name' => $plugin->name,
				'location' => $plugin->location,
				'active' => (int) $plugin->active,
			],
			$this->pluginRepository->findAll(),
		);

		$grid = new Datagrid($this, $name);
		$grid->setPrimaryKey('id');
		$grid->setDataSource($source);
		$grid->setTranslator($this->translator);

		$grid->addColumnText('name', 'Name');

		$grid->addColumnText('location', 'Location');

		//active
		$activeColumn = $grid->addColumnStatus('active', 'Active');
		$activeColumn->getElementPrototype("th")->setTitle($this->translator->translate("Active"));
		$activeColumn->addOption(0, 'Disabled')
			->setClass('btn-danger')
			->setIcon('ban')
			->setTitle('Enable plugin')
			->setConfirmation(new CallbackConfirmation(function (array $item): string {
				return $this->getDisableConfirmation((string) $item['id']);
			}));
		$activeColumn->addOption(1, 'Enabled')
			->setClass('btn-success')
			->setIcon('check-circle')
			->setTitle('Disable plugin');
		$activeColumn->onChange[] = function ($id, $value) {
			$this->handleToggle((string) $id, (bool) $value);
		};

		return $grid;
	}


	/**
	 * Enable/disable handler - rewrites theme/config/plugins.neon
	 */
	#[Secured]
	#[Resource('Plugins')]
	#[Privilege('edit')]
	public function handleToggle(string $id, bool $active): void
	{
		if ($active) {
			$this->pluginRepository->activate($id);
			$this->runMigrations($id);
		} else {
			$this->pluginRepository->deactivate($id);
			$this->runDeactivateScript($id);
		}

		$this->redirect('this');
	}


	/**
	 * Confirmation text shown before disabling a plugin - empty (= no dialog,
	 * see column_status.latte) unless the plugin ships a data/deactivate.sql,
	 * i.e. disabling it runs that script and may destroy data.
	 */
	private function getDisableConfirmation(string $id): string
	{
		$plugin = $this->pluginRepository->find($id);
		if ($plugin === null || !$this->pluginMigrator->hasDeactivateScript($plugin)) {
			return '';
		}

		return sprintf(
			'Disabling "%s" will run its data/deactivate.sql cleanup script, which may permanently delete data. Continue?',
			$plugin->name,
		);
	}


	/**
	 * Runs the just-enabled plugin's pending migrations immediately, instead of
	 * waiting for the next DI container recompile to notice it (see
	 * App\Model\Plugin\PluginMigrator doc comment / docs/AI-Context/gotchas.md).
	 */
	private function runMigrations(string $id): void
	{
		$plugin = $this->pluginRepository->find($id);
		if ($plugin === null) {
			$this->flashMessage(SUCCESS_SAVE, FLASH_SUCCESS);
			return;
		}

		try {
			$executed = $this->pluginMigrator->migrate($plugin);
		} catch (\Throwable $e) {
			$this->flashMessage(FAIL_SAVE . ' (migrations: ' . $e->getMessage() . ')', FLASH_FAILED);
			return;
		}

		$this->flashMessage(
			$executed > 0 ? SUCCESS_SAVE . " ({$executed} migration(s) executed)" : SUCCESS_SAVE,
			FLASH_SUCCESS,
		);
	}


	/**
	 * Runs the just-disabled plugin's data/deactivate.sql, if it has one - the
	 * admin already confirmed this via getDisableConfirmation() before the
	 * request even got here.
	 */
	private function runDeactivateScript(string $id): void
	{
		$plugin = $this->pluginRepository->find($id);
		if ($plugin === null) {
			$this->flashMessage(SUCCESS_SAVE, FLASH_SUCCESS);
			return;
		}

		try {
			$ran = $this->pluginMigrator->deactivate($plugin);
		} catch (\Throwable $e) {
			$this->flashMessage(FAIL_SAVE . ' (deactivate.sql: ' . $e->getMessage() . ')', FLASH_FAILED);
			return;
		}

		$this->flashMessage(
			$ran ? SUCCESS_SAVE . ' (deactivate.sql executed)' : SUCCESS_SAVE,
			FLASH_SUCCESS,
		);
	}

}
