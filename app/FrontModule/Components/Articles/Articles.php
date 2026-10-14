<?php
declare(strict_types=1);

namespace App\FrontModule\Components\Articles;

use AlesWita\VisualPaginator\VisualPaginator;
use AlesWita\VisualPaginator\VisualPaginatorFactory;
use App\FileStorage\FileManager;
use App\Model;
use Nette\Application\UI\Control;
use Nette\Localization\Translator;

/**
 * Class Articles
 *
 * Articles Component
 */
class Articles extends Control
{

	/** articles */
	//private array $articles = array();

	/** Query */
	private string $query = "";

	/** Tag id */
	private ?int $tagId = null;

	private string $templateFile;

	private string $language;


	/**
	 * Articles Component
	 */
	public function __construct(
		private readonly Translator $translator,
		private readonly Model\Database\Articles $articlesModel,
		private readonly Model\Database\Files $filesModel,
		private readonly FileManager $fileManager,
		private readonly Model\Database\Users $usersModel,
		private readonly Model\UserManager $userManager,
		private readonly VisualPaginatorFactory $visualPaginatorFactory)
	{
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
		$this->templateFile = $template ?: __DIR__ . '/Articles.latte';
	}


	/**
	 * Render function
	 */
	public function render(int $fromCategory = null): void
	{
		$this->customTemplate();

		$this->template->setFile($this->templateFile);
		$this->template->setTranslator($this->translator);

		// jen publikované aktivní články s datem publikace v minulosti (koncepty ani budoucí články na web nepatří)
		$articles = $this->articlesModel->findPublished($this->language)
			->order("article.publishingDate DESC");
		if($fromCategory){
			//$articles->where("article:category_article.categoryId", $fromCategory == null ? 1 : $fromCategory);
			$articles->where("article:". Model\Database\Categories::RELATION_ARTICLE_TABLE_NAME.".categoryId", $fromCategory);
		}
		if($this->query){
			$articles->whereOr(array(
				"title LIKE ?" => "%".$this->query."%",
				"excerpt LIKE ?" => "%".$this->query."%",
				"content LIKE ?" => "%".$this->query."%"
				));
		}
		if($this->tagId){
			$articles->where("article:" . Model\Database\Articles::RELATION_TAG_TABLE_NAME . ".tagId", $this->tagId);
		}
		$itemsCount = $articles->count();
		/** @var VisualPaginator $paginator */
		$paginator = $this["paginator"];
		$paginator->setItemCount($itemsCount);
		$articles->limit($paginator->getItemsPerPage(), $paginator->getOffset());

		$articlesArray = $articles->fetchAll();
		foreach ($articlesArray as &$article){
			$article = \Nette\Utils\ArrayHash::from($article->toArray());
			$files = $this->articlesModel->getRelationFile($article->articleId)->where("isMain", true);
			$article->mainFile = null;
			foreach ($files as $file){
				$article->mainFile = $this->filesModel->toFileEntity($file);
			}

			//author
			$user = $this->usersModel->getById($article->createdBy)?->toArray();
			$user['author'] = $this->userManager->makeName($user);
			$article->author = $user;
		}

		$this->template->articles = $articlesArray;
		$this->template->showPaginator = $itemsCount > $paginator->getItemsPerPage();
		$this->template->__imagestore = $this->fileManager;
		$this->template->render();
	}


	/**
	 * Paginator factory - pevně 10 článků na stránku, bez výběru počtu
	 */
	protected function createComponentPaginator(): VisualPaginator
	{
		return $this->visualPaginatorFactory->create()
			->setDefaultItemsPerPage(10);
	}

}
