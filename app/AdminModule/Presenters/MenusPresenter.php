<?php
declare(strict_types=1);

namespace App\AdminModule\Presenters;

use App\Attributes\Privilege;
use App\Attributes\Resource;
use App\Attributes\Secured;
use App\Components\Menu\Model\Menus;
use App\Forms\MenuFormFactory;
use App\Model;
use App\Service\LanguageService;
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

	/**
	 * Language
	 */
	#[Persistent]
	public ?string $language = null;

	/**
	 * Menu parent
	 */
	private ?int $parent = null;

	/**
	 * Actual language
	 */
	public ?string $actualLanguage = null;

	/** @inject */
	public MenuFormFactory $menuFactory;

	/** @inject */
	public LanguageService $languages;

	/** @inject */
	public Menus $menusModel;

	/** @inject */
	public Model\Categories $categoriesModel;


	protected function startup(): void
	{
		parent::startup();

		$this->addBreadCrumbLink("Menus", $this->link(":Admin:Menus:default", array("id" => null)) );

		//default language
		if($this->language == $this->languages->getDefaultLanguage()) {
			$this->redirect("this", array("language" => null));
		}


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
		$this->parent = $parent;

		if ($this->languages->existLanguage($this->language)) {
			$this->actualLanguage = $this->language == null ? $this->languages->getDefaultLanguage() : $this->language;
		} else {
			$this->actualLanguage = $this->languages->getDefaultLanguage();
		}

		$this->template->menuInfo = $this->menusModel->getById($this->id);

		//breadcrumb
		$this->addBreadCrumbLink($this->template->menuInfo['name'], $this->link(":Admin:Menus:detail", array("id" => $this->id)), null, false);
	}


	public function renderDetail(): void
	{
		$this->template->languages = $this->languages->getLanguages();
		$this->template->actualLanguage = $this->actualLanguage;
	}


	/**
	 * Menus grid
	 * @throws \LiveTranslator\TranslatorException
	 * @throws DatagridColumnStatusException
	 * @throws DatagridException
	 */
	protected function createComponentMenusGrid(string $name): Datagrid
	{
		$source = $this->menusModel->findAll()
			->order("name")
			->order("location");
		$primaryKey = $this->menusModel->getColumnId();

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

		$grid->addColumnText("name", "Name");

		$grid->addColumnText("location", "Template location");

		//Actions
		$grid->addAction('edit', 'Edit', 'edit!', array($primaryKey => $primaryKey))
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

		$grid->addAction('delete', 'Delete', 'delete!', array($primaryKey => $primaryKey))
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
	 * MenuItems grid
	 * @throws \LiveTranslator\TranslatorException
	 * @throws DatagridException
	 */
	protected function createComponentMenuItemsGrid(string $name): Datagrid
	{
		$source = $this->menusModel->getRelationMenuItems($this->id);
		$primaryKey = "category_id";
		$self = $this;

		$grid = new Datagrid($this, $name);
		$grid->setPrimaryKey($primaryKey);
		$grid->setDataSource($source);
		$grid->setTranslator($this->translator);
		$grid->setSortable();

		$grid->addColumnText("grid_name", "Name")
			->setRenderer(function ($row) {
				if (!$row['active']) {
					return '<span class="font-italic">' . $row['grid_name'] . '</span>';
				} else {
					return $row["grid_name"];
				}
			})
			->setTemplateEscaping(false);

		//Actions
		$grid->addAction('delete', 'Delete', 'removeCategory!', array($primaryKey => $primaryKey))
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
	 * @throws DatagridException
	 */
	protected function createComponentModalMenuItemsGrid(string $name): Datagrid
	{
		$source = $this->getMenuItemChildrens(null);
		//$source->setParentKey("parent_id");
		$primaryKey = $this->categoriesModel->getColumnId();

		$grid = new Datagrid($this, $name);
		$grid->setPrimaryKey($primaryKey);
		$grid->setDataSource($source);
		$grid->setTranslator($this->translator);

		//tree
		$grid->setTreeView([$this, 'getMenuItemChildrens'], 'parent_id');

		$grid->addColumnText("grid_name", "Name");

		//Actions
		$grid->addAction('add', 'Add', 'addCategory!', array($primaryKey => $primaryKey))
			->setClass('btn btn-primary btn-sm ajax float-right')
			->setIcon('plus')
			->setTitle('Add');

		return $grid;
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
		$form = $this->menuFactory->create($this->id, $this->actualLanguage);
		$form->setTranslator($this->translator);
		$form->onSuccess[] = function ($form) {
			$form->getPresenter()->flashMessage(SUCCESS_SAVE, FLASH_SUCCESS);
			$form->getPresenter()->redirect('this');
		};

		return $form;
	}

	public function getMenuItemChildrens(?int $parentId): Nette\Database\Table\Selection {
		$this->payload->parr = $parentId;
		$query = $this->categoriesModel->findAll()
			->select("*")
			->select("IFNULL(parent_id,0) AS parent_id")
			->where("history_id", null)
			->where("status IN (?)", array('publish','pending','draft'));
		if(isset($parentId))
		{
			$query->where("parent_id", $parentId);
		}

		return $query;
	}

	/**
	 * Sort handler
	 * @throws Nette\Application\AbortException
	 */
	#[Secured]
	#[Resource('Menus')]
	#[Privilege('edit')]
	public function handleSort(?int $item_id, ?int $prev_id, ?int $next_id): void
	{
		if(!$item_id){
			throw new Nette\InvalidArgumentException("Missing argumet item_id");
		}
		$this->menusModel->updatePositionOfMenuItem($this->id, $item_id, $prev_id, $next_id);

		$this->flashMessage(SUCCESS_SAVE, FLASH_SUCCESS);

		$this->redrawControl('flashes');
		$this->redrawControl("modalMenuItemsGrid");

		if(!$this->isAjax()){
			$this->redirect('this');
		}
	}


	/**
	 * Activate
	 * @throws Nette\Application\AbortException
	 */
	#[Secured]
	#[Resource('Menus')]
	#[Privilege('edit')]
	public function handleActivate(int $menu_id, bool $status): void
	{
		$this->menusModel->update($menu_id, array("active" => (boolean) $status));
		$this->flashMessage(SUCCESS_SAVE, FLASH_SUCCESS);
		$this->redirect('this');
	}


	/**
	 * Edit handler
	 */
	#[Secured]
	#[Resource('Menus')]
	#[Privilege('edit')]
	public function handleEdit(int $menu_id): void
	{
		$this->menuFactory->setEditId($menu_id);
		$this->menuFactory->setDefaultValues($this["menuForm"], $menu_id);
		$this->redrawControl("menuForm");
	}


	/**
	 * Delete handler
	 * @throws Nette\Application\AbortException
	 */
	#[Secured]
	#[Resource('Menus')]
	#[Privilege('delete')]
	public function handleDelete(int $menu_id): void
	{
		$this->menusModel->delete($menu_id);
		$this->flashMessage(SUCCESS_DELETE, FLASH_SUCCESS);

		$this->flashMessage(FAIL_DELETE, FLASH_FAILED);

		$this->redirect('this');
	}


	/**
	 * Validate URL handler
	 */
	public function handleValidateUrl($text): void
	{
		$this->payload->url = $this->menusModel->getUrl($text, $this->id);

		$this->sendPayload();
	}


	/**
	 * Add Images handler
	 */
	#[Secured]
	#[Resource('Menus')]
	#[Privilege('edit')]
	public function handleAddImage(array $files): void
	{
		foreach ($files as $fileId){
			$this->menusModel->insertRelationFile($this->id, $fileId);
		}

		$this->redrawControl("files");
	}


	/**
	 * Delete file handler
	 */
	#[Secured]
	#[Resource('Menus')]
	#[Privilege('edit')]
	public function handleRemoveImage($file_id): void
	{
		$this->menusModel->deleteRelationFile($this->id, $file_id);

		if($this->isAjax()){
			$this->redrawControl("files");
		} else {
			$this->redirect('this');
		}
	}


	/**
	 * Add Category handler
	 * @param $category_id
	 */
	#[Secured]
	#[Resource('Menus')]
	#[Privilege('add')]
	public function handleAddCategory($category_id): void
	{
		$this->menusModel->insertRelationMenuItem($this->id, $category_id);

		$this->flashMessage(SUCCESS_SAVE, FLASH_SUCCESS);
		$this->redrawControl("modalMenuItemsGrid");
	}


	/**
	 * Delete Category handler
	 * @throws Nette\Application\AbortException
	 */
	#[Secured]
	#[Resource('Menus')]
	#[Privilege('delete')]
	public function handleRemoveCategory(int $category_id): void
	{
		$this->menusModel->deleteRelationMenuItem($this->id, $category_id);

		$this->flashMessage(SUCCESS_DELETE, FLASH_SUCCESS);

		$this->redrawControl("flashes");
		$this->redrawControl("modalMenuItemsGrid");

		if(!$this->isAjax()){
			$this->redirect("this", array("id" => null));
		}
	}


	/**
	 * SetCategoryAsMain
	 * @throws Nette\Application\AbortException
	 */
	#[Secured]
	#[Resource('Menus')]
	#[Privilege('edit')]
	public function handleSetCategoryAsMain(int $category_id): void
	{
		$this->categoriesModel->setRelationMenuAsMain($category_id, $this->id);
		$this->flashMessage(SUCCESS_SAVE, FLASH_SUCCESS);

		if($this->isAjax()){
			$this->redrawControl("categories");
		} else {
			$this->redirect('this');
		}
	}

}
