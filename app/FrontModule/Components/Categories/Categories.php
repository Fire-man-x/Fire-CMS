<?php
declare(strict_types=1);

namespace App\FrontModule\Components\Categories;

use AlesWita\VisualPaginator\VisualPaginator;
use AlesWita\VisualPaginator\VisualPaginatorFactory;
use App\Model;
use Nette\Application\UI\Control;
use Nette\Localization\Translator;

/**
 * Class Categories
 *
 * Categories Component
 */
class Categories extends Control
{
	/** Query @var string */
	private $query = "";

	/** Tag id @var int */
	private $tagId = null;

	private string $templateFile;

	private string $language;


	/**
	 * Categories Component
	 */
	public function __construct(
		private readonly Translator $translator,
		private readonly Model\Database\Categories $categoriesModel,
		private readonly VisualPaginatorFactory $visualPaginatorFactory,
	) {
	}


	/**
	 * Query setter
	 */
	public function setQuery(string $query): static
	{
		$this->query = $query;
		return $this;
	}


	/**
	 * Tag setter
	 */
	public function whereTag(int $tagId): static
	{
		$this->tagId = $tagId;
		return $this;
	}


	/**
	 * Language setter
	 */
	public function setLanguage(string $language): self
	{
		$this->language = $language;

		return $this;
	}


	/**
	 * Custom template setter
	 */
	public function customTemplate(string $template = null): void
	{
		$this->templateFile = $template ?: __DIR__ . '/Categories.latte';
	}


	/**
	 * Render function
	 */
	public function render($fromCategory = null): void
	{
		$this->customTemplate();

		$this->template->setFile($this->templateFile);
		$this->template->setTranslator($this->translator);

		$categories = $this->categoriesModel->getAllWithTranslation($this->language)
			->where("active", true)
			->where("historyId", null)
			->order("title ASC");
		if($fromCategory){
			//$categories->where("category:category_category.categoryId", $fromCategory == null ? 1 : $fromCategory);
			$categories->where("category.parentId", $fromCategory);
		}
		if($this->query){
			$categories->whereOr(array(
				"title LIKE ?" => "%".$this->query."%",
				"excerpt LIKE ?" => "%".$this->query."%",
				"content LIKE ?" => "%".$this->query."%"
				));
		}
		if($this->tagId){
			$categories->where("category:" . Model\Database\Categories::RELATION_TAG_TABLE_NAME . ".tagId", $this->tagId);
		}
		$itemsCount = $categories->count();
		$this["paginator"]->setItemCount($itemsCount);
		$categories->limit($this["paginator"]->getItemsPerPage(), $this["paginator"]->getOffset());

		$this->template->categories = $categories;
		$this->template->showPaginator = $itemsCount > $this["paginator"]->getItemsPerPage();
		$this->template->render();
	}


	/**
	 * Paginator factory - pevně 10 kategorií na stránku, bez výběru počtu
	 */
	protected function createComponentPaginator(): VisualPaginator
	{
		return $this->visualPaginatorFactory->create()
			->setDefaultItemsPerPage(10);
	}

}
