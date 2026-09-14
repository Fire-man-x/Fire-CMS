<?php
declare(strict_types=1);

namespace App\Modules\CommentsModule\Components\Comments;

use AlesWita\Components\VisualPaginator;
use App\Forms\CommentFormFactory;
use App\Model\UserManager;
use App\Modules\CommentsModule;
use Nette\Application\UI\Control;

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

	private \Nette\Localization\Translator $translator;

	private CommentFormFactory $commentFormFactory;

	private CommentsModule\Comment $commentsService;

	private string $language;

	private string $type;


	/**
	 * Comments Component
	 */
	public function __construct(\Nette\Localization\Translator $translator, CommentFormFactory $commentFormFactory, CommentsModule\Comment $commentsService)
	{
		$this->translator = $translator;
		$this->commentFormFactory = $commentFormFactory;
		$this->commentsService = $commentsService;
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
	 * @return VisualPaginator
	 */
	protected function loadSubComments($fromCommentId)
	{

	}


	/**
	 * Paginator factory.
	 * @return VisualPaginator
	 */
	protected function createComponentPaginator()
	{
		$vp = new VisualPaginator();
		//$vp->setCanSetItemsPerPage(true);
		//$vp->setItemsPerPageList(array(1,2,3,10=>10))
		$vp->setItemsPerPage(10);
		$vp->setTranslator($this->translator);

		return $vp;
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
