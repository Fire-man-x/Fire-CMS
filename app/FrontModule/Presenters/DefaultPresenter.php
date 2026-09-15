<?php
declare(strict_types=1);

namespace App\FrontModule\Presenters;

use App\Forms\CategoryFormFactory;
use App\Model;
use Nette;


class DefaultPresenter extends BasePresenter
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
			->where("history_id", null)
			->where("type", "homepage")->fetch();
		if(!$category){
			throw new Nette\Application\BadRequestException("Category homepage doesn't exist.");
		}
		//breadcrumb
		$this->addBreadCrumbLink($category->title, $this->link(":Front:Default:default"), null, false );

		$category = Nette\Utils\ArrayHash::from($category->toArray());

		//author
		$user = $this->usersModel->getById($category->created_by)?->toArray();
		$user['author'] = $this->userManager->makeName($user);
		$category->author = $user;

		$this->template->category = $category;
		$files = $this->categoriesModel->getRelationFile($category->category_id);
		$this->template->files = array();
		foreach ($files as $file){
			$this->template->files[] = $this->filesModel->toFileEntity($file);
		}

		//set menu item
		$this->menu->setActiveMenuItem($category->category_id);

		//set links to languageChanger
		$lanuageItems = $this->categoriesModel->getAllWithTranslation()->where("category.".$this->categoriesModel->getColumnId(), $category->category_id)->fetchAll();
		foreach ($lanuageItems as $lanuageItem) {
			$this->languageChanger->setLinkForLanguage($lanuageItem->language_id, $this->link("this", array(
				//"url"=>$lanuageItem->url,
				"locale"=>$lanuageItem->language_id
				)));
		}

	}
}
