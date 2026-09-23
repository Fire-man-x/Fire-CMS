<?php
declare(strict_types=1);

namespace App\FrontModule\Presenters;

use App\Forms\CategoryFormFactory;
use App\Model;
use Nette;


class HomepagePresenter extends BasePresenter
{

	/** @inject */
	public CategoryFormFactory $categoryFactory;

	/** @inject */
	public Model\Categories $categoriesModel;

	/** @inject */
	public Model\Files $filesModel;

	/** @inject */
	public \App\Components\FileManager\FileManager $fileManager;

	/** @inject */
	public Model\Users $usersModel;

	/** @inject */
	public Model\UserManager $userManager;


	protected function startup(): void
	{
		parent::startup();

		$this->template->__imagestore = $this->fileManager;
	}


	public function actionDefault(): void
	{
	}


	public function renderDefault(): void
	{
		$category = $this->categoriesModel->getAllWithTranslation($this->language)
			->where("active", true)
			->where("historyId", null)
			->where("type", "homepage")->fetch();
		if(!$category){
			throw new Nette\Application\BadRequestException("Category homepage doesn't exist.");
		}
		//breadcrumb
		$this->addBreadCrumbLink($category->title, $this->link(":Front:Homepage:default"), null, false );

		$category = Nette\Utils\ArrayHash::from($category->toArray());

		//author
		$user = $this->usersModel->getById($category->createdBy)?->toArray();
		$user['author'] = $this->userManager->makeName($user);
		$category->author = $user;

		$this->template->category = $category;
		$files = $this->categoriesModel->getRelationFile($category->categoryId);
		$this->template->files = array();
		foreach ($files as $file){
			$this->template->files[] = $this->filesModel->toFileEntity($file);
		}

		//set menu item
		$this->menu->setActiveMenuItem($category->categoryId);

		//set links to languageChanger
		$lanuageItems = $this->categoriesModel->getAllWithTranslation()->where("category.".$this->categoriesModel->getColumnId(), $category->categoryId)->fetchAll();
		foreach ($lanuageItems as $lanuageItem) {
			$this->languageChanger->setLinkForLanguage($lanuageItem->languageId, $this->link("this", array(
				//"url"=>$lanuageItem->url,
				"locale"=>$lanuageItem->languageId
				)));
		}

	}
}
