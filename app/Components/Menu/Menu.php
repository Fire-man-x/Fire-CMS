<?php
declare(strict_types=1);

namespace App\Components\Menu;

use App\Components\Menu\Model\Menus;
use App\Components\Shortcodes;
use App\Modules\UrlModule\UrlManager;
use App\Model;
use Nette\Application\UI\Control;

/**
 * Class CategoriesMenu
 *
 * CategoriesMenu Component
 */
class Menu extends Control
{
	private string $templateFile;

	private \Nette\Database\Table\Selection $firstLevelCategories;

	private array $subCategories;

	private string $language;

	private int $activeMenuItem;

	private array $circularDetector;


	/**
	 * CategoriesMenu
	 */
	public function __construct(
		private Menus $menusModel,
		private Model\Categories $categoriesModel,
		private \Nette\Localization\Translator $translator,
		private Shortcodes $shortcodes)
	{
	}


	public function setLanguage($language)
	{
		$this->language = $language;
		return $this;
	}


	public function setActiveMenuItem($activeCategory)
	{
		$this->activeMenuItem = $activeCategory;
		return $this;
	}



	public function customTemplate(string $template = null): void
	{
		$this->templateFile = $template ?: __DIR__ . '/Menu.latte';
	}


	/**
	 * Render function
	 */
	public function render($location = null, $maxSublevel = null, $menuClass = "", $itemClass = "")
	{
		$this->customTemplate();

		$this->template->setFile($this->templateFile);

		$menuExist = $this->menusModel->findAll()
			->where("location", $location)
			->where("active", true)->fetch();
		if(!$menuExist){
			echo $this->translator->translate("Menu doesn't exist.");
			return;
		}
		$this->firstLevelCategories = $this->menusModel->getAllMenuItemsWithTranslation($this->language, $location)->fetchAssoc("category_id");
		if(!$this->firstLevelCategories){
			echo $this->translator->translate("No item.");
			return;
		}

		//all categories
		$this->subCategories = $this->categoriesModel->getAllWithTranslation($this->language)
			->where("category.active", true)
			->where("category.show_in_menu", true)
			->where("category.status IN ?", array("publish"))
			->where("category.history_id", null)
			->fetchAssoc("category_id");
		$subCategoriesTree = $this->createTree($this->subCategories, null, 0);

		//walk all categories in first level
		foreach ($this->firstLevelCategories as $categoryId => &$firstLevelCategory){
			$items = $this->arrayRecursiveSearch($subCategoriesTree, $categoryId, 0, $maxSublevel); //set only maxSublevel

			$firstLevelCategory['link'] = $this->linkByType($firstLevelCategory["type"], $firstLevelCategory["category_id"], $this->language);
			$firstLevelCategory = \Nette\Utils\ArrayHash::from($firstLevelCategory);
			$firstLevelCategory['childs'] = $items;
		}

		$this->template->categories = $this->firstLevelCategories;
		$this->template->activeCategory = $this->activeMenuItem;
		$this->template->maxSublevel = $maxSublevel;
		$this->template->menuClass = $menuClass;
		$this->template->itemClass = $itemClass;

		$this->shortcodes->register($this->template);
		$this->template->setTranslator($this->translator);
		$this->template->render();
	}


	/**
	 * Create tree
	 */
	private function createTree(array $items, ?int $parent = null, int $level = 0): array
	{
		$tree = array();
		foreach ($items as $itemId => $category) {
			if ($category['parent_id'] == $parent) {
				unset($items[$itemId]);
				$category['link'] = $this->linkByType($category["type"], $category["category_id"], $this->language);
				$category['childs'] = $this->createTree($items, $category['category_id'], $level + 1);
				$tree[] = \Nette\Utils\ArrayHash::from($category, false);
			}
		}
		return $tree;
	}


	/**
	 * Search for value recursively in array
	 * @param int $level
	 * @param int|null $maxSublevel
	 */
	private function arrayRecursiveSearch(array $inItems, string $searchValue): array
	{
		foreach ($inItems as $itemId => $item) {

			/*if($searchValue == 10 && $item['category_id'] == $searchValue){
				\Tracy\Debugger::barDump($item['childs']);
			}*/

			//this item has childs
			if ($item['category_id'] == $searchValue) {
				/*	\Tracy\Debugger::barDump($item);
				$this->arrayCutMaxLevel($item, $level, $maxSublevel);
					\Tracy\Debugger::barDump($item);
					\Tracy\Debugger::barDump("++++");*/
				return $item['childs'];
			}

			if (isset($item['childs']) && is_array($item['childs'])) {
				/*if($searchValue == 10){
					\Tracy\Debugger::barDump("search in childs");
				}*/
				$returnValue = $this->arrayRecursiveSearch($item['childs'], $searchValue);

				/*if($searchValue == 10 && $returnValue != null){
					\Tracy\Debugger::barDump("find in childs");
					\Tracy\Debugger::barDump($returnValue);
					\Tracy\Debugger::barDump($item['childs']);
				}*/
				if ($returnValue != null) {
					/*\Tracy\Debugger::barDump($returnValue);
					$this->arrayCutMaxLevel($returnValue, $level, $maxSublevel);
					\Tracy\Debugger::barDump($returnValue);
					\Tracy\Debugger::barDump("++++");*/
					return $returnValue;
					//return $item['childs'];
				}
			}
		}

		return null;
	}


	/**
	 * Cut to max level
	 * @param int|null $maxSublevel
	 * /
	private function arrayCutMaxLevel(&$items, $level = 0, $maxSublevel = null)
	{
		if($maxSublevel === null){
			return;
		}
		if ($level > $maxSublevel) {
				\Tracy\Debugger::barDump($items['childs'], "more than sublevel");
			if (!empty($items['childs'])) {
				$items['childs'] = array();
			}
		} else {
			if(isset($items['childs']) && !empty($items['childs'])){
				\Tracy\Debugger::barDump($items['childs'], "cut in sub level");
				foreach ($items['childs'] as $childs){
				\Tracy\Debugger::barDump($level + 1, "ll - more than sublevel");
					$this->arrayCutMaxLevel($childs, $level + 1, $maxSublevel);
				}
				\Tracy\Debugger::barDump($items['childs'], "cut in sub level-end");
			}
		}
	}


	/**
	 * Decode tree to array
	 */
	private function treeToArray(&$toArray, array $items, $parent = null, int $level = 0): void
	{
		foreach ($items as $position => $item) {
			$toArray[] = array(
				"category_id" => $item["id"],
				"parent_id" => $parent,
				"position" => $position,
				"level" => $level
			);

			if (isset($item["children"])) {
				$this->treeToArray($toArray, $item["children"], $item["id"], $level + 1);
			}
		}
	}


	/**
	 * Decode tree to array
	 */
	private function linkByType($type, $key, $language)
	{
		switch ($type) {
			case "homepage":
				return $this->getPresenter()->link(":Front:Default:default", array("id" => null));

			case "categoryLink":
				//find target
				$targetCategory = $this->subCategories[$link];
				if($this->detectCirculation($link, $targetCategory['url'])){
					return "#error: Circulation detected";
				}
				return $this->linkByType($targetCategory['type'], $targetCategory['id'], $language);

			case "site":
			default:
				return $this->getPresenter()->link(":Front:Categories:detail", array("id" => $key, "locale" => $language));
		}
	}


	/**
	 * Detect circulation in links
	 */
	private function detectCirculation(string|int $from, string|int $to): bool
	{
		$searchFor = $from;
		$firstRound = true;
		$searching = true;
		$findedCirculation = false;
		//go thru all and find colision
		while ($searching) {
			if (!$firstRound) {
				if ($searchFor == $from) {
					$searching = false;
					$findedCirculation = true;
				}
			} else {
				$firstRound = false;
			}

			if (isset($this->circularDetector[$searchFor])) {
				$searchFor = $this->circularDetector[$searchFor];
			} else {
				$searching = false;
				$findedCirculation = false;
			}
		}

		if (!isset($this->circularDetector[$from])) {
			$this->circularDetector[$from] = $to;
		}

		return $findedCirculation;
	}

}
