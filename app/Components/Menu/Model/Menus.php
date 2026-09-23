<?php
declare(strict_types=1);

namespace App\Components\Menu\Model;

use App\Model\BaseModel;
use App\Model\Categories;
use App\Service\LanguageService;
use Nette\Database\Explorer;
use Nette\Database\SqlLiteral;
use Nette\Utils\ArrayHash;

/**
 * Menus Model
 */
class Menus extends BaseModel
{

	const
		MENU_ITEM_TABLE_NAME = 'firecms_menuItems';


	public function __construct(Explorer $database)
	{
		parent::__construct($database);

		$this->setTableName('firecms_menus');
		$this->setForeignKeyColumn('menuId');
	}


	/**
	 * Inserts new
	 */
	public function insert(ArrayHash $data): int
	{
		return parent::insert($data);
	}


	/**
	 * Validate location handler
	 * @param string $text
	 * @param int $ignoreMenuId
	 * @return string
	 */
	public function getLocation($text, $ignoreMenuId = null)
	{
		$exist = true;
		$index = 0;
		while ($exist){
			$location = \Nette\Utils\Strings::webalize($text . ($index == 0 ? "" : " " . $index));
			$sql = $this->findAll()
				->where("location", $location);
			if($ignoreMenuId){
				$sql->where($this->getColumnId()." != ?", $ignoreMenuId);
			}
			$exist = $sql->fetch();

			$index++;
		}

		return $location;
	}


	/**
	 * Get items in menu
	 * @param int $menuId
	 * @return \Nette\Database\Table\Selection All categorys relation
	 */
	public function getRelationMenuItems($menuId)
	{
		return $this->database->table(self::MENU_ITEM_TABLE_NAME)
			->select(self::MENU_ITEM_TABLE_NAME.".*")
			->select("category.*")
			->select(self::MENU_ITEM_TABLE_NAME.".position")
			->where("menuId", $menuId)
			->where("category.historyId", null)
			->where("category.status IN (?)", array('publish','pending','draft'))
			->order(self::MENU_ITEM_TABLE_NAME.".position");
	}


	/**
	 * Inserts relation with category
	 * @param int $menuId
	 * @param int $categoryId
	 * @param ArrayHash $data
	 * @return int Row id
	 */
	public function insertRelationMenuItem($menuId, $categoryId, $data = array())
	{
		$data["menuId"] = $menuId;
		$data["categoryId"] = $categoryId;
		if(!isset($data["position"])){
			$position = $this->database->table(self::MENU_ITEM_TABLE_NAME)
				->select("IFNULL(MAX(position),0)+1")
				->where("menuId",$menuId)
				->fetchField();
			$data["position"] = $position;
		}

		$this->database->query('INSERT IGNORE INTO '.self::MENU_ITEM_TABLE_NAME.' ?', $data);
	}


	/**
	 * Delete relation with category
	 * @param int $menuId
	 * @param int $categoryId
	 * @return int Row id
	 */
	public function deleteRelationMenuItem($menuId, $categoryId)
	{
		$item = $this->database->table(self::MENU_ITEM_TABLE_NAME)
			->where("menuId", $menuId)
			->where("categoryId", $categoryId);
		$this->database->table(self::MENU_ITEM_TABLE_NAME)
			->where("menuId", $menuId)
			->where("position > ?", $item->fetch()->position)
			->update(array("position" => new SqlLiteral("position - 1")));
		$item->delete();
	}


	/**
	 * Delete relation with category
	 * @param int $menuId
	 * @param int $categoryId
	 * @param int|null $previousCategoryId
	 * @param int|null $nextCategoryId
	 */
	public function updatePositionOfMenuItem($menuId, $categoryId, $previousCategoryId, $nextCategoryId)
	{
		$menuItems = $this->getRelationMenuItems($menuId)->fetchPairs("categoryId", "position");

		// Find all items that have to be moved one position up
		$this->database->table(self::MENU_ITEM_TABLE_NAME)
			->where("menuId", $menuId)
			->where("position <= ?", isset($menuItems[$previousCategoryId]) ? $menuItems[$previousCategoryId] : $menuItems[$categoryId])
			->where("position > ?", $menuItems[$categoryId])
			->update(array("position" => new SqlLiteral("position - 1")));


		// Find all items that have to be moved one position down
		$this->database->table(self::MENU_ITEM_TABLE_NAME)
			->where("menuId", $menuId)
			->where("position >= ?", isset($menuItems[$nextCategoryId]) ? $menuItems[$nextCategoryId] : $menuItems[$categoryId])
			->where("position < ?", $menuItems[$categoryId])
			->update(array("position" => new SqlLiteral("position + 1")));

		// Update current item order
		//reload menu items
		$menuItems = $this->getRelationMenuItems($menuId)->fetchPairs("categoryId", "position");
		if (isset($menuItems[$previousCategoryId])) {
			$newPosition = $menuItems[$previousCategoryId] + 1;
		} else if (isset($menuItems[$nextCategoryId])) {
			$newPosition = $menuItems[$nextCategoryId] - 1;
		} else {
			$newPosition = 1;
		}
		$this->database->table(self::MENU_ITEM_TABLE_NAME)
			->where("menuId", $menuId)
			->where("categoryId", $categoryId)
			->update(array("position" => $newPosition));
	}


	/**
	 * Inserts relation with category
	 * @param int $menuId
	 * @return \Nette\Database\Table\Selection All categories relation
	 */
	public function getRelationMenu($menuId)
	{
		return $this->database->table(Categories::RELATION_ARTICLE_TABLE_NAME)
			->select(Categories::RELATION_ARTICLE_TABLE_NAME.".*")
			->select("category.*")
			->where("menu.id", $menuId)
			->order("isMain DESC");
	}


	/**
	 * Get all with translation
	 * @param string $language
	 * @return \Nette\Database\Table\Selection Selection
	 */
	public function getAllMenuItemsWithTranslation($language, $menuLocation)
	{
		return $this->database->table(self::MENU_ITEM_TABLE_NAME)
			->select(self::MENU_ITEM_TABLE_NAME.".*")
			->select("category:" . Categories::TRANSLATION_TABLE_NAME . ".*")
			->select("category.*")
			->where("menu.location", $menuLocation)
			->where("category:" . Categories::TRANSLATION_TABLE_NAME . ".languageId", $language)
			->where("category.status IN ?", array("publish"))
			->where("category.historyId", null)
			->where("menu.active", true)
			->where("category.active", true)
			->where("category.showInMenu", true)
			->order(self::MENU_ITEM_TABLE_NAME.".position");
	}

}
