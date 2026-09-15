<?php
declare(strict_types=1);

namespace App\AdminModule\SettingsModule\Presenters;

use App\Forms\RoleFormFactory;
use App\Model\Roles;
use Contributte\Datagrid\Datagrid;
use Nette;

/**
 * Roles presenter.
 */
class RolesPresenter extends BasePresenter
{

	/** @inject */
	public RoleFormFactory $factory;

	/** @inject */
	public Roles $model;


	public function startup(): void
	{
		parent::startup();

		$this->addBreadCrumbLink("Roles", $this->link(":Admin:Settings:Roles:default", array("id"=>null)));
	}


	/**
	 * Roles grid
	 */
	protected function createComponentRolesGrid(string $name): Datagrid
	{
		$source = $this->model->findAll()->order("position");
		$primaryKey = $this->model->getColumnId();

		$grid = new Datagrid($this, $name);
		$grid->setPrimaryKey($primaryKey);
		$grid->setDataSource($source);
		$grid->setTranslator($this->translator);

		$grid->addColumnText("title", "Title")
			->setSortable()
			->setFilterText();
		$grid->addColumnText("name", "Role")
			->setSortable()
			->setFilterText();

		//Actions
		$grid->addAction('edit', 'Edit', 'edit!', array($primaryKey => $primaryKey))
			->setClass('btn btn-primary btn-sm ajax')
			->setIcon(ICON_EDIT)
			->setTitle('Edit')
			->addAttributes(array(
				"data-bs-toggle" => "modal",
				"data-bs-target" => "#modal"
			));

		$grid->addAction('editSettings', 'Settings', 'editRolePermission!', array($primaryKey => $primaryKey))
			->setClass('btn btn-primary btn-sm ajax')
			->setIcon('list')
			->setTitle('Settings')
			->addAttributes(array(
				"data-bs-toggle" => "modal",
				"data-bs-target" => "#modal-role-permission"
			));
		$grid->addAction('actionDelete', 'Delete', 'delete!', array($primaryKey => $primaryKey))
			->setClass(function($item) {
				return 'btn btn-danger btn-sm ajax'.($item->default || $item->name == "admin" ? ' disabled' : '');
			})
			->setIcon(ICON_DELETE)
			->setTitle('Delete')
			->addAttributes(array(
				'data-bs-toggle' => 'modal',
				"data-bs-target" => "#confirm-modal",
				"data-confirm-text" => $this->translator->translate('Delete?'),
			));
		$grid->allowRowsAction('actionDelete', function(Nette\Database\Table\ActiveRow $item): bool {
			return $item->default || $item->name == "admin";
		});

		/*$actions->onRender[] = function ($rowData, \Mesour\Datagrid\Column\Actions $actionColumns) {
			$actions = $actionColumns->getActions();
			/* @var $permissionButton \Mesour\Datagrid\Components\Button * /
			$permissionButton = $actions[1];
			/* @var $deleteButton \Mesour\Datagrid\Components\Button * /
			$deleteButton = $actions[2];
			if ($rowData["name"] == "admin") {
				$permissionButton->setDisabled();
			} else {
				$permissionButton->setDisabled(false);
			}
			if ($rowData["default"]) {
				$deleteButton->setDisabled();
			} else {
				$deleteButton->setDisabled(false);
			}
		};*/

		/*$grid->enableSorting();
		$model = $this->model;
		$grid->onSort[] = function ($data) use ($model) {
			$sort = 1;
			foreach($data as $item_id) {
				$model->update($item_id, array(
					'position' => $sort++
				));
			}
		};*/

		return $grid;
	}


	/**
	 * Add user form
	 */
	protected function createComponentRoleForm(): Nette\Application\UI\Form
	{
		$this->factory->asModal();
		$form = $this->factory->create();
		$form->setTranslator($this->translator);
		$form->onSuccess[] = function ($form) {
			$form->getPresenter()->flashMessage(SUCCESS_SAVE, FLASH_SUCCESS);
			$form->getPresenter()->redirect('this');
		};

		return $form;
	}


	/**
	 * Add user form
	 */
	protected function createComponentRoleModulesForm(): Nette\Application\UI\Form
	{
		$this->factory->asModal();
		$form = $this->factory->createModuleForm();
		$form->setTranslator($this->translator);
		$form->onSuccess[] = function ($form) {
			$form->getPresenter()->flashMessage(SUCCESS_SAVE, FLASH_SUCCESS);
			$form->getPresenter()->redirect('this');
		};

		return $form;
	}


	/**
	 * Add handler
	 */
	public function handleAdd(): void
	{
		$this->factory->resetEditMode();
		$this->redrawControl("roleForm");
	}


	/**
	 * Edit handler
	 */
	public function handleEdit(int $role_id): void
	{
		$this->factory->setEditId($role_id);
		/** @var Nette\Application\UI\Form $form */
		$form = $this["roleForm"];
		$this->factory->setDefaultValues($form, $role_id);
		$this->redrawControl("roleForm");
	}


	/**
	 * Delete handler
	 */
	public function handleDelete(int $role_id): void
	{
		if(!$this->model->getById($role_id)?->default){
			$this->model->delete($role_id);
			$this->flashMessage(SUCCESS_DELETE, FLASH_SUCCESS);
		}  else {
			$this->flashMessage(FAIL_DELETE, FLASH_FAILED);
		}
		$this->redirect('this');
	}


	/**
	 * Edit handler
	 */
	public function handleEditRolePermission(int $role_id): void
	{
		$this->factory->setEditId($role_id);
		/** @var Nette\Application\UI\Form $form */
		$form = $this["roleForm"];
		$this->factory->setModuleFormDefaultValues($form, $role_id);
		$this->redrawControl("roleModulesForm");
	}

}
