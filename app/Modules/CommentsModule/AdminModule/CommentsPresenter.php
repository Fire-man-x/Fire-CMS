<?php
declare(strict_types=1);

namespace App\Modules\CommentsModule\AdminModule;

use App\AdminModule\Presenters\BasePresenter;
use App\Attributes\Privilege;
use App\Attributes\Resource;
use App\Attributes\Secured;
use App\Model;
use App\Modules\CommentsModule;
use App\Forms\CommentFormFactory;
use App\Service\LanguageService;
use App\Service\Tag;
use Contributte\Datagrid\Datagrid;
use Nette;
use Nette\Application\Attributes\Persistent;

/**
 * Comment Presenter
 */
class CommentsPresenter extends BasePresenter
{
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
	 * Show filter
	 * @persistentInDefault
	 */
	public ?string $show;

	/**
	 * Comment parent
	 */
	//private int $parent;

	/**
	 * Actual language
	 */
	public ?string $actualLanguage = null;

	/** @inject */
	public CommentFormFactory $commentFactory;

	/** @inject */
	public CommentsModule\Model\Comments $commentsModel;

	/** @inject */
	public CommentsModule\Comment $commentService;

	/** @inject */
	public Model\Categories $categoriesModel;

	/** @inject */
	public Model\Files $filesModel;

	/** @inject */
	public Tag $tagService;

	/** @inject */
	public Model\UserManager $userManager;

	/** @inject */
	public Model\Users $userModel;


	protected function startup(): void
	{
		parent::startup();

		$this->addBreadCrumbLink("Comments", $this->link(":Admin:Comments:default", array("id" => null)) );

		//default language
		if($this->language == $this->languages->getDefaultLanguage()) {
			$this->redirect("this", array("language" => null));
		}
	}


	#[Secured]
	#[Resource('Comments')]
	#[Privilege('view')]
	public function actionDefault(string $show = null)
	{
		$this->show = $show;
		if (!is_null($this->show) && !in_array($this->show, array("trash","pending"))) {
			throw  new \InvalidArgumentException("Parameter show '$this->show' is not permited");
		}
		$this->template->show = $this->show;

		$counts = array(
			"all"=>$this->commentsModel->findAll()
				->select("COUNT(*) AS count")
				->where($this->commentsModel->getTableName().".status != ?","trash")
				//->where($this->commentsModel->getTableName().".historyId", null)
				->fetch()?->count,
			"pending"=>$this->commentsModel->findAll()
				->select("COUNT(*) AS count")
				->where($this->commentsModel->getTableName().".status = ?","pending")
				//->where($this->commentsModel->getTableName().".historyId", null)
				->fetch()?->count,
			"trash"=>$this->commentsModel->findAll()
				->select("COUNT(*) AS count")
				->where($this->commentsModel->getTableName().".status = ?","trash")
				//->where($this->commentsModel->getTableName().".historyId", null)
				->fetch()?->count
			);
		$this->template->counts = $counts;
	}


	/**
	 * @SecuredInside
	 * @Resource (Comments)
	 * @Privilege (edit)
	 */
	public function actionDetail($parent = null)
	{
		$this->parent = $parent;

		if ($this->language !== null && $this->languages->existLanguage($this->language)) {
			$this->actualLanguage = $this->language == null ? $this->languages->getDefaultLanguage() : $this->language;
		} else {
			$this->actualLanguage = $this->languages->getDefaultLanguage();
		}

		$this->template->comments = $this->commentsModel;

		//edit own
		$createdBy = $this->commentsModel->getById($this->id)?->createdBy;
		if(!$this->user->isAllowed(new \App\Security\Resource("Comments", $createdBy),"edit")){
			throw new \Nette\Application\ForbiddenRequestException("You have not access to 'Comments' with priviledge 'edit'.");
		}

		//revisionCount
		$this->template->revisionCount = $this->commentService->getRevisionsCount($this->id);
	}


	/**
	 * Comments grid
	 */
	protected function createComponentCommentsGrid(string $name): Datagrid
	{
		$source = $this->commentsModel->findAll()
			->select($this->commentsModel->getTableName().".*")
			//->where($this->commentsModel->getTableName().".historyId", null)
			->order($this->commentsModel->getTableName().".createDate DESC")
			->order($this->commentsModel->getTableName().".".$this->commentsModel->getColumnId());
		switch ($this->show) {
			case "pending":
				$source->where($this->commentsModel->getTableName().".status = ?","pending");
				break;
			case "trash":
				$source->where($this->commentsModel->getTableName().".status = ?","trash");
				break;

			case null:
			default:
			$source->where($this->commentsModel->getTableName().".status != ?","trash");
				break;
		}

		$primaryKey = $this->commentsModel->getColumnId();
		$paramKey = $this->commentsModel->getForeignKeyColumn();

		$grid = new DataGrid($this, $name);
		$grid->setPrimaryKey($primaryKey);
		$grid->setDataSource($source);
		$grid->setTranslator($this->translator);

		$usersList = $this->userModel->findAll()->fetchAssoc($this->userModel->getColumnId());
		$userManager = $this->userManager;
		$grid->addColumnText("author", "Author")
			->setRenderer(function ($row) use ($usersList, $userManager) {
				if($row["createdBy"]){
					return $userManager->makeName($usersList[$row["createdBy"]]);
				}else{
					return $row["author"]."<br>". \Nette\Utils\Html::el("a",array("href"=>"mailto:".$row["authorEmail"]))->setText($row["authorEmail"]);
				}
			})
			->setTemplateEscaping(false);

		$translator = $this->translator;
		$presenter = $this->getPresenter();
		$grid->addColumnText("title", "Title")
			->setRenderer(function ($row) use ($translator, $paramKey, $presenter) {
				$replyTo = "";
				if($row["parentId"]){
					$replyTo = "<br><small><a href=\"".$presenter->link("reply!", array($paramKey=>$row["parentId"]))."\" class=\"ajax\" data-bs-toggle=\"modal\" data-bs-target=\"#modal\">".$translator->translate("From comment...")."</a></small>";
				}
				return Nette\Utils\Strings::truncate($row["title"], 50).$replyTo;
			})
			->setTemplateEscaping(false);

		$grid->addColumnText("text", "Text")
			->setRenderer(function ($row) {
				return Nette\Utils\Strings::truncate($row["text"], 100);
			});

		$grid->addColumnDateTime("createDate", "Create date")
			->setFormat(DATETIME_FORMAT);

		//Actions
		if($this->show == "pending"){
			$grid->addAction('approve', 'Approve', 'approve!', array($paramKey => $primaryKey))
				->setClass(function($item) {
					return 'btn btn-success btn-sm ajax'.(!$this->user->isAllowed("Comments", "approve_comment") ? ' disabled' : '');
				})
				->setIcon('check')
				->setTitle('Edit')
				->addAttributes(array(
					'data-bs-toggle' => 'modal',
					"data-bs-target" => "#confirm-modal",
					"data-confirm-text" => $this->translator->translate('Approve?'),
				));
		}
		$grid->addAction('reply', 'Reply', 'reply!', array($paramKey => $primaryKey))
			->setClass(function($item) {
				return 'btn btn-primary btn-sm'.(!$this->user->isAllowed(new \App\Security\Resource("Comments", $item["createdBy"]), "edit") ? ' disabled' : '');
			})
			->setIcon('reply')
			->setTitle('Reply')
			->addAttributes(array(
				"data-bs-toggle" => "modal",
				"data-bs-target" => "#modal"
			));

		$grid->addAction('delete', 'Delete', 'delete!', array($paramKey => $primaryKey))
			->setClass(function($item) {
				return 'btn btn-danger btn-sm ajax'.(!$this->user->isAllowed(new \App\Security\Resource("Comments", $item["createdBy"]), "edit") ? ' disabled' : '');
			})
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
	 * Sign-up form factory.
	 * @return Nette\Application\UI\Form
	 */
	protected function createComponentCommentReplyForm()
	{
		$form = $this->commentFactory->createReply(
			$this->id,
			$this->actualLanguage ?: "en"
		);
		$form->setTranslator($this->translator);

		return $form;
	}


	/**
	 * Activate
	 * @param int $commentId
	 * @param boolean $status
	 * @SecuredInside
	 * @Resource(Comments)
	 * @Privilege(edit)
	 */
	public function handleActivate(int $commentId, $status = 0)
	{
		//edit own
		$createdBy = $this->commentsModel->getById($commentId)?->createdBy;
		if(!$this->user->isAllowed(new \App\Security\Resource("Comments", $createdBy),"edit")){
			throw new \Nette\Application\ForbiddenRequestException("You have not access to 'Comments' with priviledge 'edit'.");
		}
		$this->commentService->makeBackup($commentId);
		$this->commentsModel->update($commentId, array("active" => (boolean) $status));
		$this->flashMessage(SUCCESS_SAVE, FLASH_SUCCESS);
		$this->redirect('this');
	}


	/**
	 * Approve
	 * @param int $commentId
	 */
	#[Secured]
	#[Resource('Comments')]
	#[Privilege('approve_comment')]
	public function handleApprove(int $commentId)
	{
		$this->commentService->makeBackup($commentId);
		$this->commentsModel->statusPublish($commentId);
		$this->flashMessage(SUCCESS_SAVE, FLASH_SUCCESS);
		$this->redirect('this');
	}


	/**
	 * Add handler
	 * @param int $commentId
	 */
	#[Secured]
	#[Resource('Comments')]
	#[Privilege('edit')]
	public function handleReply(int $commentId)
	{
		$this->template->commentInfo = $this->commentsModel->getById($commentId);;

		//$this->commentFactory->setEditId($commentId);
		$this->commentFactory->setDefaultValuesReply($this["commentReplyForm"], $commentId);
		$this->redrawControl("commentReplyForm");
	}


	/**
	 * Edit handler
	 * @param int $commentId
	 */
	#[Secured]
	#[Resource('Comments')]
	#[Privilege('edit')]
	public function handleEdit(int $commentId)
	{
		$this->commentFactory->setEditId($commentId);
		$this->commentFactory->setDefaultValues($this["commentForm"], $commentId);
		$this->redrawControl("commentForm");
	}


	/**
	 * Delete handler
	 * @param int $commentId
	 */
	#[Secured]
	#[Resource('Comments')]
	#[Privilege('delete')]
	public function handleDelete(int $commentId)
	{
		$this->commentService->delete($commentId);
		$this->flashMessage(SUCCESS_DELETE, FLASH_SUCCESS);

		$this->redirect('this');
	}

}
