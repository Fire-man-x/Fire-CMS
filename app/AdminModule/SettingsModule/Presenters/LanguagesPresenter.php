<?php
declare(strict_types=1);

namespace App\AdminModule\SettingsModule\Presenters;

use App\Model\Languages;
use App\Forms\LanguageFormFactory;
use Contributte\Datagrid\Column\ColumnLink;
use Contributte\Datagrid\Datagrid;
use Nette;

/**
 * Languages presenter.
 */
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
		$source = $this->model->getAll()->order("default DESC")->order($this->model->getColumnId());
		$primaryKey = $this->model->getColumnId();

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
			$this->handleActivate($id, $value);
		};

		//default
		$grid->addColumnLink('default', 'D.', 'setDefault!', 'default', array($primaryKey => $primaryKey))
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
		$grid->addAction('edit', 'Edit', 'edit!', array($primaryKey => $primaryKey))
			->setClass('btn btn-primary btn-sm ajax')
			->setIcon(ICON_EDIT)
			->setTitle('Edit')
			->addAttributes(array(
				"data-bs-toggle" => "modal",
				"data-bs-target" => "#modal"
			));

		$grid->addAction('delete', 'Delete', 'delete!', array($primaryKey => $primaryKey))
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
	public function handleEdit(int $language_id): void
	{
		$this->factory->setEditId($language_id);
		$this->factory->setDefaultValues($this["languageForm"], $language_id);
		$this->redrawControl("languageForm");
	}


	/**
	 * Delete handler
	 */
	public function handleDelete(int $language_id): void
	{
		if(!$this->model->findById($language_id)->fetch()->default){
			$this->model->delete($language_id);
			$this->flashMessage(SUCCESS_DELETE, FLASH_SUCCESS);
		}  else {
			$this->flashMessage(FAIL_DELETE, FLASH_FAILED);
		}
		$this->redirect('this');
	}


	/**
	 * Activate
	 * @param int $language_id
	 * @param boolean $status
	 */
	public function handleActivate($language_id, $status = 0): void
	{
		$this->model->update($language_id, array("active" => (boolean) $status));
		$this->flashMessage(SUCCESS_SAVE, FLASH_SUCCESS);

		if ($this->isAjax()) {
			$this->redrawControl('flashes');
			//$this['categoriesGrid']->setDataSource($this->categoryRepository->getAdminSelection('all'));
			$this['languagesGrid']->redrawItem($language_id, 'language_id');
		} else {
			$this->redirect('this');
		}
	}


	/**
	 * Activate
	 */
	public function handleSetDefault(int $language_id): void
	{
		$this->model->getAll()->update(array("default" => false));
		$this->model->update($language_id, array("default" => true));
		$this->flashMessage(SUCCESS_SAVE, FLASH_SUCCESS);
		$this->redirect('this');
	}

}
