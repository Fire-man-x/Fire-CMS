<?php
declare(strict_types=1);

namespace App\Modules\CommentsModule\Components\Comments;

use AlesWita\VisualPaginator\VisualPaginator;
use AlesWita\VisualPaginator\VisualPaginatorFactory;
use App\Forms\CommentFormFactory;
use App\Modules\CommentsModule;
use Nette\Application\UI\Control;
use Nette\Localization\Translator;

/**
 * Class Comments
 *
 * Comments Component
 */
class Comments extends Control
{

	/** comments */
	private array $comments = array();

	/** Article id */
	private ?int $articleId = null;

	/** Category id */
	private ?int $categoryId = null;

	private string $templateFile;

	private string $language;

	private string $type;


	/**
	 * Comments Component
	 */
	public function __construct(
		private readonly Translator $translator,
		private readonly CommentFormFactory $commentFormFactory,
		private readonly CommentsModule\Comment $commentsService,
		private readonly VisualPaginatorFactory $visualPaginatorFactory,
	) {
	}


	private function setType($type): self
	{
		if (!in_array($type, array(CommentsModule\Comment::TYPE_ARTICLE, CommentsModule\Comment::TYPE_CATEGORY))) {
			throw new \InvalidArgumentException("Type '$type' is not valid.");
		}

		$this->type = $type;
		return $this;
	}


	/**
	 * Article setter
	 */
	public function whereArticle(int $articleId): static
	{
		if(isset($this->categoryId)){
			throw new \InvalidArgumentException("You can not set Article, Category already setted.");
		}
		$this->articleId = $articleId;

		$this->setType(CommentsModule\Comment::TYPE_ARTICLE);
		return $this;
	}


	/**
	 * Category setter
	 */
	public function whereCategory(int $categoryId): static
	{
		if(isset($this->articleId)){
			throw new \InvalidArgumentException("You can not set Category, Article already setted.");
		}
		$this->categoryId = $categoryId;

		$this->setType(CommentsModule\Comment::TYPE_CATEGORY);
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
		$this->templateFile = $template ?: __DIR__ . '/Comments.latte';
	}


	/**
	 * Render function
	 */
	public function render()
	{
		$this->customTemplate();

		$this->template->setFile($this->templateFile);
		$this->template->setTranslator($this->translator);

		if($this->articleId){
			$columnId = $this->articleId;
		}
		if($this->categoryId){
			$columnId = $this->categoryId;
		}
		$this->template->comments = $this->commentsService->getAllCommentsWithChilds($this->language, $this->type, $columnId, $this["paginator"]->getPaginator());

		//$this->commentsService->recalculateTree($this->language, $this->type, $columnId);

		$this->template->showPaginator = $this["paginator"]->getPaginator()->getItemCount() > $this["paginator"]->getItemsPerPage();
		$this->template->render();
	}


	/**
	 * Load subcomments
	 */
	protected function loadSubComments($fromCommentId)
	{

	}


	/**
	 * Paginator factory - pevně 10 komentářů na stránku, bez výběru počtu
	 */
	protected function createComponentPaginator(): VisualPaginator
	{
		return $this->visualPaginatorFactory->create()
			->setDefaultItemsPerPage(10);
	}


	/**
	 * Comment form factory.
	 */
	protected function createComponentCommentForm(): \Nette\Application\UI\Form
	{
		$this->commentFormFactory->setType($this->type);
		if($this->articleId){
			$form = $this->commentFormFactory->create(null, $this->language, $this->articleId);
		}
		if($this->categoryId){
			$form = $this->commentFormFactory->create(null, $this->language, $this->categoryId);
		}
		$form->setTranslator($this->translator);

		return $form;
	}

}
