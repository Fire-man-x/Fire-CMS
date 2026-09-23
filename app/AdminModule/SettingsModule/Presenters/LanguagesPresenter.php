<?php
declare(strict_types=1);

namespace App\AdminModule\SettingsModule\Presenters;

use App\Attributes\Privilege;
use App\Attributes\Resource;
use App\Attributes\Secured;
use App\Forms\LanguageFormFactory;
use App\Model\Languages;
use Contributte\Datagrid\Column\ColumnLink;
use Contributte\Datagrid\Datagrid;
use Nette;

/**
 * Languages presenter.
 */
#[Secured]
#[Resource('Languages')]
#[Privilege('view')]
class LanguagesPresenter extends BasePresenter
{

	/** @inject */
	public LanguageFormFactory $factory;

	/** @inject */
	public Languages $model;


	public function startup(): void
	{
		parent::startup();

		$this->addBreadCrumbLink("Languages", $this->link(":Admin:Settings:Languages:default", array("id"=>null)));
	}


	/**
	 * Languages grid
	 * @throws \LiveTranslator\TranslatorException
	 * @throws \Contributte\Datagrid\Exception\DatagridColumnStatusException
	 * @throws \Contributte\Datagrid\Exception\DatagridException
	 */
	protected function createComponentLanguagesGrid(): Datagrid
	{
		$source = $this->model->findAll()->order("default DESC")->order($this->model->getColumnId());
		$primaryKey = $this->model->getColumnId();
		$paramKey = $this->model->getForeignKeyColumn();

		$grid = new Datagrid();
		$grid->setPrimaryKey($primaryKey);
		$grid->setDataSource($source);
		$grid->setTranslator($this->translator);
		//$grid->setSortable();
		//$grid->onSort[] = function($data, $item_id){};

		//active
		$activeColumn = $grid->addColumnStatus('active', 'A.');
		//$activeColumn->getElementPrototype("th")->setTitle($this->translator->translate("Active"));
		$activeColumn->addOption(0, 'Unactive') // show if status == 0
			->setClass('btn-danger')
			->setIcon('ban')
			->setTitle('Set as active');
		$activeColumn->addOption(1, 'Active') // show if status == 1
			->setClass('btn-success')
			->setIcon('check-circle')
			->setTitle('Set as unactive');
		$activeColumn->onChange[] = function($id, $value) {
			$this->handleActivate((string) $id, (bool) $value);
		};
		// výchozí jazyk musí zůstat aktivní (LanguageService hledá výchozí jen mezi aktivními) - místo
		// přepínače jen text stavu (column_status.latte bez dropdownu); serverová kontrola je v handleActivate()
		$activeColumn->setRenderCondition(fn($item): bool => !$item->default);

		//default
		$grid->addColumnLink('default', 'D.', 'setDefault!', 'default', array($paramKey => $primaryKey))
			->setClass('btn btn-outline-primary btn-sm')
			->setIcon('ban') //default ban icon
			->setTitle($this->translator->translate('Set as default'))
			->getElementPrototype("th")->setTitle($this->translator->translate("Default"));
		//override default value
		$grid->addColumnCallback("default", function(ColumnLink $column, $data){
			if ($data->default == 1) {
				$column->setRenderer(function() {
					return '<span class="btn btn-outline-success btn-sm" title="'.$this->translator->translate('Default').'"><i class="fa fa-check-circle"></i></span>';
				});
				$column->setTemplateEscaping(false);
			}
			else
			{
				$column->setReplacement(array('0' => ''));
			}
		});

		//columns
		$grid->addColumnText("name", "Name");
		$grid->addColumnText("shortcut", "Shortcut");

		//Actions
		$grid->addAction('edit', 'Edit', 'edit!', array($paramKey => $primaryKey))
			->setClass('btn btn-primary btn-sm ajax')
			->setIcon(ICON_EDIT)
			->setTitle('Edit')
			->addAttributes(array(
				"data-bs-toggle" => "modal",
				"data-bs-target" => "#modal"
			));

		$grid->addAction('delete', 'Delete', 'delete!', array($paramKey => $primaryKey))
			->setClass(function($item) {
				return 'btn btn-danger btn-sm ajax'.($item->default ? ' disabled' : '');
			})
			->setIcon(ICON_DELETE)
			->setTitle('Delete')
			->addAttributes(array(
				'data-bs-toggle' => 'modal',
				"data-bs-target" => "#confirm-modal",
				"data-confirm-text" => $this->translator->translate('Delete?')
			));

		return $grid;
	}


	/**
	 * Language form factory.
	 */
	protected function createComponentLanguageForm(): Nette\Application\UI\Form
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
	 * Add handler
	 */
	public function handleAdd(): void
	{
		$this->factory->resetEditMode();
		$this->redrawControl("languageForm");
	}


	/**
	 * Edit handler
	 */
	public function handleEdit(int|string $languageId): void
	{
		$this->factory->setEditId($languageId);
		/** @var Nette\Application\UI\Form $form */
		$form = $this["languageForm"];
		$this->factory->setDefaultValues($form, $languageId);
		$this->redrawControl("languageForm");
	}


	/**
	 * Delete handler
	 */
	public function handleDelete(int|string $languageId): void
	{
		if(!$this->model->getById($languageId)?->default){
			$this->model->delete($languageId);
			$this->flashMessage(SUCCESS_DELETE, FLASH_SUCCESS);
		}  else {
			$this->flashMessage(FAIL_DELETE, FLASH_FAILED);
		}
		$this->redirect('this');
	}


	/**
	 * Activate
	 */
	public function handleActivate(string $languageId, bool $status = false): void
	{
		if (!$status && $this->model->getById($languageId)?->default) {
			$this->flashMessage('The default language cannot be deactivated.', FLASH_FAILED);
		} else {
			$this->model->update($languageId, array("active" => $status));
			$this->flashMessage(SUCCESS_SAVE, FLASH_SUCCESS);
		}

		if ($this->isAjax()) {
			$this->redrawControl('flashes');
			//$this['categoriesGrid']->setDataSource($this->categoryRepository->getAdminSelection('all'));
			$this['languagesGrid']->redrawItem($languageId, 'languageId');
		} else {
			$this->redirect('this');
		}
	}


	/**
	 * Activate
	 */
	public function handleSetDefault(string $languageId): void
	{
		$this->model->setDefault($languageId);
		$this->flashMessage(SUCCESS_SAVE, FLASH_SUCCESS);
		$this->redirect('this');
	}

}
