<?php
declare(strict_types=1);

namespace App\AdminModule\Presenters;

use App\Attributes\Privilege;
use App\Attributes\Resource;
use App\Attributes\Secured;
use App\Forms\ArticleFormFactory;
use App\Forms\MetaValueFormFactory;
use App\Model\Articles;
use App\Model\Categories;
use App\Model\Files;
use App\Modules\CommentsModule;
use App\Modules\UrlModule\UrlManager;
use App\Service\Article;
use App\Service\LanguageService;
use App\Service\Meta;
use App\Service\Tag;
use Contributte\Datagrid\Datagrid;
use Nette\Application\Attributes\Persistent;
use Nette\Application\ForbiddenRequestException;
use Nette\Application\UI\Form;
use Nette\Utils\ArrayHash;

/**
 * Article Presenter
 */
#[Secured]
#[Resource('Articles')]
#[Privilege('view')]
class ArticlesPresenter extends BasePresenter
{
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
	 * Show filter
	 * @persistentInDefault
	 */
	public ?string $show;

	/**
	 * Actual language
	 */
	public string $actualLanguage;

	/** @inject */
	public ArticleFormFactory $articleFactory;

	/** @inject */
	public MetaValueFormFactory $metaValueFactory;

	/** @inject */
	public LanguageService $languages;

	/** @inject */
	public Articles $articlesModel;

	/** @inject */
	public Article $articleService;

	/** @inject */
	public Categories $categoriesModel;

	/** @inject */
	public Files $filesModel;

	/** @inject */
	public Tag $tagService;

	/** @inject */
	public UrlManager $urlManager;


	protected function startup(): void
	{
		parent::startup();

		$this->addBreadCrumbLink("Articles", $this->link(":Admin:Articles:default", array("id" => null)) );

		//default language
		if($this->language == $this->languages->getDefaultLanguage()) {
			$this->redirect("this", array("language" => null));
		}
	}


	#[Secured]
	#[Resource('Articles')]
	#[Privilege('view')]
	public function actionDefault(string $show = null): void
	{
		$this->show = $show;
		if (!is_null($this->show) && !in_array($this->show, array("personal","pending","trash"))) {
			throw  new \InvalidArgumentException("Parameter show '$this->show' is not permited");
		}
		$this->template->show = $this->show;

		$counts = array(
			"all"=>$this->articlesModel->findAll()
				->select("COUNT(*) AS count")
				->where($this->articlesModel->getTableName().".status != ?","trash")
				->where($this->articlesModel->getTableName().".historyId", null)->fetch()?->count,
			"personal"=>$this->articlesModel->findAll()
				->select("COUNT(*) AS count")
				->where($this->articlesModel->getTableName().".createdBy = ?", $this->user->getId())
				->where($this->articlesModel->getTableName().".historyId", null)->fetch()?->count,
			"pending"=>$this->articlesModel->findAll()
				->select("COUNT(*) AS count")
				->where($this->articlesModel->getTableName().".status = ?","pending")
				->where($this->articlesModel->getTableName().".historyId", null)->fetch()?->count,
			"trash"=>$this->articlesModel->findAll()
				->select("COUNT(*) AS count")
				->where($this->articlesModel->getTableName().".status = ?","trash")
				->where($this->articlesModel->getTableName().".historyId", null)->fetch()?->count
			);
		$this->template->counts = $counts;
	}


	/**
	 * @SecuredInside
	 * @Resource (Articles)
	 * @Privilege (edit)
	 */
	public function actionDetail(int $parent = null): void
	{
		if ($this->languages->existLanguage($this->language)) {
			$this->actualLanguage = $this->language == null ? $this->languages->getDefaultLanguage() : $this->language;
		} else {
			$this->actualLanguage = $this->languages->getDefaultLanguage();
		}

		$this->template->articles = $this->articlesModel;

		//edit own
		if($this->id) {
			$articleInfo = $this->articlesModel->getById($this->id);
			if(!$this->user->isAllowed(new \App\Security\Resource("Articles", $articleInfo ? $articleInfo->createdBy : $this->getUser()->getId()), "edit")) {
				throw new ForbiddenRequestException("You have not access to 'Articles' with priviledge 'edit'.");
			}
		}



		if($this->id)
		{
			$articleTranslationInfo = $this->articlesModel->findTranslationBy($this->id, $this->actualLanguage)->fetch();
			if($articleTranslationInfo) {
				$this->addBreadCrumbLink($articleTranslationInfo->title, $this->link(":Admin:Articles:detail", array("id" => $this->id)), null, false);
			} else {
				$this->addBreadCrumbLink("New translation", $this->link(":Admin:Articles:detail", array("id" => $this->id)));
			}
		}
		else
		{
			$this->addBreadCrumbLink("New", $this->link(":Admin:Articles:detail", array("id" => null)));
		}

		//revisionCount
		$this->template->revisionCount = $this->id ? $this->articleService->getRevisionsCount($this->id) : 0;
	}


	public function renderDetail(): void
	{
		$this->template->languages = $this->languages->getLanguages();
		$this->template->actualLanguage = $this->actualLanguage;

		if($this->id){
			$files = $this->articlesModel->getRelationFile($this->id);
			$this->template->files = array();
			foreach ($files as $file){
				$this->template->files[] = $this->filesModel->toFileEntity($file);
			}

			//revisionList
			$this->template->revisionsList = $this->articleService->getRevisions($this->id);
			$this->template->id = $this->id;
		}
	}


	/**
	 * Articles grid
	 */
	protected function createComponentArticlesGrid(): Datagrid
	{
		$source = $this->articlesModel->findAll()
			->select($this->articlesModel->getTableName().".*")
			->where($this->articlesModel->getTableName().".historyId", null)
			->order($this->articlesModel->getTableName().".createDate DESC")
			->order($this->articlesModel->getTableName().".".$this->articlesModel->getColumnId());
		$this->articlesModel->selectTitle($source, "`" . $this->articlesModel->getTableName() . "`.`id`", $this->language);
		//název hlavní kategorie článku
		$this->categoriesModel->selectTitle($source, "(SELECT `relation`.`categoryId` FROM `" . Categories::RELATION_ARTICLE_TABLE_NAME . "` `relation`"
			. " WHERE `relation`.`articleId` = `" . $this->articlesModel->getTableName() . "`.`id` ORDER BY `relation`.`isMain` DESC LIMIT 1)", $this->language, "categoryTitle");
		switch ($this->show) {
			case "personal":
				$source->where($this->articlesModel->getTableName().".createdBy = ?", $this->user->getId());
				break;
			case "pending":
				$source->where($this->articlesModel->getTableName().".status = ?","pending");
				break;
			case "trash":
				$source->where($this->articlesModel->getTableName().".status = ?","trash");
				break;

			case null:
			default:
			$source->where($this->articlesModel->getTableName().".status != ?","trash");
				break;
		}

		$primaryKey = $this->articlesModel->getColumnId();
		$paramKey = $this->articlesModel->getForeignKeyColumn();

		$grid = new Datagrid();
		$grid->setPrimaryKey($primaryKey);
		$grid->setDataSource($source);
		$grid->setTranslator($this->translator);

		//active
		$active_column = $grid->addColumnStatus('active', 'A.');
		$active_column->getElementPrototype("th")->setTitle($this->translator->translate("Active"));
		$active_column->addOption(0, 'Unactive') // show if status == 0
		->setClass('btn-danger')
			->setIcon('ban')
			->setTitle('Set as active');
		$active_column->addOption(1, 'Active') // show if status == 1
		->setClass('btn-success')
			->setIcon('check-circle')
			->setTitle('Set as unactive');
		$active_column->onChange[] = function($id, $value) {
			$this->handleActivate((int) $id, (bool) $value);
		};

		/*$activateButton
			->setCallbackArguments(array($activateButton))
			->setCallback(function ($row, $selfButton) {
				/* @var $selfButton \Mesour\Datagrid\Components\StatusButton * /
				if (!$this->user->isAllowed(new \App\Security\Resource("Articles", $row["createdBy"]), "edit")) {
					$selfButton->setDisabled();
				} else {
					$selfButton->setDisabled(false);
				}
				if ($selfButton->getStatus() == $row["active"]) {
					return $selfButton;
				}
			});
		$deactivateButton = $active_column->addButton()
			->setStatus('1') // show if status == 1
			->setType('btn-success')
			//->setClassName('ajax')
			->setIcon('fa fa-check-circle')
			->setTitle('Set as unactive (active)')
			->setAttribute('href', new Link('activate!', array(
				$paramKey => '{' . $primaryKey . '}',
				'status' => 0
			)));
		$deactivateButton
			->setCallbackArguments(array($deactivateButton))
			->setCallback(function ($row, $selfButton) {
				/* @var $selfButton \Mesour\Datagrid\Components\StatusButton * /
				if (!$this->user->isAllowed(new \App\Security\Resource("Articles", $row["createdBy"]), "edit")) {
					$selfButton->setDisabled();
				} else {
					$selfButton->setDisabled(false);
				}
				if ($selfButton->getStatus() == $row["active"]) {
					return $selfButton;
				}
			});*/

		$grid->addColumnText("title", "Name");

		$grid->addColumnText("categoryTitle", "Category");

		$grid->addColumnDateTime("createDate", "Create date")
			->setFormat(DATETIME_FORMAT);

		//Actions
		if($this->show == "pending"){
			$grid->addAction('approve', 'Approve', 'approve!', array($paramKey => $primaryKey))
				->setClass(function($item) {
					return 'btn btn-success btn-sm ajax'.(!$this->user->isAllowed("Articles", "approve_article") ? ' disabled' : '');
				})
				->setIcon('check')
				->setTitle('Edit')
				->addAttributes(array(
					'data-bs-toggle' => 'modal',
					"data-bs-target" => "#confirm-modal",
					"data-confirm-text" => $this->translator->translate('Approve?'),
				));
		}
		$grid->addAction('edit', 'Edit', 'detail', array('id' => $primaryKey))
			->setClass(function($item) {
				return 'btn btn-primary btn-sm'.(!$this->user->isAllowed(new \App\Security\Resource("Articles", $item["createdBy"]), "edit") ? ' disabled' : '');
			})
			->setIcon(ICON_EDIT)
			->setTitle('Edit');

		$grid->addAction('delete', 'Delete', 'delete!', array($paramKey => $primaryKey))
			->setClass(function($item) {
				return 'btn btn-danger btn-sm ajax'.(!$this->user->isAllowed(new \App\Security\Resource("Articles", $item["createdBy"]), "edit") ? ' disabled' : '');
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
	 * Categories grid
	 * @param string $name
	 * @return Datagrid
	 * @throws \LiveTranslator\TranslatorException
	 * @throws \Contributte\Datagrid\Exception\DatagridColumnStatusException
	 * @throws \Contributte\Datagrid\Exception\DatagridException
	 */
	protected function createComponentCategoriesGrid($name)
	{
		$source = $this->articlesModel->getRelationCategory($this->id);
		$this->categoriesModel->selectTitle($source, "`" . Categories::RELATION_ARTICLE_TABLE_NAME . "`.`categoryId`", $this->language)
			->order("title");
		$primaryKey = "categoryId";

		$grid = new Datagrid($this, $name);
		$grid->setPrimaryKey($primaryKey);
		$grid->setDataSource($source);
		$grid->setTranslator($this->translator);

		//isMain
		$mainColumn = $grid->addColumnStatus('isMain', 'M.');
		$mainColumn->getElementPrototype("th")->setTitle($this->translator->translate("Main"));
		$mainColumn->addOption(0, 'Not main') // show if status == 0
			->setClass('btn-danger ajax')
			->setIcon('ban')
			->setTitle('Set as main');
		$mainColumn->addOption(1, 'Main') // show if status == 1
			->setClass('btn-success')
			->setIcon('check-circle')
			->setTitle('Set as unactive');
		$mainColumn->onChange[] = function($id, $value) {
			$this->handleSetCategoryAsMain((int) $id);
		};

		$grid->addColumnText("title", "Name");

		//Actions
		$grid->addAction('delete', 'Delete', 'removeCategory!', array($primaryKey => $primaryKey))
			->setClass(function($item) {
				return 'btn btn-danger btn-sm ajax float-right'.($item->default ? ' disabled' : '');
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
	 * Category Selection grid
	 * @param string $name
	 * @return Datagrid
	 * @throws \Contributte\Datagrid\Exception\DatagridException
	 */
	protected function createComponentCategorySelectionGrid($name)
	{
		$source = $this->categoriesModel->getAllForMenu()
			->select($this->categoriesModel->getTableName() . ".*");
		$this->categoriesModel->selectTitle($source, "`" . $this->categoriesModel->getTableName() . "`.`id`", $this->language)
			->order("title");
		$primaryKey = $this->categoriesModel->getColumnId();
		$paramKey = $this->categoriesModel->getForeignKeyColumn();

		$grid = new Datagrid($this, $name);
		$grid->setPrimaryKey($primaryKey);
		$grid->setDataSource($source);
		$grid->setTranslator($this->translator);

		$grid->addColumnText("title", "Name");

		//Actions
		$grid->addAction('edit', 'Add', 'addCategory!', array($paramKey => $primaryKey))
			->setClass('btn btn-primary btn-sm ajax float-right')
			->setIcon('plus')
			->setTitle('Add')
			->addAttributes(array(
				"data-bs-toggle" => "modal",
				"data-bs-target" => "#modal"
			));

		return $grid;
	}


	/**
	 * Sign-up form factory.
	 */
	protected function createComponentArticleForm(): Form
	{
		$form = $this->articleFactory->create(
			$this->id,
			$this->actualLanguage,
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
		$this->metaValueFactory->setType(Meta::TYPE_ARTICLE);
		$form = $this->metaValueFactory->create($this->id, array($this, "link"), $this->actualLanguage);
		$form->setTranslator($this->translator);

		$form->getElementPrototype()->addClass("ajax");

		$form->onSuccess[] = function ($form) {
			$form->getPresenter()->redrawControl("metas");
		};

		return $form;
	}


	/**
	 * Activate
	 * @param int $articleId
	 * @param boolean $status
	 * @SecuredInside
	 * @Resource(Articles)
	 * @Privilege(edit)
	 */
	public function handleActivate(int $articleId, $status = 0): void
	{
		//edit own
		$createdBy = $this->articlesModel->getById($articleId)?->createdBy;
		if(!$this->user->isAllowed(new \App\Security\Resource("Articles", $createdBy),"edit")){
			throw new ForbiddenRequestException("You have not access to 'Articles' with priviledge 'edit'.");
		}
		$this->articleService->makeBackup($articleId);
		$this->articlesModel->update($articleId, array("active" => (boolean) $status));
		$this->flashMessage(SUCCESS_SAVE, FLASH_SUCCESS);
		$this->redirect('this');
	}


	/**
	 * Approve
	 * @param int $articleId
	 */
	#[Secured]
	#[Resource('Articles')]
	#[Privilege('approve_article')]
	public function handleApprove(int $articleId): void
	{
		$this->articleService->makeBackup($articleId);
		$this->articlesModel->statusPublish($articleId);
		$this->flashMessage(SUCCESS_SAVE, FLASH_SUCCESS);
		$this->redirect('this');
	}


	/**
	 * Delete handler
	 * @param int $articleId
	 */
	#[Secured]
	#[Resource('Articles')]
	#[Privilege('delete')]
	public function handleDelete(int $articleId): void
	{
		$this->articlesModel->delete($articleId);
		$this->flashMessage(SUCCESS_DELETE, FLASH_SUCCESS);

		$this->flashMessage(FAIL_DELETE, FLASH_FAILED);

		$this->redirect('this');
	}


	/**
	 * Validate URL handler
	 */
	public function handleValidateUrl($text): void
	{
		$this->payload->url = $this->urlManager->validateUrl($text, "article", $this->id);

		$this->sendPayload();
	}


	/**
	 * Add Images handler
	 */
	#[Secured]
	#[Resource('Articles')]
	#[Privilege('add')]
	public function handleAddImage(array $files): void
	{
		foreach ($files as $fileId){
			$this->articlesModel->insertRelationFile($this->id, $fileId);
		}

		$this->redrawControl("files");
	}


	/**
	 * Delete file handler
	 */
	#[Secured]
	#[Resource('Articles')]
	#[Privilege('delete')]
	public function handleRemoveImage(int $fileId): void
	{
		$this->articlesModel->deleteRelationFile($this->id, $fileId);

		if($this->isAjax()){
			$this->redrawControl("files");
		} else {
			$this->redirect('this');
		}
	}


	/**
	 * Add Category handler
	 */
	#[Secured]
	#[Resource('Articles')]
	#[Privilege('add')]
	public function handleAddCategory(int $categoryId): void
	{
		$this->categoriesModel->insertRelationArticle($categoryId, $this->id, new ArrayHash());

		$this->redrawControl("categories");
	}


	/**
	 * Delete Category handler
	 */
	#[Secured]
	#[Resource('Articles')]
	#[Privilege('delete')]
	public function handleRemoveCategory(int $categoryId): void
	{
		$this->categoriesModel->deleteRelationArticle($categoryId, $this->id);

		if($this->isAjax()){
			$this->redrawControl("categories");
		} else {
			$this->redirect('this');
		}
	}


	/**
	 * SetCategoryAsMain
	 * @param int $categoryId
	 */
	#[Secured]
	#[Resource('Articles')]
	#[Privilege('edit')]
	public function handleSetCategoryAsMain(int $categoryId): void
	{
		$this->categoriesModel->setRelationArticleAsMain($categoryId, $this->id);
		$this->flashMessage(SUCCESS_SAVE, FLASH_SUCCESS);

		if($this->isAjax()){
			$this->redrawControl("categories");
		} else {
			$this->redirect('this');
		}
	}


	/**
	 * Load tags handler
	 * @param string $query
	 */
	#[Secured]
	#[Resource('Articles')]
	#[Privilege('edit')]
	public function handleLoadTags($query): void
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
