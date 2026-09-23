<?php
declare(strict_types=1);

namespace App\FrontModule\Components\Articles;

use AlesWita\Components\VisualPaginator;
use App\Components\FileManager\FileManager;
use App\Model;
use Nette\Application\UI\Control;

/**
 * Class Articles
 *
 * Articles Component
 */
class Articles extends Control
{

	/** articles */
	private array $articles = array();

	/** Query */
	private string $query = "";

	/** Tag id */
	private ?int $tagId = null;

	private string $templateFile;

	private \Nette\Localization\Translator $translator;

	private Model\Articles $articlesModel;

	private Model\Files $filesModel;

	private string $language;

	private FileManager $fileManager;

	private Model\Users $usersModel;

	private Model\UserManager $userManager;


	/**
	 * Articles Component
	 */
	public function __construct(\Nette\Localization\Translator $translator, Model\Articles $articlesModel, Model\Files $filesModel, FileManager $fileManager, Model\Users $usersModel, Model\UserManager $userManager)
	{
		$this->translator = $translator;
		$this->articlesModel = $articlesModel;
		$this->filesModel = $filesModel;
		$this->fileManager = $fileManager;
		$this->usersModel = $usersModel;
		$this->userManager = $userManager;
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

		$articles = $this->articlesModel->getAllWithTranslation($this->language)
			->where("historyId", null)
			->order("createDate DESC");
		if($fromCategory){
			//$articles->where("article:category_article.categoryId", $fromCategory == null ? 1 : $fromCategory);
			$articles->where("article:".Model\Categories::RELATION_ARTICLE_TABLE_NAME.".categoryId", $fromCategory);
		}
		if($this->query){
			$articles->whereOr(array(
				"title LIKE ?" => "%".$this->query."%",
				"excerpt LIKE ?" => "%".$this->query."%",
				"content LIKE ?" => "%".$this->query."%"
				));
		}
		if($this->tagId){
			$articles->where("article:" . Model\Articles::RELATION_TAG_TABLE_NAME . ".tagId", $this->tagId);
		}
		$itemsCount = $articles->count();
		$this["paginator"]->setItemCount($itemsCount);
		$articles->limit($this["paginator"]->getItemsPerPage(), $this["paginator"]->getOffset());

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
		$this->template->showPaginator = $itemsCount > $this["paginator"]->getItemsPerPage();
		$this->template->__imagestore = $this->fileManager;
		$this->template->render();
	}


	/**
	 * Paginator factory.
	 * @return VisualPaginator
	 */
	protected function createComponentPaginator()
	{
		$vp = new VisualPaginator();
		//$vp->setCanSetItemsPerPage(true);
		//$vp->setItemsPerPageList(array(1,2,3,10=>10));
		$vp->setItemsPerPage(10);
		$vp->setTranslator($this->translator);

		return $vp;
	}

}
