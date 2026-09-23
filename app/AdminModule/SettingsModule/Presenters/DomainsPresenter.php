<?php
declare(strict_types=1);

namespace App\AdminModule\SettingsModule\Presenters;

use App\Attributes\Privilege;
use App\Attributes\Resource;
use App\Attributes\Secured;
use App\Forms\DomainFormFactory;
use App\Model\Domains;
use App\Model\Languages;
use Contributte\Datagrid\Datagrid;
use Nette;

/**
 * Domains presenter.
 *
 * Správa domén přiřazených jazykovým mutacím - viz App\Service\DomainService a
 * App\Router\CustomRouter, které podle nich rozhodují o routování.
 */
#[Secured]
#[Resource('Domains')]
#[Privilege('view')]
class DomainsPresenter extends BasePresenter
{

	/** @inject */
	public DomainFormFactory $factory;

	/** @inject */
	public Domains $model;

	/** @inject */
	public Languages $languagesModel;


	public function startup(): void
	{
		parent::startup();

		$this->addBreadCrumbLink("Domains", $this->link(":Admin:Settings:Domains:default", array("id" => null)));
	}


	/**
	 * Domains grid
	 * @throws \LiveTranslator\TranslatorException
	 * @throws \Contributte\Datagrid\Exception\DatagridColumnStatusException
	 * @throws \Contributte\Datagrid\Exception\DatagridException
	 */
	protected function createComponentDomainsGrid(): Datagrid
	{
		$source = $this->model->findAll()->order('languageId')->order('default DESC')->order('domain');
		$primaryKey = $this->model->getColumnId();
		$paramKey = $this->model->getForeignKeyColumn();

		$grid = new Datagrid();
		$grid->setPrimaryKey($primaryKey);
		$grid->setDataSource($source);
		$grid->setTranslator($this->translator);

		//active
		$activeColumn = $grid->addColumnStatus('active', 'A.');
		$activeColumn->addOption(0, 'Unactive')
			->setClass('btn-danger')
			->setIcon('ban')
			->setTitle('Set as active');
		$activeColumn->addOption(1, 'Active')
			->setClass('btn-success')
			->setIcon('check-circle')
			->setTitle('Set as unactive');
		$activeColumn->onChange[] = function ($id, $value) {
			$this->handleActivate((int) $id, (bool) $value);
		};

		//default (canonical domain per language)
		$grid->addColumnLink('default', 'D.', 'setDefault!', 'default', array($paramKey => $primaryKey))
			->setClass('btn btn-outline-primary btn-sm')
			->setIcon('ban')
			->setTitle($this->translator->translate('Set as default'))
			->getElementPrototype("th")->setTitle($this->translator->translate("Default"));
		$grid->addColumnCallback("default", function (\Contributte\Datagrid\Column\ColumnLink $column, $data) {
			if ($data->default == 1) {
				$column->setRenderer(function () {
					return '<span class="btn btn-outline-success btn-sm" title="' . $this->translator->translate('Default') . '"><i class="fa fa-check-circle"></i></span>';
				});
				$column->setTemplateEscaping(false);
			} else {
				$column->setReplacement(array('0' => ''));
			}
		});

		//columns
		$grid->addColumnText("domain", "Domain");
		$grid->addColumnText("languageId", "Language")
			->setReplacement($this->languagesModel->findAll()->fetchPairs('languageId', 'name'));

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
			->setClass('btn btn-danger btn-sm ajax')
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
	 * Domain form factory.
	 */
	protected function createComponentDomainForm(): Nette\Application\UI\Form
	{
		if ($this->id) {
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
		$this->redrawControl("domainForm");
	}


	/**
	 * Edit handler
	 */
	public function handleEdit(int $domainId): void
	{
		$this->factory->setEditId($domainId);
		/** @var Nette\Application\UI\Form $form */
		$form = $this["domainForm"];
		$this->factory->setDefaultValues($form, $domainId);
		$this->redrawControl("domainForm");
	}


	/**
	 * Delete handler
	 */
	public function handleDelete(int $domainId): void
	{
		$this->model->delete($domainId);
		$this->flashMessage(SUCCESS_DELETE, FLASH_SUCCESS);
		$this->redirect('this');
	}


	/**
	 * Activate
	 */
	public function handleActivate(int $domainId, bool $status = false): void
	{
		$this->model->update($domainId, array("active" => $status));
		$this->flashMessage(SUCCESS_SAVE, FLASH_SUCCESS);

		if ($this->isAjax()) {
			$this->redrawControl('flashes');
			$this['domainsGrid']->redrawItem($domainId, 'id');
		} else {
			$this->redirect('this');
		}
	}


	/**
	 * Set as default (canonical) domain within its language
	 */
	public function handleSetDefault(int $domainId): void
	{
		$domain = $this->model->getById($domainId);
		if ($domain) {
			$this->model->findAll()->where('languageId', $domain->languageId)->update(array("default" => false));
			$this->model->update($domainId, array("default" => true));
			$this->flashMessage(SUCCESS_SAVE, FLASH_SUCCESS);
		}
		$this->redirect('this');
	}

}
