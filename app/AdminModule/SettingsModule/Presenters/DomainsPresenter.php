<?php
declare(strict_types=1);

namespace App\AdminModule\SettingsModule\Presenters;

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
		$source = $this->model->findAll()->order('language_id')->order('default DESC')->order('domain');
		$primaryKey = $this->model->getColumnId();

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
			$this->handleActivate($id, $value);
		};

		//default (canonical domain per language)
		$grid->addColumnLink('default', 'D.', 'setDefault!', 'default', array($primaryKey => $primaryKey))
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
		$grid->addColumnText("language_id", "Language")
			->setReplacement($this->languagesModel->findAll()->fetchPairs('language_id', 'name'));

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
	public function handleEdit(int $domain_id): void
	{
		$this->factory->setEditId($domain_id);
		/** @var Nette\Application\UI\Form $form */
		$form = $this["domainForm"];
		$this->factory->setDefaultValues($form, $domain_id);
		$this->redrawControl("domainForm");
	}


	/**
	 * Delete handler
	 */
	public function handleDelete(int $domain_id): void
	{
		$this->model->delete($domain_id);
		$this->flashMessage(SUCCESS_DELETE, FLASH_SUCCESS);
		$this->redirect('this');
	}


	/**
	 * Activate
	 */
	public function handleActivate(int $domain_id, bool $status = false): void
	{
		$this->model->update($domain_id, array("active" => $status));
		$this->flashMessage(SUCCESS_SAVE, FLASH_SUCCESS);

		if ($this->isAjax()) {
			$this->redrawControl('flashes');
			$this['domainsGrid']->redrawItem($domain_id, 'domain_id');
		} else {
			$this->redirect('this');
		}
	}


	/**
	 * Set as default (canonical) domain within its language
	 */
	public function handleSetDefault(int $domain_id): void
	{
		$domain = $this->model->getById($domain_id);
		if ($domain) {
			$this->model->findAll()->where('language_id', $domain->language_id)->update(array("default" => false));
			$this->model->update($domain_id, array("default" => true));
			$this->flashMessage(SUCCESS_SAVE, FLASH_SUCCESS);
		}
		$this->redirect('this');
	}

}
