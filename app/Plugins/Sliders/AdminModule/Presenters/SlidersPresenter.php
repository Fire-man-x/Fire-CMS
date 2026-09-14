<?php
declare(strict_types=1);

namespace App\Plugins\Sliders\AdminModule\Presenters;

use App\AdminModule\Presenters\BasePresenter;
use App\Model\Files;
use App\Plugins\Sliders\Forms\SliderFormFactory;
use App\Plugins\Sliders\Forms\SliderItemFormFactory;
use App\Plugins\Sliders\Model\SliderItems;
use App\Plugins\Sliders\Model\Sliders;
use App\Service\LanguageService;
use Contributte\Datagrid\Datagrid;
use Nette;
use Nette\Application\Attributes\Persistent;
use Nette\InvalidArgumentException;

/**
 * Sliders presenter.
 */
class SlidersPresenter extends BasePresenter
{

	/**
	 * Id
	 */
	#[Persistent]
	public int $id;

	/** @inject */
	public SliderFormFactory $factorySlider;

	/** @inject */
	public SliderItemFormFactory $factorySliderItem;

	/** @inject */
	public Sliders $modelSliders;

	/** @inject */
	public Files $filesModel;

	/** @inject */
	public SliderItems $modelSliderItems;

	/** @inject */
	public LanguageService $languages;

	/** @inject */
	public \App\Components\FileManager\FileManager $fileManager;


	public function startup(): void
	{
		parent::startup();

		$this->addBreadCrumbLink("Sliders", $this->link(":Admin:Sliders:default", array("id" => null)));

		$this->template->__imagestore = $this->fileManager;
	}


	public function renderDetail(): void
	{
		$this->template->languages = $this->languages->getLanguages();
		$this->template->sliderInfo = $this->modelSliders->findById($this->id)->fetch();
		$this->template->items = $this->modelSliderItems->getAll()
			->select("slider_items.*")
			->select("file.*")
			->where("slider_id", $this->id)
			->order("position")
			->fetchAssoc("language_id|position");

		foreach ($this->template->items as &$languageItem){
			foreach ($languageItem as &$item){
				$item["file"] = $this->filesModel->toFileEntity($item);
			}
		}
	}


	/**
	 * Sliders grid
	 */
	protected function createComponentSlidersGrid(string $name): Datagrid
	{
		$source = $this->modelSliders->getAll()->order("name");
		$primaryKey = $this->modelSliders->getColumnId();

		$grid = new Datagrid($this, $name);
		$grid->setPrimaryKey($primaryKey);
		$grid->setDataSource($source);
		$grid->setTranslator($this->translator);

		$grid->addColumnText("name", "Title");
		$grid->addColumnText("location", "Template location");

		//Actions
		$grid->addAction('edit', 'Edit', 'edit!', array($primaryKey => $primaryKey))
			->setClass('btn btn-primary btn-sm ajax')
			->setIcon(ICON_EDIT)
			->setTitle('Edit')
			->addAttributes(array(
				"data-bs-toggle" => "modal",
				"data-bs-target" => "#modal"
			));

		$grid->addAction('settings', 'Settings', 'detail', array("id" => $primaryKey))
			->setClass('btn btn-primary btn-sm')
			->setIcon('list')
			->setTitle('Settings');

		$grid->addAction('delete', 'Delete', 'delete!', array($primaryKey => $primaryKey))
			->setClass('btn btn-danger btn-sm ajax')
			->setIcon(ICON_DELETE)
			->setTitle('Delete')
			->addAttributes(array(
				'data-bs-toggle' => 'modal',
				"data-bs-target" => "#confirm-modal",
				"data-confirm-text" => $this->translator->translate('Delete?'),
			));


		//sorting
		/*$grid->setSortable();
		$model = $this->modelSliders;
		/*$grid->onSort[] = function ($data) use ($model) {
			$sort = 1;
			foreach ($data as $item_id) {
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
	protected function createComponentSliderForm(): Nette\Application\UI\Form
	{
		$this->factorySlider->asModal();
		$form = $this->factorySlider->create();
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
	protected function createComponentSliderItemForm(): Nette\Application\UI\Form
	{
		$this->factorySliderItem->asModal();
		$this->factorySliderItem->setSliderId($this->id);
		$form = $this->factorySliderItem->create();
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
		$this->factorySlider->resetEditMode();
		$this->redrawControl("sliderForm");
	}


	/**
	 * Edit handler
	 */
	public function handleEdit(int $slider_id): void
	{
		$this->factorySlider->setEditId($slider_id);
		$this->factorySlider->setDefaultValues($this["sliderForm"], $slider_id);
		$this->redrawControl("sliderForm");
	}


	/**
	 * Delete handler
	 */
	public function handleDelete(int $slider_id): void
	{
		if (!$this->modelSliders->findById($slider_id)->fetch()->default) {
			$this->modelSliders->delete($slider_id);
			$this->flashMessage(SUCCESS_DELETE, FLASH_SUCCESS);
		} else {
			$this->flashMessage(FAIL_DELETE, FLASH_FAILED);
		}
		$this->redirect('this');
	}


	/**
	 * Add item handler
	 */
	public function handleAddItem(string $language): void
	{
		//$this->factorySliderItem->setLanguage($language);
		//$this->factorySliderItem->resetEditMode();
		$this->redrawControl("sliderItemForm");
	}


	/**
	 * Edit item handler
	 */
	public function handleEditItem(string $language, int $position): void
	{
		$this->factorySliderItem->setEditId($position);
		$this->factorySliderItem->setLanguage($language);
		$this->factorySliderItem->setDefaultValues($this["sliderItemForm"], $position);
		$this->redrawControl("sliderItemForm");
	}


	/**
	 * Remove item handler
	 */
	public function handleRemoveItem(string $language, int $position): void
	{
		$this->modelSliderItems->delete(array(
			"slider_id" => $this->id,
			"language_id" => $language,
			"position" => $position
			));
		if ($this->isAjax()) {
			$this->redrawControl("items");
		} else {
			$this->redirect('this');
		}
	}


	/**
	 * Add item image handler
	 */
	public function handleAddItemImage(array $files, ?string $language = null): void
	{
		if(empty($files)){
			throw new InvalidArgumentException("Files cannot be empty.");
		}
		if(!$language){
			$language = $this->languages->getDefaultLanguage();
		}

		//add image
		foreach($files as $file){
			$this->modelSliderItems->insert(Nette\Utils\ArrayHash::from(array(
				"slider_id" => $this->id,
				"file_id" => $file,
				"language_id" => $language
			)));
		}

		$this->redrawControl("items");
	}


	/**
	 * Add item image handler
	 */
	public function handleSortItems(string $language, array $items): void
	{
		if(!$language){
			throw new InvalidArgumentException("Language cannot be empty.");
		}
		if(empty($items)){
			throw new InvalidArgumentException("Items cannot be empty.");
		}

		//add image
		$this->modelSliderItems->changePositions($this->id, $language, $items);

		$this->redrawControl("items");
	}

}
