<?php
declare(strict_types=1);

namespace App\AdminModule\SettingsModule\Presenters;

use App\Attributes\Privilege;
use App\Attributes\Resource;
use App\Attributes\Secured;
use App\Forms\UserFormFactory;
use App\Model\Users;
use Contributte\Datagrid\Datagrid;
use Nette;

/**
 * Users presenter.
 */
#[Secured]
#[Resource('Users')]
#[Privilege('view')]
class UsersPresenter extends BasePresenter
{
	/**
	 * @inject
	 */
	public Nette\Database\Explorer $database;

	/** @inject */
	public UserFormFactory $factory;

	/** @inject */
	public Users $users;


	public function startup(): void
	{
		parent::startup();

		$this->addBreadCrumbLink("Users", $this->link(":Admin:Settings:Users:default", array("id"=>null)));
	}


	public function actionDetail(): void
	{
		if($this->id) {
			$this->template->userInfo = $this->users->getById($this->id);
		}
	}

	public function actionPassword(): void
	{
		if($this->id) {
			$this->template->userInfo = $this->users->getById($this->id);
		}
	}


	/**
	 * Users grid
	 * @throws \LiveTranslator\TranslatorException
	 * @throws \Contributte\Datagrid\Exception\DatagridColumnStatusException
	 * @throws \Contributte\Datagrid\Exception\DatagridException
	 */
	protected function createComponentUsersGrid(): Datagrid
	{
		$source = $this->users->findAll()
			->select($this->users->getTableName().'.*')
			->select('role.title AS role_title')
			->order($this->users->getColumnId());
		$primaryKey = $this->users->getColumnId();

		$grid = new Datagrid();
		$grid->setPrimaryKey($primaryKey);
		$grid->setDataSource($source);
		$grid->setTranslator($this->translator);

		$active_column = $grid->addColumnStatus('active', 'A.');
		$active_column->getElementPrototype("th")->setTitle($this->translator->translate("Active"));
		$active_column->addOption(0, 'Unactive') // show if status == 0
		->setClass('btn-danger')
			->setIcon('ban')
			->setTitle('Set as active');
		$active_column->addOption(1, 'Active') // show if status == 1
		->setClass('btn-success')
			->setIcon('check-circle')
			->setTitle('Set as unactive');
		$active_column->onChange[] = function($id, $value) {
			$this->handleActivateUser((int) $id, (boolean) $value);
		};

		$grid->addColumnText("username", "Username");
		$grid->addColumnLink("role_title", "Role", "Roles:default", "role_title", array("id" => "role_id"));

		$grid->addColumnText("first_name", "First name");
		$grid->addColumnText("surname", "Surname");


		//Actions
		$grid->addAction('edit', 'Edit', 'detail', array('id' => $primaryKey))
			->setClass('btn btn-primary btn-sm')
			->setIcon(ICON_EDIT)
			->setTitle('Edit');
		$grid->allowRowsAction('edit', function(Nette\Database\Table\ActiveRow $item): bool {
			return $this->user->isAllowed('Users', 'edit', $this->user->getId());
		});


		$grid->addAction('delete', 'Delete', 'delete!', array('id' => $primaryKey))
			->setClass(function($item) {
				return 'btn btn-danger btn-sm ajax'.($item['id'] === 1 ? ' disabled' : '');
			})
			->setIcon(ICON_DELETE)
			->setTitle('Delete')
			->addAttributes(array(
				'data-bs-toggle' => 'modal',
				"data-bs-target" => "#confirm-modal",
				"data-confirm-text" => $this->translator->translate('Delete?')
			));
		$grid->allowRowsAction('delete', function(Nette\Database\Table\ActiveRow $item): bool {
			return $item['id'] !== 1 && $this->user->isAllowed('Users', 'delete', $this->user->getId());
		});

		return $grid;
	}


	/**
	 * Add user form
	 */
	protected function createComponentUserForm(): Nette\Application\UI\Form
	{
		if($this->id){
			$this->factory->setEditId($this->id);
		}

		$form = $this->factory->create();
		$form->setTranslator($this->translator);
		$form->onSuccess[] = function ($form) {
			$form->getPresenter()->flashMessage(SUCCESS_SAVE, FLASH_SUCCESS);
			$form->getPresenter()->redirect('this');
		};

		return $form;
	}


	/**
	 * Add new password form
	 */
	protected function createComponentUserNewPasswordForm(): Nette\Application\UI\Form
	{
		$form = $this->factory->createNewPassword($this->id);
		$form->setTranslator($this->translator);
		$form->onSuccess[] = function ($form) {
			$form->getPresenter()->flashMessage(SUCCESS_SAVE, FLASH_SUCCESS);
			$form->getPresenter()->redirect('this');
		};

		return $form;
	}


	#[Secured]
	#[Resource('Users')]
	#[Privilege('add')]
	public function handleAdd(): void
	{
		$this->factory->resetEditMode();
		$this->redrawControl("streetForm");
	}


	/**
	 * Delete handle
	 * @throws Nette\Application\AbortException
	 */
	#[Secured]
	#[Resource('Users')]
	#[Privilege('delete')]
	public function handleDelete(int $id): void
	{
		$this->users->delete($id);
		$this->flashMessage(SUCCESS_DELETE, FLASH_SUCCESS);
		$this->redirect('this');
	}


	/**
	 * Activate user
	 */
	#[Secured]
	#[Resource('Users')]
	#[Privilege('edit')]
	public function handleActivateUser(int $user_id, bool $status=false): void
	{
		$this->users->update($user_id, ["active"=>$status]);
		$this->flashMessage(SUCCESS_SAVE, FLASH_SUCCESS);
		$this->redirect('this');
	}

}
