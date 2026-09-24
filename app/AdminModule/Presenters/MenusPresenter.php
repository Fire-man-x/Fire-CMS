<?php
declare(strict_types=1);

namespace App\AdminModule\Presenters;

use App\Attributes\Privilege;
use App\Attributes\Resource;
use App\Attributes\Secured;
use App\Components\Menu as MenuComponent;
use App\Components\Menu\Model\Menus;
use App\Forms\MenuFormFactory;
use App\Forms\MenuItemFormFactory;
use App\Model;
use Contributte\Datagrid\Datagrid;
use Contributte\Datagrid\Exception\DatagridColumnStatusException;
use Contributte\Datagrid\Exception\DatagridException;
use Nette;
use Nette\Application\Attributes\Persistent;


/**
 * Menus Presenter
 */
#[Secured]
#[Resource('Menus')]
#[Privilege('view')]
class MenusPresenter extends BasePresenter
{

	/**
	 * Id
	 */
	#[Persistent]
	public ?int $id = null;


	/** @inject */
	public MenuFormFactory $menuFactory;

	/** @inject */
	public Menus $menusModel;

	/** @inject */
	public Model\Categories $categoriesModel;

	/** @inject */
	public Model\Files $filesModel;

	/** @inject */
	public MenuComponent\Model\MenuItemTitles $menuItemTitles;

	/** @inject */
	public MenuItemFormFactory $menuItemFactory;


	protected function startup(): void
	{
		parent::startup();

		$this->addBreadCrumbLink("Menus", $this->link(":Admin:Menus:default", array("id" => null)) );

		\Vodacek\Forms\Controls\DateInput::register();

		$this->template->__imagestore = $this->fileManager;
	}


	/**
	 * @return void
	 * @throws Nette\Application\UI\InvalidLinkException
	 */
	#[Secured]
	#[Resource('Menus')]
	#[Privilege('edit')]
	public function actionDetail(?int $parent = null): void
	{
		$this->template->menuInfo = $this->menusModel->getById($this->id);
		if (!$this->template->menuInfo) {
			$this->error("Menu '$this->id' doesn't exist.");
		}
		$this->template->menuName = $this->menusModel->getDisplayName((int) $this->id, $this->editLocale);

		//breadcrumb
		$this->addBreadCrumbLink($this->template->menuName, $this->link(":Admin:Menus:detail", array("id" => $this->id)), null, false);
	}


	public function renderDetail(): void
	{
	}


	/**
	 * Menus grid
	 * @throws \LiveTranslator\TranslatorException
	 * @throws DatagridColumnStatusException
	 * @throws DatagridException
	 */
	protected function createComponentMenusGrid(string $name): Datagrid
	{
		// název = nadpis ve výchozím jazyce (firecms_menuDescriptions), menu bez nadpisu se pozná podle location
		// select() vypíná výchozí `*`, sloupce tabulky je proto nutné vybrat výslovně
		$source = $this->menusModel->findAll()
			->select("`" . $this->menusModel->getTableName() . "`.*");
		$this->menusModel->selectTitle($source, "`" . $this->menusModel->getTableName() . "`.`id`");
		$source->order("title")->order("location");
		$primaryKey = $this->menusModel->getColumnId();
		$paramKey = $this->menusModel->getForeignKeyColumn();

		$grid = new Datagrid($this, $name);
		$grid->setPrimaryKey($primaryKey);
		$grid->setDataSource($source);
		$grid->setTranslator($this->translator);

		//active
		$activeColumn = $grid->addColumnStatus('active', 'A.');
		$activeColumn->getElementPrototype("th")->setTitle($this->translator->translate("Active"));
		$activeColumn->addOption(0, 'Unactive') // show if status == 0
		->setClass('btn-danger')
			->setIcon('ban')
			->setTitle('Set as active');
		$activeColumn->addOption(1, 'Active') // show if status == 1
		->setClass('btn-success')
			->setIcon('check-circle')
			->setTitle('Set as unactive');
		$activeColumn->onChange[] = function($id, $value) {
			$this->handleActivate((int) $id, (bool) $value);
		};

		$grid->addColumnText("title", "Name")
			->setRenderer(fn($row): string => (string) ($row->title ?? $row->location));

		$grid->addColumnText("location", "Template location");

		//Actions
		$grid->addAction('edit', 'Edit', 'edit!', array($paramKey => $primaryKey))
			->setClass('btn btn-primary btn-sm ajax')
			->setIcon(ICON_EDIT)
			->setTitle('Edit')
			->addAttributes(array(
				"data-bs-toggle" => "modal",
				"data-bs-target" => "#modal"
			));

		$grid->addAction('items', 'Items', 'detail', array("id" => $primaryKey))
			->setClass('btn btn-primary btn-sm')
			->setIcon('list')
			->setTitle('Items');

		$grid->addAction('delete', 'Delete', 'delete!', array($paramKey => $primaryKey))
			->setClass('btn btn-danger btn-sm ajax')
			->setIcon(ICON_DELETE)
			->setTitle('Delete')
			->addAttributes(array(
				'data-bs-toggle' => 'modal',
				"data-bs-target" => "#confirm-modal",
				"data-confirm-text" => $this->translator->translate('Delete?'),
			));

		return $grid;
	}


	/**
	 * Položky menu - plochý seznam ve stromovém pořadí (podpoložky odsazené). Přetažením se mění pořadí
	 * mezi sourozenci (handleSort), rodič se mění ve formuláři položky.
	 * @throws DatagridException
	 */
	protected function createComponentMenuItemsGrid(string $name): Datagrid
	{
		$grid = new Datagrid($this, $name);
		$grid->setPrimaryKey('id');
		$grid->setDataSource($this->getMenuItemsGridRows());
		$grid->setTranslator($this->translator);
		$grid->setSortable();
		$grid->setPagination(false);

		$grid->addColumnText('title', 'Name')
			->setRenderer(function (array $row): Nette\Utils\Html {
				$title = Nette\Utils\Html::el('span')
					->setText(str_repeat('— ', $row['level']) . $row['title']);
				if (!$row['active']) {
					$title->class('font-italic text-muted');
				}
				return $title;
			});

		$grid->addColumnText('linkType', 'Link type')
			->setRenderer(fn(array $row): string => $this->translator->translate($row['linkTypeLabel']));

		$grid->addColumnText('target', 'Target');

		// náhledy obrázků položky (první = hlavní), přidání ze správce souborů, odebrání, řazení přetažením
		// makro n:src potřebuje $__imagestore - šablony presenteru ho mají (startup()), šablona sloupce gridu ne
		$grid->addColumnText('files', 'Images')
			->setTemplate(__DIR__ . '/../templates/Menus/itemImages.latte', ['__imagestore' => $this->fileManager]);

		//Actions
		$grid->addAction('edit', 'Edit', 'editItem!', ['itemId' => 'id'])
			->setClass('btn btn-primary btn-sm ajax')
			->setIcon(ICON_EDIT)
			->setTitle('Edit')
			->addAttributes([
				'data-bs-toggle' => 'modal',
				'data-bs-target' => '#modal',
			]);

		$grid->addAction('delete', 'Delete', 'deleteItem!', ['itemId' => 'id'])
			->setClass('btn btn-danger btn-sm ajax')
			->setIcon(ICON_DELETE)
			->setTitle('Delete')
			->addAttributes([
				'data-bs-toggle' => 'modal',
				'data-bs-target' => '#confirm-modal',
				'data-confirm-text' => $this->translator->translate('Delete the item including its sub-items?'),
			]);

		return $grid;
	}


	/**
	 * Řádky gridu položek: název = popisek v editovaném jazyce, jinak název kategorie/článku, jinak cíl
	 * @return list<array<string, mixed>>
	 */
	private function getMenuItemsGridRows(): array
	{
		$items = $this->menusModel->getItemsTree((int) $this->id, (string) $this->editLocale);

		$contentTitles = $this->menuItemTitles->getContentTitles($items, (string) $this->editLocale);
		$titles = $this->menuItemTitles->getTitles($items, (string) $this->editLocale);

		$files = $this->menusModel->getItemFiles(array_map(fn($item): int => $item->id, $items));

		$rows = [];
		foreach ($items as $item) {
			$linkType = $item->getLinkType();
			$contentTitle = $contentTitles[$item->linkType][(int) $item->target] ?? null;
			$rows[] = [
				'id' => $item->id,
				'level' => $item->level,
				'active' => $item->active,
				'title' => $titles[$item->id],
				'linkTypeLabel' => $linkType?->label() ?? $item->linkType,
				'target' => $linkType?->targetsContent()
					? ($contentTitle ?? $this->translator->translate('(deleted)')) . ' [#' . $item->target . ']'
					: $item->target,
				'files' => array_map(fn($file) => $this->filesModel->toFileEntity($file), $files[$item->id] ?? []),
			];
		}

		return $rows;
	}


	/**
	 * Formulář položky menu (modal)
	 */
	protected function createComponentMenuItemForm(): Nette\Application\UI\Form
	{
		$this->menuItemFactory->asModal();
		$form = $this->menuItemFactory->create(null, (int) $this->id, (string) $this->editLocale);
		$form->setTranslator($this->translator);
		$form->onSuccess[] = function (Nette\Application\UI\Form $form): void {
			$this->flashMessage(SUCCESS_SAVE, FLASH_SUCCESS);
			$this->redirect('this');
		};

		return $form;
	}


	/**
	 * Menu form factory.
	 */
	protected function createComponentMenuForm(): Nette\Application\UI\Form
	{
		if($this->id){
			$this->menuFactory->setEditId($this->id);
		}

		$this->menuFactory->asModal();
		$form = $this->menuFactory->create();
		$form->setTranslator($this->translator);
		$form->onSuccess[] = function ($form) {
			$form->getPresenter()->flashMessage(SUCCESS_SAVE, FLASH_SUCCESS);
			$form->getPresenter()->redirect('this');
		};

		return $form;
	}


	/**
	 * Sort handler (drag & drop v gridu položek) - jen mezi sourozenci, viz Menus::moveItem()
	 * @throws Nette\Application\AbortException
	 */
	#[Secured]
	#[Resource('Menus')]
	#[Privilege('edit')]
	public function handleSort(?int $item_id, ?int $prev_id = null, ?int $next_id = null): void
	{
		if(!$item_id){
			throw new Nette\InvalidArgumentException("Missing argumet item_id");
		}
		$this->assertItemOfThisMenu($item_id);
		$this->menusModel->moveItem($item_id, $prev_id, $next_id);

		$this->flashMessage(SUCCESS_SAVE, FLASH_SUCCESS);

		if($this->isAjax()){
			$this->redrawControl('flashes');
			$this->redrawControl('menuItemsGrid');
		} else {
			$this->redirect('this');
		}
	}


	/**
	 * Nová položka - prázdný formulář v modalu
	 */
	#[Secured]
	#[Resource('Menus')]
	#[Privilege('add')]
	public function handleAddItem(): void
	{
		$this->menuItemFactory->resetEditMode();
		$this->redrawControl('menuItemForm');
	}


	/**
	 * Úprava položky - formulář v modalu s hodnotami položky
	 */
	#[Secured]
	#[Resource('Menus')]
	#[Privilege('edit')]
	public function handleEditItem(int $itemId): void
	{
		$this->assertItemOfThisMenu($itemId);
		$this->menuItemFactory->setItemDefaults($this['menuItemForm'], $itemId, (string) $this->editLocale);
		$this->redrawControl('menuItemForm');
	}


	/**
	 * Smazání položky i s podpoložkami
	 * @throws Nette\Application\AbortException
	 */
	#[Secured]
	#[Resource('Menus')]
	#[Privilege('delete')]
	public function handleDeleteItem(int $itemId): void
	{
		$this->assertItemOfThisMenu($itemId);
		$this->menusModel->deleteItem($itemId);

		$this->flashMessage(SUCCESS_DELETE, FLASH_SUCCESS);
		$this->redirect('this');
	}


	/**
	 * Obrázky vybrané ve správci souborů pro položku (odkaz s data-selected-files-url, viz itemImages.latte)
	 * @param array<int|string> $files
	 */
	#[Secured]
	#[Resource('Menus')]
	#[Privilege('edit')]
	public function handleAddItemImages(int $itemId, array $files = []): void
	{
		$this->assertItemOfThisMenu($itemId);
		$this->menusModel->addItemFiles($itemId, array_values(array_map('intval', $files)));
		$this->redrawControl('menuItemsGrid');
	}


	#[Secured]
	#[Resource('Menus')]
	#[Privilege('edit')]
	public function handleRemoveItemImage(int $itemId, int $fileId): void
	{
		$this->assertItemOfThisMenu($itemId);
		$this->menusModel->removeItemFile($itemId, $fileId);
		if ($this->isAjax()) {
			$this->redrawControl('menuItemsGrid');
		} else {
			$this->redirect('this');
		}
	}


	/**
	 * Nové pořadí obrázků položky po přetažení (první = hlavní)
	 * @param array<int|string> $items
	 */
	#[Secured]
	#[Resource('Menus')]
	#[Privilege('edit')]
	public function handleSortItemImages(int $itemId, array $items = []): void
	{
		$this->assertItemOfThisMenu($itemId);
		$this->menusModel->sortItemFiles($itemId, array_values(array_map('intval', $items)));
		$this->redrawControl('menuItemsGrid');
	}


	/**
	 * Položka z URL musí patřit právě editovanému menu (ne jinému menu přes podvržené itemId)
	 * @throws Nette\Application\BadRequestException
	 */
	private function assertItemOfThisMenu(int $itemId): void
	{
		$item = $this->menusModel->getItem($itemId);
		if (!$item || $item->menuId !== $this->id) {
			$this->error("Menu item '$itemId' doesn't exist in this menu.");
		}
	}


	/**
	 * Activate
	 * @throws Nette\Application\AbortException
	 */
	#[Secured]
	#[Resource('Menus')]
	#[Privilege('edit')]
	public function handleActivate(int $menuId, bool $status): void
	{
		$this->menusModel->update($menuId, array("active" => (boolean) $status));
		$this->flashMessage(SUCCESS_SAVE, FLASH_SUCCESS);
		$this->redirect('this');
	}


	/**
	 * Edit handler
	 */
	#[Secured]
	#[Resource('Menus')]
	#[Privilege('edit')]
	public function handleEdit(int $menuId): void
	{
		$this->menuFactory->setEditId($menuId);
		$this->menuFactory->setDefaultValues($this["menuForm"], $menuId);
		$this->redrawControl("menuForm");
	}


	/**
	 * Delete handler
	 * @throws Nette\Application\AbortException
	 */
	#[Secured]
	#[Resource('Menus')]
	#[Privilege('delete')]
	public function handleDelete(int $menuId): void
	{
		$this->menusModel->delete($menuId);
		$this->flashMessage(SUCCESS_DELETE, FLASH_SUCCESS);

		$this->flashMessage(FAIL_DELETE, FLASH_FAILED);

		$this->redirect('this');
	}


}
