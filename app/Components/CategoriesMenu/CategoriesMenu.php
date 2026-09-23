<?php
declare(strict_types=1);

namespace App\Components\CategoriesMenu;

use App\Attributes\Privilege;
use App\Attributes\Resource;
use App\Attributes\Secured;
use App\Model;
use App\Service\Category;
use Nette\Application\ForbiddenRequestException;
use Nette\Application\UI\Control;

/**
 * Class CategoriesMenu
 *
 * CategoriesMenu Component
 */
class CategoriesMenu extends Control
{
	private string $templateFile;

	private array $categories;

	private ?int $activeCategory;

	/** Jazyk administrace, ve kterém se zobrazují názvy kategorií (null = výchozí jazyk webu) */
	private ?string $language = null;


	/**
	 * CategoriesMenu
	 */
	public function __construct(
		private Model\Categories $categoriesModel,
		private Category $categoriesService,
		private \Nette\Localization\Translator $translator)
	{
	}


	public function setActiveCategory($activeCategory): self
	{
		$this->activeCategory = $activeCategory;
		return $this;
	}


	public function setLanguage(?string $language): self
	{
		$this->language = $language;
		return $this;
	}



	public function customTemplate(string $template = null): void
	{
		$this->templateFile = $template ?: __DIR__ . '/CategoriesMenu.latte';
	}


	/**
	 * Render function
	 */
	public function render(): void
	{
		$this->customTemplate();

		$this->template->setFile($this->templateFile);

		$categories = $this->categoriesModel->getAllForMenu()
			->select($this->categoriesModel->getTableName() . ".*");
		$this->categoriesModel->selectTitle($categories, "`" . $this->categoriesModel->getTableName() . "`.`id`", $this->language);
		$this->categories = $this->createTree($categories->fetchAll());

		$this->template->categories = $this->categories;
		$this->template->activeCategory = $this->activeCategory;

		$this->template->setTranslator($this->translator);
		$this->template->render();
	}


	/**
	 * Create tree
	 */
	private function createTree($items, $parent=null, $level=0): array
	{
		$tree = array();
		foreach ($items as $itemId => $item){
			if($item->parentId == $parent){
				$category = $item->toArray();
				unset($items[$itemId]);
				$category['childs'] = $this->createTree($items, $category['id'], $level+1);
				$tree[] = \Nette\Utils\ArrayHash::from($category, false);
			}
		}
		return $tree;
	}


	/**
	 * Decode tree to array
	 */
	private function treeToArray(&$toArray, $items, $parent=null, $level=0): void
	{
		foreach ($items as $position => $item){
			$toArray[] = array(
				"id"=>$item["id"],
				"parentId"=>$parent,
				"position"=>$position,
				"level"=>$level
			);

			if(isset($item["children"])){
				$this->treeToArray($toArray, $item["children"], $item["id"], $level+1);
			}
		}
	}


	/**
	 * Sort menu handler
	 */
	public function handleSortMenu(array $items): void
	{
		$updateItems = array();
		$this->treeToArray($updateItems, $items);

		$this->categoriesService->updateTreePositions($updateItems);

		$this->getPresenter()->terminate();
	}


	/**
	 * Sort menu handler
	 */
	public function recalculateLeftRight(): void
	{
		$items = $this->createTree($this->categoriesModel->findAll()->fetchAll());

		$updateItems = array();
		$this->treeToArray($updateItems, $items);

		$this->categoriesService->updateTreePositions($updateItems);

		$this->getPresenter()->terminate();
	}


	/**
	 * Delete category handler
	 */
	#[Secured]
	#[Resource('Categories')]
	#[Privilege('delete')]
	public function handleRemoveCategory(int $categoryId): void
	{
		//delete
		try
		{
			$redirectToCategoryId = $this->categoriesService->delete($categoryId);
		}catch(ForbiddenRequestException $e){
			$this->getPresenter()->flashMessage($e->getMessage(), FLASH_FAILED);
			$this->redirect('this');
		}

		//redirect
		$redirectToId = null;
		if($this->getPresenter()->id == $categoryId){
			$redirectToId = $redirectToCategoryId;
		}

		$this->getPresenter()->flashMessage(SUCCESS_DELETE, FLASH_SUCCESS);
		$this->getPresenter()->redirect("this", array("id"=>$redirectToId));
	}

}