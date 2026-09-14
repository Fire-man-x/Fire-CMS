<?php
declare(strict_types=1);

namespace App\Modules\UrlModule\AdminModule\SettingsModule;

use App\AdminModule\Presenters\BasePresenter;
use App\Modules\UrlModule\Forms\RedirectionFormFactory;
use App\Modules\UrlModule\Forms\UrlFormFactory;
use App\Modules\UrlModule\Model;
use App\Modules\UrlModule\RedirectionsModel;
use Contributte\Datagrid\Datagrid;
use Nette;
use Nette\Application\Attributes\Persistent;

/**
 * Class UrlsPresenter
 * @package App\AdminModule\SettingsModule\Presenters
 */
class UrlsPresenter extends BasePresenter
{
	/**
	 * Id
	 */
	#[Persistent]
	public ?int $id = null;

	/** @inject */
	public UrlFormFactory $factory;

	/** @inject */
	public RedirectionFormFactory $redirectionFormFactory;

	/** @inject */
	public Model $model;

	/** @inject */
	public RedirectionsModel $redirectionsModel;


	public function startup(): void
	{
		parent::startup();

		$this->addBreadCrumbLink("Urls", $this->link(":Admin:Settings:Urls:default", array("id"=>null)));
	}


	/**
	 * Urls grid
	 * @throws \LiveTranslator\TranslatorException
	 * @throws \Contributte\DataGrid\Exception\DataGridColumnStatusException
	 * @throws \Contributte\DataGrid\Exception\DataGridException
	 */
	protected function createComponentUrlsGrid(string $name): Datagrid
	{
		$source = $this->model->getAllForGrid();
		$primaryKey = $this->model->getColumnId();

		$grid = new Datagrid($this, $name);
		$grid->setPrimaryKey($primaryKey);
		$grid->setDataSource($source);
		$grid->setTranslator($this->translator);
		//$grid->setSortable();
		//$grid->onSort[] = function($data, $item_id){};


		//columns
		$grid->addColumnText("url", "Url")
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
	 * Redirections grid
	 * @throws \LiveTranslator\TranslatorException
	 * @throws \Contributte\DataGrid\Exception\DataGridColumnStatusException
	 * @throws \Contributte\DataGrid\Exception\DataGridException
	 */
	protected function createComponentRedirectionsGrid(string $name): Datagrid
	{
		$source = $this->redirectionsModel->getAllForGrid();
		$primaryKey = $this->redirectionsModel->getColumnId();

		$grid = new Datagrid($this, $name);
		$grid->setPrimaryKey($primaryKey);
		$grid->setDataSource($source);
		$grid->setTranslator($this->translator);
		//$grid->setSortable();
		//$grid->onSort[] = function($data, $item_id){};


		//columns
		$grid->addColumnText("old_url", "Old url")
			->setFilterText();
		$grid->addColumnText("new_url", "New url")
			->setFilterText();
		$grid->addColumnDateTime("last_usage_date", "Last usage date");

		//Actions
		$grid->addAction('edit', 'Edit', 'editRedirection!', array($primaryKey => $primaryKey))
			->setClass('btn btn-primary btn-sm ajax')
			->setIcon(ICON_EDIT)
			->setTitle('Edit')
			->addAttributes(array(
				"data-bs-toggle" => "modal",
				"data-bs-target" => "#modal"
			));

		$grid->addAction('delete', 'Delete', 'deleteRedirection!', array($primaryKey => $primaryKey))
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
	 * Url form factory.
	 */
	protected function createComponentUrlForm(): Nette\Application\UI\Form
	{
		if($this->id){
			$this->factory->setEditId($this->id);
		}

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
	 * Redirection form factory.
	 */
	protected function createComponentUrlRedirectionForm(): Nette\Application\UI\Form
	{
		if($this->id){
			$this->redirectionFormFactory->setEditId($this->id);
		}

		$this->redirectionFormFactory->asModal();
		$form = $this->redirectionFormFactory->create();
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
	public function handleAdd()
	{
		$this->factory->resetEditMode();
		$this->redrawControl("urlForm");
	}


	/**
	 * Edit handler
	 */
	public function handleEdit(int $url_id)
	{
		$this->factory->setEditId($url_id);
		$this->factory->setDefaultValues($this["urlForm"], $url_id);
		$this->redrawControl("urlForm");
	}


	/**
	 * Delete handler
	 */
	public function handleDelete(int $url_id)
	{
		if(!$this->model->findById($url_id)->fetch()->default){
			$this->model->delete($url_id);
			$this->flashMessage(SUCCESS_DELETE, FLASH_SUCCESS);
		}  else {
			$this->flashMessage(FAIL_DELETE, FLASH_FAILED);
		}
		$this->redirect('this');
	}


	/**
	 * Edit handler
	 */
	public function handleEditRedirection(int $url_redirection_id)
	{
		$this->redirectionFormFactory->setEditId($url_redirection_id);
		$this->redirectionFormFactory->setDefaultValues($this["urlRedirectionForm"], $url_redirection_id);
		$this->redrawControl("urlRedirectionForm");
	}


	/**
	 * Delete handler
	 * @throws Nette\Application\AbortException
	 */
	public function handleDeleteRedirection(int $url_redirection_id)
	{
		$this->redirectionsModel->delete($url_redirection_id);
		$this->flashMessage(SUCCESS_DELETE, FLASH_SUCCESS);

		$this->redirect('this');
	}

}
