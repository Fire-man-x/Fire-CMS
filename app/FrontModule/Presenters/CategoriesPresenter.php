<?php
declare(strict_types=1);

namespace App\FrontModule\Presenters;

use App\Components\ViewCounter;
use App\Model;
use App\Modules\CommentsModule;
use App\Service\Meta;
use App\Service\Tag;
use Nette;
use Nette\Application\Attributes\Persistent;


class CategoriesPresenter extends BasePresenter
{

	/**
	 * Id
	 */
	#[Persistent]
	public int $id;

	/** @inject */
	public Model\Categories $categoriesModel;

	/** @inject */
	public Meta $metaService;

	/** @inject */
	public Tag $tagService;

	/** @inject */
	public Model\Users $usersModel;

	/** @inject */
	public Model\Files $filesModel;

	/** @inject */
	public Model\UserManager $userManager;

	/** @inject */
	public ViewCounter $viewCounter;


	protected function startup(): void
	{
		parent::startup();
	}


	public function actionDefault(): void
	{
		$this->template->categories = $this->categoriesModel;
	}


	public function renderDetail(): void
	{
		$category = $this->categoriesModel->getAllWithTranslation($this->language)
			->where("active", true)
			->where("category.id", $this->id)->fetch();
		if(!$category || !$this->id){
			throw new Nette\Application\BadRequestException("Category with url '$this->id' doesn't exist.");
		}

		//Not logged in, so redirect to sign
		if ($category->public == 0 && !$this->getUser()->isLoggedIn() && !$this->isLinkCurrent(":Front:Sign:*")) {
			$this->forward(':Front:Sign:inFast', array('id' => null, 'backlink' => $this->isLinkCurrent(":Front:Homepage:default") ? null : $this->storeRequest()));
		}

		//parent breadcrumb
		$parentTree = $category->parentId !== null ? $this->categoriesModel->getAllParents((int) $category->parentId, $this->language) : [];
		foreach (array_reverse($parentTree) as $parent) {
			//breadcrumb
			$this->addBreadCrumbLink($parent->title, $this->link(":Front:Categories:detail", array("id" => $parent->categoryId)), null, false );
		}
		//breadcrumb
		$this->addBreadCrumbLink($category->title, $this->link(":Front:Categories:detail", array("id" => $this->id)), null, false );

		//change to object
		$categoryInfo = Nette\Utils\ArrayHash::from($category->toArray());


		//author
		$user = $this->usersModel->getById($categoryInfo->createdBy)?->toArray();
		$user['author'] = $this->userManager->makeName($user);
		$categoryInfo->author = $user;

		//meta
		$meta = $this->metaService->getStructureByColumnId(Meta::TYPE_CATEGORY, $this->language, $category->categoryId);
		$categoryInfo->metas = $meta;

		//tags
		$tags = $this->tagService->getRelationTags(Tag::TYPE_CATEGORY, $this->language, $category->categoryId);
		$categoryInfo->tags = $tags;

		$this->template->category = $categoryInfo;
		$files = $this->categoriesModel->getRelationFile($category->categoryId);
		$this->template->files = array();
		foreach ($files as $file){
			$this->template->files[] = $this->filesModel->toFileEntity($file);
		}

		//set SEO
		$this->setSEO($categoryInfo["seoTitle"], $categoryInfo["seoDescription"], $categoryInfo["seoKeywords"]);

		//set menu item
		$this->menu->setActiveMenuItem($category->categoryId);

		//set links to languageChanger
		$lanuageItems = $this->categoriesModel->getAllWithTranslation()->where("category.".$this->categoriesModel->getColumnId(), $category->categoryId)->fetchAll();
		foreach ($lanuageItems as $lanuageItem) {
			$this->languageChanger->setLinkForLanguage($lanuageItem->languageId, $this->link("this", array(
				"id"=>$lanuageItem->categoryId,
				"locale"=>$lanuageItem->languageId
				)));
		}

		//viewCounter
		$this->viewCounter->itemViewed(ViewCounter::TYPE_CATEGORY, $category->categoryId, $this->language);

		//comments restriction
		$this->comments->whereCategory($category->categoryId);
	}
}
