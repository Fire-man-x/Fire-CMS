<?php
declare(strict_types=1);

namespace App\AdminModule\Presenters;

use App\Attributes\Privilege;
use App\Attributes\Resource;
use App\Attributes\Secured;
use App\AdminModule\Presenters\SectionAwareTrait\SectionAwareTrait;
use App\Components\CategoriesMenu\CategoriesMenu;
use App\Forms\CategoryFormFactory;
use App\Forms\MetaValueFormFactory;
use App\Model\Categories;
use App\Model\Files;
use App\Modules\UrlModule\UrlManager;
use App\Service\Category;
use App\Service\LanguageService;
use App\Service\Meta;
use App\Service\Tag;
use Nette\Application\Attributes\Persistent;
use Nette\Application\BadRequestException;
use Nette\Application\UI\Form;
use Nette\InvalidArgumentException;
use Nette\Utils\ArrayHash;

/**
 * Category Presenter
 */
#[Secured]
#[Resource('Categories')]
#[Privilege('view')]
class CategoriesPresenter extends BasePresenter
{
	use SectionAwareTrait;

	/**
	 * Not persistent revision parameter
	 */
	public static string $revisionParameter = "revision";

	/**
	 * Id
	 */
	#[Persistent]
	public ?int $id = null;

	/**
	 * Language
	 */
	#[Persistent]
	public ?string $language = null;

	/**
	 * Category parent
	 */
	private ?int $parent = null;

	/**
	 * Actual language
	 */
	public string $actualLanguage;

	/** @inject */
	public CategoryFormFactory $categoryFactory;

	/** @inject */
	public MetaValueFormFactory $metaValueFactory;

	/** @inject */
	public LanguageService$languages;

	/** @inject */
	public Categories $categoriesModel;

	/** @inject */
	public Category $categoryService;

	/** @inject */
	public Files $filesModel;

	/** @inject */
	public CategoriesMenu $categoriesMenu;

	/** @inject */
	public UrlManager $urlManager;

	/** @inject */
	public Tag $tagService;


	protected function startup(): void
	{
		parent::startup();

		$this->addBreadCrumbLink("Categories", $this->link(":Admin:Categories:default", array("id" => null)) );

		//default language
		if($this->language == $this->languages->getDefaultLanguage()) {
			$this->redirect("this", array("language" => null));
		}

		$category = $this->id ? $this->categoriesModel->getById($this->id) : null;
		if($this->id && !$category){
			throw new BadRequestException("Item with id '$this->id' doesn't exist.");
		}
		// sekce: editovaná kategorie má přednost před parametrem `section`
		$this->resolveSection($category ? (int) $category->sectionId : null);

		//$this->categoryService->recalculateTree();
	}


	#[Secured]
	#[Resource('Categories')]
	#[Privilege('view')]
	public function actionDefault(int $parent = null): void
	{
		// nadřazená kategorie jen ze stejné sekce
		if ($parent !== null && (int) $this->categoriesModel->getById($parent)?->sectionId !== $this->getSectionId()) {
			throw new BadRequestException("Parent category '$parent' is not in this section.");
		}
		$this->parent = $parent;

		if ($this->languages->existLanguage($this->language)) {
			$this->actualLanguage = $this->language == null ? $this->languages->getDefaultLanguage() : $this->language;
		} else {
			$this->actualLanguage = $this->languages->getDefaultLanguage();
		}

		if(!$this->id){
			$this->addBreadCrumbLink("New", $this->link(":Admin:Categories:default", array("id" => null)) );
		}

		$this->template->categories = $this->categoriesModel;

		//revisionCount
		$this->template->revisionCount = $this->categoryService->getRevisionsCount($this->id ?? 0);

		//urlManager
		$this->template->urlManager = $this->urlManager;
	}


	public function renderDefault(): void
	{
		$this->template->languages = $this->languages->getLanguages();
		$this->template->actualLanguage = $this->actualLanguage;
		$this->template->id = $this->id;

		if($this->id){
			$files = $this->categoriesModel->getRelationFile($this->id);
			$this->template->files = array();
			foreach ($files as $file){
				$tmpFile = ArrayHash::from($file->toArray());
				$tmpFile->file = $this->filesModel->toFileEntity($file);
				$this->template->files[] = $tmpFile;

			}

			//revisionList
			$this->template->revisionsList = $this->categoryService->getRevisions($this->id);
		}
	}


	/**
	 * Sign-up form factory.
	 */
	protected function createComponentCategoryForm(): Form
	{
		$this->categoryFactory->setParent($this->parent);
		$this->categoryFactory->setSectionId($this->getSectionId());
		$form = $this->categoryFactory->create(
			$this->id,
			$this->actualLanguage,
			array($this, "link"),
			$this->getParameter(self::$revisionParameter),
			$this->link("loadTags!", array("query"=>"QUERY"))
			);
		$form->setTranslator($this->translator);

		return $form;
	}


	/**
	 * Sign-up form factory.
	 */
	protected function createComponentMetaValueForm(): Form
	{
		$this->metaValueFactory->setType(Meta::TYPE_CATEGORY);
		$form = $this->metaValueFactory->create($this->id, array($this, "link"), $this->actualLanguage);
		$form->setTranslator($this->translator);

		$form->getElementPrototype()->addClass("ajax");

		$form->onSuccess[] = function ($form) {
			$form->getPresenter()->redrawControl("metas");
		};

		return $form;
	}


	/**
	 * Categories menu
	 */
	protected function createComponentCategoriesMenu(): CategoriesMenu
	{
		$control =  $this->categoriesMenu;
		$control->setActiveCategory($this->parent ?: $this->id)
			->setLanguage($this->language)
			->setSectionId($this->getSectionId());

		return $control;
	}


	/**
	 * Validate URL handler
	 */
	public function handleValidateUrl($text): void
	{
		if(!$text)
		{
			$this->terminate();
		}
		$this->payload->url = $this->urlManager->validateUrl($text, 'category', $this->id);

		$this->sendPayload();
	}


	/**
	 * Add Images handler
	 */
	#[Secured]
	#[Resource('Categories')]
	#[Privilege('add')]
	public function handleAddImage(array $files): void
	{
		foreach ($files as $fileId){
			$this->categoriesModel->insertRelationFile($this->id, $fileId);
		}

		$this->redrawControl("files");
	}


	/**
	 * Delete file handler
	 */
	#[Secured]
	#[Resource('Categories')]
	#[Privilege('delete')]
	public function handleRemoveImage(int $fileId): void
	{
		$this->categoriesModel->deleteRelationFile($this->id, $fileId);

		if($this->isAjax()){
			$this->redrawControl("files");
		} else {
			$this->redirect('this');
		}
	}


	/**
	 * Add item image handler
	 */
	public function handleSortItems(array $items): void
	{
		if(empty($items)){
			throw new InvalidArgumentException("Items cannot be empty.");
		}

		//add image
		$this->categoriesModel->changeFilePositions($this->id, $items);

		$this->redrawControl("files");
	}


	/**
	 * Load tags handler
	 */
	#[Secured]
	#[Resource('Articles')]
	#[Privilege('edit')]
	public function handleLoadTags(string $query): void
	{
		$items = $this->tagService->findByName($this->actualLanguage, $query);
		foreach ($items as &$item){
			$item = array(
				\Achse\TagInput\DataSourceDescriptor::DEFAULT_VALUE_PROPERTY => null,
				\Achse\TagInput\DataSourceDescriptor::DEFAULT_LABEL_PROPERTY => $item["label"]
			);
		}
		$this->sendJson($items);
	}

}
