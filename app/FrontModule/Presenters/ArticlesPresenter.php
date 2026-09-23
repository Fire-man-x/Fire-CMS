<?php
declare(strict_types=1);

namespace App\FrontModule\Presenters;

use App\Components\ViewCounter;
use App\Forms\ArticleFormFactory;
use App\Model;
use App\Modules\CommentsModule;
use App\Modules\UrlModule\UrlManager;
use App\Service\Meta;
use App\Service\Tag;
use Nette;
use Nette\Application\Attributes\Persistent;


class ArticlesPresenter extends BasePresenter
{

	/**
	 * Id
	 */
	#[Persistent]
	public int $id;

	/**
	 * Article parent
	 */
	private ?int $parent = null;

	/**
	 * Actual language
	 */
	public string $actualLanguage;

	/** @inject */
	public ArticleFormFactory $articleFactory;

	/** @inject */
	public Model\Articles $articlesModel;

	/** @inject */
	public Meta $metaService;

	/** @inject */
	public Tag $tagService;

	/** @inject */
	public Model\Categories $categoriesModel;

	/** @inject */
	public Model\Files $filesModel;

	/** @inject */
	public Model\Users $usersModel;

	/** @inject */
	public Model\UserManager $userManager;

	/** @inject */
	public UrlManager $urlManager;

	/** @inject */
	public ViewCounter $viewCounter;


	protected function startup(): void
	{
		parent::startup();
	}


	public function actionDetail($parent = null): void
	{
		$this->parent = $parent;

		$this->template->articles = $this->articlesModel;

		//@todo: udelat jenom jedno nacteni
		if($this->id){
			$article = $this->articlesModel->getAllWithTranslation($this->language)->where("article.id", $this->id)->fetch();
			if(!$article){
				throw new Nette\Application\BadRequestException("Article with url '$this->id' doesn't exist.");
			}

			//comments restriction
			$this->comments->whereArticle($article->articleId);
		}
	}


	public function renderDetail(): void
	{
		if($this->id){
			$article = $this->articlesModel->getAllWithTranslation($this->language)->where("article.id", $this->id)->fetch();
			if(!$article){
				throw new Nette\Application\BadRequestException("Article with url '$this->id' doesn't exist.");
			}
			//breadcrumb
			$mainCategory = $this->articlesModel->getRelationCategory($article->articleId)->where("isMain", true)->fetch();
			if($mainCategory){
				//all parents
				$parentTree = $this->categoriesModel->getAllParents($mainCategory->categoryId, $this->language);
				foreach (array_reverse($parentTree) as $parent) {
					//breadcrumb
					$this->addBreadCrumbLink($parent->title, $this->link(":Front:Categories:detail", array("id" => $parent->categoryId)), null, false );
				}
				/*/self main category
				$mainCategoryTranslation = $this->categoriesModel->findTranslationBy($mainCategory->categoryId, $this->language)->fetch();
				$this->addBreadCrumbLink($mainCategoryTranslation->title, $this->link(":Front:Categories:detail", array("url" => $mainCategoryTranslation->url)), null, false );
				 */

				$this["menu"]->setActiveMenuItem($mainCategory->categoryId);
			}
			//breadcrumb
			$this->addBreadCrumbLink($article->title, $this->link(":Front:Articles:detail", array("url" => $this->id)), null, false );

			$articleInfo = \Nette\Utils\ArrayHash::from($article->toArray());
			$files = $this->articlesModel->getRelationFile($article->articleId);
			$articleInfo->mainFile = null;
			$articleInfo->files = array();
			foreach ($files as $file){
				$fileEntity = $this->filesModel->toFileEntity($file);

				if($file["isMain"]){
					$articleInfo->mainFile = $fileEntity;
				}
				$articleInfo->files[] = $fileEntity;
			}

			//author
			$user = $this->usersModel->getById($articleInfo->createdBy)?->toArray();
			$user['author'] = $this->userManager->makeName($user);
			$articleInfo->author = $user;

			//meta
			$metas = $this->metaService->getStructureByColumnId(Meta::TYPE_ARTICLE, $this->language, $article->articleId);
			$articleInfo->metas = $metas;

			//tags
			$tags = $this->tagService->getRelationTags(Tag::TYPE_ARTICLE, $this->language, $article->articleId);
			$articleInfo->tags = $tags;

			$this->template->article = $articleInfo;

			//set SEO
			$this->setSEO($articleInfo["seoTitle"], $articleInfo["seoDescription"], $articleInfo["seoKeywords"]);

			//set links to languageChanger
			$lanuageItems = $this->articlesModel->getAllWithTranslation()->where("article.".$this->articlesModel->getColumnId(), $article->articleId)->fetchAll();
			foreach ($lanuageItems as $lanuageItem) {
				$this->languageChanger->setLinkForLanguage($lanuageItem->languageId, $this->link("this", array(
					"id"=>$lanuageItem->articleId,
					"locale"=>$lanuageItem->languageId
				)));
			}

			//viewCounter
			$this->viewCounter->itemViewed(ViewCounter::TYPE_ARTICLE, $article->articleId, $this->language);

		}
	}

}
