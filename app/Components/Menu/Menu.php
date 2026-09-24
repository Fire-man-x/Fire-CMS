<?php
declare(strict_types=1);

namespace App\Components\Menu;

use App\Components\Menu\Model\MenuItem;
use App\Components\Menu\Model\MenuLinkType;
use App\Components\Menu\Model\Menus;
use App\Components\Shortcodes;
use App\Modules\UrlModule\UrlManager;
use App\Model;
use Nette\Application\UI\Control;
use Nette\Application\UI\InvalidLinkException;
use Nette\Utils\ArrayHash;

/**
 * Webové menu (`{control menu <location>, maxSublevel, menuClass, itemClass}`) z položek firecms_menuItems.
 * Položka odkazuje na kategorii, článek, URL nebo presenter (MenuLinkType)
 */
class Menu extends Control
{
	private string $templateFile;

	private array $firstLevelCategories;

	private array $subCategories;

	private array $subCategoriesTree;

	/** @var array<int, array<string, mixed>> */
	private array $articles = [];

	/** @var array<int, string> pageId => název */
	private array $pages = [];

	/** @var array<int, string> sectionId => název */
	private array $sections = [];

	/** @var array<int, list<\Nette\Database\Table\ActiveRow>> menuItemId => soubory */
	private array $itemFiles = [];

	private string $language;

	private ?int $activeMenuItem = null;



	/**
	 * CategoriesMenu
	 */
	public function __construct(
		private Menus $menusModel,
		private Model\Categories $categoriesModel,
		private Model\Articles $articlesModel,
		private Model\Pages $pagesModel,
		private Model\Sections $sectionsModel,
		private Model\Files $filesModel,
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

		//all categories (targets of category items + their subcategories)
		$this->subCategories = $this->categoriesModel->getAllWithTranslation($this->language)
			->where("category.active", true)
			->where("category.showInMenu", true)
			->where("category.status IN ?", array("publish"))
			->where("category.historyId", null)
			->fetchAssoc("categoryId");
		$this->subCategoriesTree = $this->createTree($this->subCategories, null, 0);

		$items = $this->menusModel->getActiveItemsByLocation((string) $location, $this->language);
		$this->articles = $this->loadArticles($items);
		$this->pages = $this->loadPages($items);
		$this->sections = $this->loadSections($items);
		$this->itemFiles = $this->menusModel->getItemFiles(array_map(fn(MenuItem $item): int => $item->id, $items));

		$itemsByParent = [];
		foreach ($items as $item) {
			$itemsByParent[$item->parentId ?? 0][] = $item;
		}

		$this->firstLevelCategories = $this->buildItems($itemsByParent, 0, $maxSublevel);
		if(!$this->firstLevelCategories){
			echo $this->translator->translate("No item.");
			return;
		}

		// `categories`/`activeCategory` - názvy proměnných zůstávají kvůli vlastním šablonám menu v projektech
		$this->template->categories = $this->firstLevelCategories;
		// veřejný nadpis menu v aktuálním jazyce (null = nevyplněný); výchozí Menu.latte ho nevykresluje
		$this->template->menuTitle = $this->menusModel->getTitle((int) $menuExist->id, $this->language);
		$this->template->activeCategory = $this->activeMenuItem;
		$this->template->maxSublevel = $maxSublevel;
		$this->template->menuClass = $menuClass;
		$this->template->itemClass = $itemClass;

		$this->shortcodes->register($this->template);
		$this->template->setTranslator($this->translator);
		$this->template->render();
	}


	/**
	 * Položky jedné úrovně pro šablonu. Položka, jejíž cíl se nedá zobrazit (nepublikovaná/skrytá kategorie,
	 * článek v koši, ...), se vynechá i s potomky.
	 *
	 * Každá položka má: id, categoryId (jen u kategorií), type (linkType), title, link, content, active,
	 * newWindow, files (obrázky, FileEntity), image (první obrázek nebo null), childs. Kategorie má v childs nejdřív vlastní
	 * podpoložky menu, pak své podkategorie se showInMenu (stejně jako dřív).
	 *
	 * @param array<int, list<MenuItem>> $itemsByParent
	 * @return list<ArrayHash>
	 */
	private function buildItems(array $itemsByParent, int $parentId, ?int $maxSublevel): array
	{
		$result = [];
		foreach ($itemsByParent[$parentId] ?? [] as $item) {
			$linkType = $item->getLinkType();
			$target = $item->target;
			$label = $item->label;
			$childs = $this->buildItems($itemsByParent, $item->id, $maxSublevel);

			$menuItem = [
				'id' => $item->id,
				'categoryId' => null,
				'type' => $linkType?->value,
				'title' => $label ?? $item->defaultLabel ?? $target,
				'link' => '#',
				'content' => null,
				'active' => true,
				'newWindow' => $item->newWindow,
			];

			switch ($linkType) {
				case MenuLinkType::Category:
					$category = $this->subCategories[(int) $target] ?? null;
					if ($category === null) {
						continue 2;
					}
					$menuItem = [
						'categoryId' => (int) $target,
						'type' => MenuLinkType::Category->value,
						'title' => $label ?? $category['title'],
						'link' => $this->categoryLink((int) $target),
						'content' => $category['content'] ?? null,
						'active' => (bool) $category['active'],
					] + $menuItem;
					$childs = [...$childs, ...($this->arrayRecursiveSearch($this->subCategoriesTree, (int) $target, 0, $maxSublevel) ?? [])];
					break;

				case MenuLinkType::Article:
					$article = $this->articles[(int) $target] ?? null;
					if ($article === null) {
						continue 2;
					}
					$menuItem['title'] = $label ?? $article['title'];
					$menuItem['link'] = $this->getPresenter()->link(':Front:Articles:detail', ['id' => (int) $target, 'locale' => $this->language]);
					break;

				case MenuLinkType::Page:
					$pageTitle = $this->pages[(int) $target] ?? null;
					if ($pageTitle === null) {
						continue 2;
					}
					$menuItem['title'] = $label ?? $pageTitle;
					$menuItem['link'] = $this->getPresenter()->link(':Front:Pages:detail', ['id' => (int) $target, 'locale' => $this->language]);
					break;

				case MenuLinkType::Section:
					$sectionTitle = $this->sections[(int) $target] ?? null;
					if ($sectionTitle === null) {
						continue 2;
					}
					$menuItem['title'] = $label ?? $sectionTitle;
					$menuItem['link'] = $this->getPresenter()->link(':Front:Sections:detail', ['id' => (int) $target, 'locale' => $this->language]);
					break;

				case MenuLinkType::Url:
					$menuItem['link'] = $target;
					break;

				case MenuLinkType::Route:
					$menuItem['link'] = $this->linkToRoute($target);
					break;

				default:
					continue 2;
			}

			// obrázky položky (první = hlavní); výchozí Menu.latte je nevykresluje, jsou pro vlastní šablony
			$menuItem['files'] = array_map(fn($file) => $this->filesModel->toFileEntity($file), $this->itemFiles[$item->id] ?? []);
			$menuItem['image'] = $menuItem['files'][0] ?? null;
			$menuItem['childs'] = $childs;
			$result[] = ArrayHash::from($menuItem, false);
		}

		return $result;
	}


	/**
	 * Publikované články, na které odkazují položky menu, s překladem v aktuálním jazyce
	 * @param list<MenuItem> $items
	 * @return array<int, array<string, mixed>> articleId => článek
	 */
	private function loadArticles(array $items): array
	{
		$ids = [];
		foreach ($items as $item) {
			if ($item->getLinkType() === MenuLinkType::Article && ctype_digit($item->target)) {
				$ids[] = (int) $item->target;
			}
		}
		if ($ids === []) {
			return [];
		}

		$articles = [];
		foreach ($this->articlesModel->getAllWithTranslation($this->language)
			->where('article.id', $ids)
			->where('article.status', 'publish')
			->where('article.historyId', null) as $article) {
			$articles[(int) $article->articleId] = $article->toArray();
		}

		return $articles;
	}


	/**
	 * Publikované stránky, na které odkazují položky menu, s názvem v aktuálním jazyce
	 * @param list<MenuItem> $items
	 * @return array<int, string> pageId => název
	 */
	private function loadPages(array $items): array
	{
		$ids = [];
		foreach ($items as $item) {
			if ($item->getLinkType() === MenuLinkType::Page && ctype_digit($item->target)) {
				$ids[] = (int) $item->target;
			}
		}
		if ($ids === []) {
			return [];
		}

		$pages = [];
		foreach ($this->pagesModel->findPublished($this->language)->where('pageId', $ids) as $page) {
			$pages[(int) $page->pageId] = (string) $page->title;
		}

		return $pages;
	}


	/**
	 * Aktivní sekce, na které odkazují položky menu, s názvem v aktuálním jazyce
	 * @param list<MenuItem> $items
	 * @return array<int, string> sectionId => název
	 */
	private function loadSections(array $items): array
	{
		$ids = [];
		foreach ($items as $item) {
			if ($item->getLinkType() === MenuLinkType::Section && ctype_digit($item->target)) {
				$ids[] = (int) $item->target;
			}
		}
		if ($ids === []) {
			return [];
		}

		$sections = [];
		foreach ($this->sectionsModel->findActive($this->language)->where('sectionId', $ids) as $section) {
			$sections[(int) $section->sectionId] = (string) $section->title;
		}

		return $sections;
	}


	/**
	 * Odkaz typu route: `:Front:Properties:default?town=Brno` = cíl presenteru + parametry. Odkazům na frontend
	 * se doplní aktuální jazyk (`locale`), stejně jako u kategorií.
	 */
	private function linkToRoute(string $target): string
	{
		[$destination, $query] = array_pad(explode('?', $target, 2), 2, '');
		parse_str($query, $params);

		if (!isset($params['locale']) && (str_starts_with($destination, ':Front:') || !str_starts_with($destination, ':'))) {
			$params['locale'] = $this->language;
		}

		try {
			return $this->getPresenter()->link($destination, $params);
		} catch (InvalidLinkException) {
			return '#error: invalid link';
		}
	}


	/**
	 * Create tree
	 */
	private function createTree(array $items, ?int $parent = null, int $level = 0): array
	{
		$tree = array();
		foreach ($items as $itemId => $category) {
			if ($category['parentId'] == $parent) {
				unset($items[$itemId]);
				$category['link'] = $this->categoryLink((int) $category["categoryId"]);
				$category['childs'] = $this->createTree($items, $category['categoryId'], $level + 1);
				$tree[] = \Nette\Utils\ArrayHash::from($category, false);
			}
		}
		return $tree;
	}


	/**
	 * Search for value recursively in array
	 */
	private function arrayRecursiveSearch(array $inItems, int $searchValue, int $level, ?int $maxSublevel): ?array
	{
		foreach ($inItems as $itemId => $item) {

			/*if($searchValue == 10 && $item['categoryId'] == $searchValue){
				\Tracy\Debugger::barDump($item['childs']);
			}*/

			//this item has childs
			if ($item['categoryId'] == $searchValue) {
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
				$returnValue = $this->arrayRecursiveSearch($item['childs'], $searchValue, ++$level, $maxSublevel);

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
	 * Odkaz na kategorii článků (kategorie nemají typ, homepage je Front:Homepage)
	 */
	private function categoryLink(int $categoryId): string
	{
		return $this->getPresenter()->link(':Front:Categories:detail', ['id' => $categoryId, 'locale' => $this->language]);
	}

}
