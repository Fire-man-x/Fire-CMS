<?php
declare(strict_types=1);

namespace App\AdminModule\SettingsModule\Presenters;

use App\Attributes\Privilege;
use App\Attributes\Resource;
use App\Attributes\Secured;
use App\Forms\TagFormFactory;
use App\Model\Tags;
use App\Service\LanguageService;
use Contributte\Datagrid\Column\ColumnText;
use Contributte\Datagrid\Datagrid;
use Nette;
use Nette\Application\Attributes\Persistent;

/**
 * Tags presenter.
 */
class TagsPresenter extends BasePresenter
{

	/**
	 * Language
	 */
	#[Persistent]
	public ?string $language = null;

	/**
	 * Actual language
	 */
	public ?string $actualLanguage = null;

	/** @inject */
	public TagFormFactory $factory;

	/** @inject */
	public Tags $model;

	/** @inject */
	public LanguageService $languages;

	public function startup(): void
	{
		parent::startup();

		$this->addBreadCrumbLink("Tags", $this->link(":Admin:Settings:Tags:default", array("id"=>null)));

		//default language
		/*if($this->language == $this->languages->getDefaultLanguage()) {
			$this->redirect("this", array("language" => null));
		}*/
	}


	#[Secured]
	#[Resource('Tags')]
	#[Privilege('view')]
	public function actionDefault(): void
	{
		if ($this->languages->existLanguage($this->language)) {
			$this->actualLanguage = $this->language;
		}

		$this->template->languages = $this->languages->getLanguages();
		$this->template->actualLanguage = $this->actualLanguage;
	}


	/**
	 * Tags grid
	 */
	protected function createComponentTagsGrid(string $name): Datagrid
	{
		$source = $this->model->getAll()
			->select("tags.*")
			->order("title");
		if ($this->actualLanguage != null) {
			$source->select(":" . $this->model->getTranslationTable()->getName() . ".name AS title");
			$source->where(":" . $this->model->getTranslationTable()->getName() . ".language_id", $this->actualLanguage);
		} else {
			$source->select("grid_name AS title");
			$source->select(":" . $this->model->getTranslationTable()->getName() . ".name");
			$source->select("COUNT(:" . $this->model->getTranslationTable()->getName() . ".language_id) AS language_count");
			$source->group("tags.tag_id");
		}

		$primaryKey = $this->model->getColumnId();

		$grid = new Datagrid($this, $name);
		$grid->setPrimaryKey($primaryKey);
		$grid->setDataSource($source);
		$grid->setTranslator($this->translator);
		$grid->setRememberState(false);

		$grid->addColumnText("title", "Tag", ":tag_descriptions.name")
			->setSortable();
			//->setFilterText('title');

		if(count($this->languages->getLanguages()) > 1 && $this->actualLanguage ==null){
			$grid->addColumnText('language_count', 'Translation status')
				//->setClass('btn btn-outline-primary btn-sm')
				//->setIcon('ban') //default ban icon
				//->setTitle($this->translator->translate('Set as default'))
				->getElementPrototype("th")->setTitle($this->translator->translate("Default"));
			//override default value
			$grid->addColumnCallback("language_count", function(ColumnText $column, $data){
				if ($data->language_count < count($this->languages->getLanguages())) {
					$column->setRenderer(function() {
						return '<span class="btn btn-outline-warning btn-sm" title="'.$this->translator->translate('Not translated in all languages').'"><i class="fas fa-exclamation-triangle"></i></span>';
					});
					$column->setTemplateEscaping(false);
				}
				else
				{
					$column->setRenderer(function() {
						return '<span class="btn btn-outline-success btn-sm" title="'.$this->translator->translate('Ok').'"><i class="fa fa-check-circle"></i></span>';
					});
					$column->setTemplateEscaping(false);
				}
			});
			/*$active_column = $grid->addColumnText('language_count', 'Translation status');

			//$active_column = $grid->addColumnStatus('language_count', 'Translation status');
			$active_column->getElementPrototype("th")->setTitle($this->translator->translate("Translation status"));
			$index = 1;
			while ($index < count($this->languages->getLanguages())) {
				//'Not translated in all languages (only %value '.$index.')'
				/*$active_column->addOption($index, 'Not translated in all languages') // show if status == 0
				->setClass('btn-warning')
					->setIcon('warning');
				* /

				$index++;
			}*/
		}

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

		/*$actions->onRender[] = function ($rowData, \Mesour\Datagrid\Column\Actions $actionColumns) {
			$actions = $actionColumns->getActions();
			/* @var $permissionButton \Mesour\Datagrid\Components\Button * /
			$permissionButton = $actions[1];
			/* @var $deleteButton \Mesour\Datagrid\Components\Button * /
			$deleteButton = $actions[2];
			if($rowData["default"]){
				$permissionButton->setDisabled();
				$deleteButton->setDisabled();
			}  else {
				$permissionButton->setDisabled(false);
				$deleteButton->setDisabled(false);
			}
		};*/

		return $grid;
	}


	/**
	 * Add user form
	 */
	protected function createComponentTagForm(): Nette\Application\UI\Form
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
	 * Add handler
	 */
	public function handleAdd(): void
	{
		$this->factory->resetEditMode();
		$this->redrawControl("tagForm");
	}


	/**
	 * Edit handler
	 */
	public function handleEdit(int $tag_id): void
	{
		$this->factory->setEditId($tag_id);
		$this->factory->setDefaultValues($this["tagForm"], $tag_id);
		$this->redrawControl("tagForm");
	}


	/**
	 * Delete handler
	 */
	public function handleDelete(int $tag_id): void
	{
		$this->model->delete($tag_id);
		$this->flashMessage(SUCCESS_DELETE, FLASH_SUCCESS);
		$this->redirect('this');
	}

}
