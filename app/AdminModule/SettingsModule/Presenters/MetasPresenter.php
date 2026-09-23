<?php
declare(strict_types=1);

namespace App\AdminModule\SettingsModule\Presenters;

use App\Attributes\Privilege;
use App\Attributes\Resource;
use App\Attributes\Secured;
use App\Model\Metas;
use App\Forms\MetaFormFactory;
use App\Service\LanguageService;
use Contributte\Datagrid\Column\ColumnText;
use Contributte\Datagrid\Datagrid;
use Nette;

/**
 * Metas presenter.
 */
#[Secured]
#[Resource('Metas')]
#[Privilege('view')]
class MetasPresenter extends BasePresenter
{

	/** @inject */
	public MetaFormFactory $factory;

	/** @inject */
	public Metas $model;

	/** @inject */
	public LanguageService $languages;


	public function startup(): void
	{
		parent::startup();

		$this->addBreadCrumbLink("Metas", $this->link(":Admin:Settings:Metas:default", array("id"=>null)));
	}


	/**
	 * Metas grid
	 */
	protected function createComponentMetasGrid(string $name): Datagrid
	{
		$source = $this->model->findAll()->order("languageId ASC")->order("key ASC")->order($this->model->getColumnId());
		$primaryKey = $this->model->getColumnId();
		$paramKey = $this->model->getForeignKeyColumn();

		$grid = new Datagrid($this, $name);
		$grid->setPrimaryKey($primaryKey);
		$grid->setDataSource($source);
		$grid->setTranslator($this->translator);

		$that = $this;

		//columns
		$grid->addColumnText("languageId", "Language");
		//override default value
		$grid->addColumnCallback("languageId", function(ColumnText $column, $data){
			if (!$data->languageId) {
				$column->setRenderer(function() {
					return $this->translator->translate(MetaFormFactory::$allLanguages);
				});
			}
			else
			{
				$column->setRenderer(function() use ($data) {
					return $this->languages->getLanguage($data->languageId);
				});
			}
		});

		$grid->addColumnText("type", "Type")
			->setRenderer(function ($row) use ($that) {
			return $that->translator->translate(MetaFormFactory::$types[$row['type']]);
		});
		$grid->addColumnText("key", "Key");
		$grid->addColumnText("value", "Value")
			->setRenderer(function ($row) {
				if(!$row['value']){
					return PROMPT_VALUE;
				}
				return $row['value'];
			});

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
				"data-confirm-text" => $this->translator->translate('Delete?'),
			));

		return $grid;
	}


	/**
	 * Meta form factory.
	 */
	protected function createComponentMetaForm(): Nette\Application\UI\Form
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
		$this->redrawControl("metaForm");
	}


	/**
	 * Edit handler
	 */
	public function handleEdit(int $metaId): void
	{
		$this->factory->setEditId($metaId);
		$this->factory->setDefaultValues($this["metaForm"], $metaId);
		$this->redrawControl("metaForm");
	}


	/**
	 * Delete handler
	 */
	public function handleDelete(int $metaId): void
	{
		$this->model->delete($metaId);
		$this->flashMessage(SUCCESS_DELETE, FLASH_SUCCESS);

		$this->redirect('this');
	}

}
