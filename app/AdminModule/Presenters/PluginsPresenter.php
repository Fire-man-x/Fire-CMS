<?php
declare(strict_types=1);

namespace App\AdminModule\Presenters;

use App\Attributes\Privilege;
use App\Attributes\Resource;
use App\Attributes\Secured;
use App\Model\Plugin\PluginRepository;
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
			->setTitle('Enable plugin');
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
		} else {
			$this->pluginRepository->deactivate($id);
		}

		$this->flashMessage(SUCCESS_SAVE, FLASH_SUCCESS);
		$this->redirect('this');
	}

}
