<?php
declare(strict_types=1);

namespace App\AdminModule\SettingsModule\Presenters;

use App\Model\Metas;
use App\Forms\MetaFormFactory;
use App\Service\LanguageService;
use Contributte\Datagrid\Column\ColumnText;
use Contributte\Datagrid\Datagrid;
use Nette;

/**
 * Metas presenter.
 */
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
		$source = $this->model->getAll()->order("language_id ASC")->order("key ASC")->order($this->model->getColumnId());
		$primaryKey = $this->model->getColumnId();

		$grid = new Datagrid($this, $name);
		$grid->setPrimaryKey($primaryKey);
		$grid->setDataSource($source);
		$grid->setTranslator($this->translator);

		$that = $this;

		//columns
		$grid->addColumnText("language_id", "Language");
		//override default value
		$grid->addColumnCallback("language_id", function(ColumnText $column, $data){
			if (!$data->language_id) {
				$column->setRenderer(function() {
					return $this->translator->translate(MetaFormFactory::$allLanguages);
				});
			}
			else
			{
				$column->setRenderer(function() use ($data) {
					return $this->languages->getLanguage($data->language_id);
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
	public function handleEdit(int $meta_id): void
	{
		$this->factory->setEditId($meta_id);
		$this->factory->setDefaultValues($this["metaForm"], $meta_id);
		$this->redrawControl("metaForm");
	}


	/**
	 * Delete handler
	 */
	public function handleDelete(int $meta_id): void
	{
		$this->model->delete($meta_id);
		$this->flashMessage(SUCCESS_DELETE, FLASH_SUCCESS);

		$this->redirect('this');
	}

}
