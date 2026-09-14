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
	private int $parent;

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
			$article = $this->articlesModel->getAllWithTranslation($this->language)->where("article.article_id", $this->id)->fetch();
			if(!$article){
				throw new Nette\Application\BadRequestException("Article with url '$this->id' doesn't exist.");
			}

			//comments restriction
			$this->comments->whereArticle($article->article_id);
		}
	}


	public function renderDetail(): void
	{
		if($this->id){
			$article = $this->articlesModel->getAllWithTranslation($this->language)->where("article.article_id", $this->id)->fetch();
			if(!$article){
				throw new Nette\Application\BadRequestException("Article with url '$this->id' doesn't exist.");
			}
			//breadcrumb
			$mainCategory = $this->articlesModel->getRelationCategory($article->article_id)->where("is_main", true)->fetch();
			if($mainCategory){
				//all parents
				$parentTree = $this->categoriesModel->getAllParents($mainCategory->category_id, $this->language);
				foreach (array_reverse($parentTree) as $parent) {
					//breadcrumb
					$this->addBreadCrumbLink($parent->title, $this->link(":Front:Categories:detail", array("id" => $parent->category_id)), null, false );
				}
				/*/self main category
				$mainCategoryTranslation = $this->categoriesModel->findTranslationBy($mainCategory->category_id, $this->language)->fetch();
				$this->addBreadCrumbLink($mainCategoryTranslation->title, $this->link(":Front:Categories:detail", array("url" => $mainCategoryTranslation->url)), null, false );
				 */

				$this["menu"]->setActiveMenuItem($mainCategory->category_id);
			}
			//breadcrumb
			$this->addBreadCrumbLink($article->title, $this->link(":Front:Articles:detail", array("url" => $this->id)), null, false );

			$articleInfo = \Nette\Utils\ArrayHash::from($article->toArray());
			$files = $this->articlesModel->getRelationFile($article->article_id);
			$articleInfo->mainFile = null;
			$articleInfo->files = array();
			foreach ($files as $file){
				$fileEntity = $this->filesModel->toFileEntity($file);

				if($file["is_main"]){
					$articleInfo->mainFile = $fileEntity;
				}
				$articleInfo->files[] = $fileEntity;
			}

			//author
			$user = $this->usersModel->findById($articleInfo->created_by)->fetch()->toArray();
			$user['author'] = $this->userManager->makeName($user);
			$articleInfo->author = $user;

			//meta
			$metas = $this->metaService->getStructureByColumnId(CommentsModule\Meta::TYPE_ARTICLE, $this->language, $article->article_id);
			$articleInfo->metas = $metas;

			//tags
			$tags = $this->tagService->getRelationTags(CommentsModule\Tag::TYPE_ARTICLE, $this->language, $article->article_id);
			$articleInfo->tags = $tags;

			$this->template->article = $articleInfo;

			//set SEO
			$this->setSEO($articleInfo["seo_title"], $articleInfo["seo_description"], $articleInfo["seo_keywords"]);

			//set links to languageChanger
			$lanuageItems = $this->articlesModel->getAllWithTranslation()->where("article.".$this->articlesModel->getColumnId(), $article->article_id)->fetchAll();
			foreach ($lanuageItems as $lanuageItem) {
				$this->languageChanger->setLinkForLanguage($lanuageItem->language_id, $this->link("this", array(
					"id"=>$lanuageItem->article_id,
					"locale"=>$lanuageItem->language_id
				)));
			}

			//viewCounter
			$this->viewCounter->itemViewed(ViewCounter::TYPE_ARTICLE, $article->article_id, $this->language);

		}
	}

}
