<?php
declare(strict_types=1);

namespace App\Components\Menu\Model;

use App\Model\BaseModel;
use App\Model\Translatable;
use App\Model\TranslatedTitleTrait\TranslatedTitleTrait;
use App\Service\LanguageService;
use Nette\Database\Explorer;
use Nette\Database\SqlLiteral;
use Nette\Database\Table\ActiveRow;
use Nette\Database\Table\Selection;
use Nette\Utils\ArrayHash;

/**
 * Menu (`firecms_menus` + nadpisy po jazycích `firecms_menuDescriptions`) a jejich položky (`firecms_menuItems` + popisky `firecms_menuItemDescriptions`).
 * Položka je samostatná entita s typem odkazu (MenuLinkType) a cílem `target`, viz migrace
 * data/migrations/structures/20260923200000.sql.
 */
class Menus extends BaseModel implements Translatable
{
	use TranslatedTitleTrait;

	const
		TRANSLATION_TABLE_NAME = 'firecms_menuDescriptions',
		MENU_ITEM_TABLE_NAME = 'firecms_menuItems',
		MENU_ITEM_TRANSLATION_TABLE_NAME = 'firecms_menuItemDescriptions',
		MENU_ITEM_FILE_TABLE_NAME = 'firecms_menuItemFiles';


	public function __construct(Explorer $database, private LanguageService $languages)
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
	 * Sloupec překladové tabulky s názvem menu (viz TranslatedTitleTrait) - menu nemá jiný název než `title`
	 */
	protected function getTitleColumn(): string
	{
		return 'title';
	}


	/**
	 * Název menu pro administraci: nadpis v jazyce $language, jinak ve výchozím jazyce, jinak `location`
	 */
	public function getDisplayName(int $menuId, ?string $language = null): string
	{
		$defaultLanguage = $this->languages->getDefaultLanguage();
		$title = ($language !== null ? $this->getTitle($menuId, $language) : null) ?? $this->getTitle($menuId, $defaultLanguage);
		if ($title !== null) {
			return $title;
		}

		$location = $this->findAll()->where($this->getColumnId(), $menuId)->fetchField('location');

		return is_string($location) ? $location : '#' . $menuId;
	}


	/**
	 * @return Selection<ActiveRow>
	 */
	public function getTranslationTable(): Selection
	{
		return $this->database->table(self::TRANSLATION_TABLE_NAME);
	}


	/**
	 * Veřejný nadpis menu v jazyce $language (null = nevyplněný)
	 */
	public function getTitle(int $menuId, string $language): ?string
	{
		$title = $this->getTranslationTable()
			->where($this->getForeignKeyColumn(), $menuId)
			->where('languageId', $language)
			->fetchField('title');

		return is_string($title) && $title !== '' ? $title : null;
	}


	/**
	 * Nadpisy menu ve všech jazycích
	 * @return array<string, string> languageId => title
	 */
	public function getTitles(int $menuId): array
	{
		$titles = [];
		foreach ($this->getTranslationTable()->where($this->getForeignKeyColumn(), $menuId) as $row) {
			if (is_string($row->languageId) && is_string($row->title)) {
				$titles[$row->languageId] = $row->title;
			}
		}

		return $titles;
	}


	/**
	 * Uloží nadpisy menu; prázdný nadpis = žádný řádek pro daný jazyk
	 * @param array<string, string|null> $titles languageId => title
	 */
	public function saveTitles(int $menuId, array $titles): void
	{
		$this->database->transaction(function () use ($menuId, $titles): void {
			foreach ($titles as $language => $title) {
				$title = $title !== null && trim($title) !== '' ? trim($title) : null;
				$existing = $this->getTranslationTable()
					->where($this->getForeignKeyColumn(), $menuId)
					->where('languageId', $language);

				if ($title === null) {
					$existing->delete();
				} elseif ($existing->fetch()) {
					$existing->update(['title' => $title]);
				} else {
					$this->getTranslationTable()->insert([
						$this->getForeignKeyColumn() => $menuId,
						'languageId' => $language,
						'title' => $title,
					]);
				}
			}
		});
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
	 * Položky menu ve stromovém pořadí (rodič, pak jeho potomci podle `position`) s popiskem v jazyce
	 * $language a hloubkou `level` (od 0)
	 *
	 * @return list<MenuItem>
	 */
	public function getItemsTree(int $menuId, string $language): array
	{
		$byParent = [];
		foreach ($this->getItemsWithLabel($menuId, $language) as $row) {
			$item = MenuItem::fromRow($row);
			$byParent[$item->parentId ?? 0][] = $item;
		}

		$tree = [];
		$walk = function (int $parentId, int $level) use (&$walk, &$tree, $byParent): void {
			foreach ($byParent[$parentId] ?? [] as $item) {
				$tree[] = $item->withLevel($level);
				$walk($item->id, $level + 1);
			}
		};
		$walk(0, 0);

		return $tree;
	}


	/**
	 * Položky menu (bez ohledu na aktivitu) s popiskem `label` v jazyce $language
	 * @return Selection<ActiveRow>
	 */
	public function getItemsWithLabel(int $menuId, string $language): Selection
	{
		return $this->getItemsTable()
			->select(self::MENU_ITEM_TABLE_NAME . '.*')
			->select(
				'(SELECT `description`.`label` FROM `' . self::MENU_ITEM_TRANSLATION_TABLE_NAME . '` `description`'
				. ' WHERE `description`.`menuItemId` = `' . self::MENU_ITEM_TABLE_NAME . '`.`id` AND `description`.`languageId` = ?) AS `label`',
				$language,
			)
			// náhradní popisek (výchozí jazyk) pro položky URL/route, které nemají vlastní název
			->select(
				'(SELECT `defaultDescription`.`label` FROM `' . self::MENU_ITEM_TRANSLATION_TABLE_NAME . '` `defaultDescription`'
				. ' WHERE `defaultDescription`.`menuItemId` = `' . self::MENU_ITEM_TABLE_NAME . '`.`id` AND `defaultDescription`.`languageId` = ?) AS `defaultLabel`',
				$this->languages->getDefaultLanguage(),
			)
			->where('menuId', $menuId)
			->order('parentId IS NOT NULL')
			->order('parentId')
			->order('position');
	}


	/**
	 * Aktivní položky aktivního menu na dané pozici šablony (`location`) s popiskem v jazyce $language -
	 * pro webové menu. Jen stromové pořadí, cíle (kategorie/články) řeší App\Components\Menu\Menu.
	 *
	 * Neaktivní položka se vynechá i s potomky.
	 *
	 * @return list<MenuItem>
	 */
	public function getActiveItemsByLocation(string $location, string $language): array
	{
		$menuId = $this->findAll()
			->where('location', $location)
			->where('active', true)
			->fetchField('id');
		if (!is_numeric($menuId)) {
			return [];
		}
		$menuId = (int) $menuId;

		$active = [];
		$hiddenIds = [];
		foreach ($this->getItemsTree($menuId, $language) as $item) {
			if (!$item->active || ($item->parentId !== null && isset($hiddenIds[$item->parentId]))) {
				$hiddenIds[$item->id] = true;
				continue;
			}
			$active[] = $item;
		}

		return $active;
	}


	public function getItem(int $itemId): ?MenuItem
	{
		$row = $this->getItemsTable()->get($itemId);

		return $row instanceof ActiveRow ? MenuItem::fromRow($row) : null;
	}


	/**
	 * Popisky položky ve všech jazycích
	 * @return array<string, string> languageId => label
	 */
	public function getItemLabels(int $itemId): array
	{
		$labels = [];
		foreach ($this->database->table(self::MENU_ITEM_TRANSLATION_TABLE_NAME)->where('menuItemId', $itemId) as $row) {
			if (is_string($row->languageId) && is_string($row->label)) {
				$labels[$row->languageId] = $row->label;
			}
		}

		return $labels;
	}


	/**
	 * Vloží položku na konec svých sourozenců
	 * @param array{parentId?: int|null, active?: bool, linkType: string, target: string, newWindow?: bool} $data
	 * @param array<string, string|null> $labels languageId => popisek (prázdný = bez popisku)
	 */
	public function insertItem(int $menuId, array $data, array $labels = []): int
	{
		$data['menuId'] = $menuId;
		$data['position'] = $this->getNextItemPosition($menuId, $data['parentId'] ?? null);

		$row = $this->getItemsTable()->insert($data);
		if (!$row instanceof ActiveRow) {
			throw new \RuntimeException('Menu item was not inserted.');
		}
		$itemId = MenuItem::fromRow($row)->id;
		$this->saveItemLabels($itemId, $labels);

		return $itemId;
	}


	/**
	 * Uloží položku; při změně rodiče ji zařadí na konec nových sourozenců
	 * @param array{parentId?: int|null, active?: bool, linkType: string, target: string, newWindow?: bool} $data
	 * @param array<string, string|null>|null $labels languageId => popisek; null = popisky neměnit, jazyk,
	 *        který v poli chybí, se také nemění
	 */
	public function updateItem(int $itemId, array $data, ?array $labels = null): void
	{
		$item = $this->getItem($itemId);
		if (!$item) {
			throw new \InvalidArgumentException("Menu item '$itemId' doesn't exist.");
		}

		$newParentId = $data['parentId'] ?? null;
		if ($newParentId !== null && in_array($newParentId, $this->getSubtreeIds($itemId), true)) {
			throw new \InvalidArgumentException('Menu item cannot be moved under itself or its descendant.');
		}

		$this->database->transaction(function () use ($item, $itemId, $data, $newParentId, $labels): void {
			if ($newParentId !== $item->parentId) {
				$this->closePositionGap($item->menuId, $item->parentId, $item->position);
				$data['position'] = $this->getNextItemPosition($item->menuId, $newParentId);
			}
			$this->getItemsTable()->where('id', $itemId)->update($data);
			if ($labels !== null) {
				$this->saveItemLabels($itemId, $labels);
			}
		});
	}


	/**
	 * Smaže položku i s potomky (FK parentId ON DELETE CASCADE) a srovná pořadí sourozenců
	 */
	public function deleteItem(int $itemId): void
	{
		$item = $this->getItem($itemId);
		if (!$item) {
			return;
		}

		$this->database->transaction(function () use ($item, $itemId): void {
			$this->getItemsTable()->where('id', $itemId)->delete();
			$this->closePositionGap($item->menuId, $item->parentId, $item->position);
		});
	}


	/**
	 * Smaže položky všech menu, které odkazují na daný cíl (např. na smazanou kategorii)
	 */
	public function deleteItemsByTarget(MenuLinkType $linkType, string $target): void
	{
		foreach ($this->getItemsTable()->where('linkType', $linkType->value)->where('target', $target) as $row) {
			$this->deleteItem(MenuItem::fromRow($row)->id);
		}
	}


	/**
	 * Přesune položku mezi sourozenci (drag & drop v administraci): za $previousId, nebo před $nextId.
	 * Položku jde přesunout jen mezi sourozence - soused z jiné úrovně se ignoruje (rodič se mění ve formuláři).
	 */
	public function moveItem(int $itemId, ?int $previousId, ?int $nextId): void
	{
		$item = $this->getItem($itemId);
		if (!$item) {
			throw new \InvalidArgumentException("Menu item '$itemId' doesn't exist.");
		}

		$siblingIds = [];
		foreach ($this->getSiblings($item->menuId, $item->parentId)->where('id != ?', $itemId)->order('position') as $row) {
			$siblingIds[] = MenuItem::fromRow($row)->id;
		}

		$previousIndex = $previousId !== null ? array_search($previousId, $siblingIds, true) : false;
		$nextIndex = $nextId !== null ? array_search($nextId, $siblingIds, true) : false;
		if (is_int($previousIndex)) {
			$index = $previousIndex + 1;
		} elseif (is_int($nextIndex)) {
			$index = $nextIndex;
		} else {
			return;
		}

		array_splice($siblingIds, $index, 0, [$itemId]);

		$this->database->transaction(function () use ($siblingIds): void {
			foreach ($siblingIds as $position => $id) {
				$this->getItemsTable()->where('id', $id)->update(['position' => $position + 1]);
			}
		});
	}


	/**
	 * Id položky a všech jejích potomků
	 * @return list<int>
	 */
	public function getSubtreeIds(int $itemId): array
	{
		$ids = [$itemId];
		$parents = [$itemId];
		while ($parents !== []) {
			$children = [];
			foreach ($this->getItemsTable()->where('parentId', $parents) as $row) {
				$children[] = MenuItem::fromRow($row)->id;
			}
			$ids = [...$ids, ...$children];
			$parents = $children;
		}

		return $ids;
	}


	/**
	 * Obrázky položek v pořadí (první = hlavní), seskupené podle položky. Řádky obsahují sloupce
	 * `firecms_files` (Files::toFileEntity()) + `menuItemId`.
	 * @param list<int> $itemIds
	 * @return array<int, list<ActiveRow>> menuItemId => řádky souborů
	 */
	public function getItemFiles(array $itemIds): array
	{
		if ($itemIds === []) {
			return [];
		}

		$files = [];
		foreach ($this->database->table(self::MENU_ITEM_FILE_TABLE_NAME)
			->select(self::MENU_ITEM_FILE_TABLE_NAME . '.menuItemId')
			->select('file.*')
			->where('menuItemId', $itemIds)
			->order(self::MENU_ITEM_FILE_TABLE_NAME . '.position') as $row) {
			$files[self::toInt($row->menuItemId)][] = $row;
		}

		return $files;
	}


	/**
	 * Přidá obrázky položce na konec (už přiřazené se přeskočí)
	 * @param list<int> $fileIds
	 */
	public function addItemFiles(int $itemId, array $fileIds): void
	{
		$table = fn(): Selection => $this->database->table(self::MENU_ITEM_FILE_TABLE_NAME)->where('menuItemId', $itemId);
		$max = $table()->max('position');
		$position = is_numeric($max) ? (int) $max : 0;
		$existing = [];
		foreach ($table() as $row) {
			$existing[self::toInt($row->fileId)] = true;
		}

		foreach ($fileIds as $fileId) {
			if (isset($existing[$fileId])) {
				continue;
			}
			$existing[$fileId] = true;
			$this->database->table(self::MENU_ITEM_FILE_TABLE_NAME)->insert([
				'menuItemId' => $itemId,
				'fileId' => $fileId,
				'position' => ++$position,
			]);
		}
	}


	public function removeItemFile(int $itemId, int $fileId): void
	{
		$this->database->table(self::MENU_ITEM_FILE_TABLE_NAME)
			->where('menuItemId', $itemId)
			->where('fileId', $fileId)
			->delete();
	}


	/**
	 * Nové pořadí obrázků položky (první = hlavní)
	 * @param list<int> $fileIds
	 */
	public function sortItemFiles(int $itemId, array $fileIds): void
	{
		$this->database->transaction(function () use ($itemId, $fileIds): void {
			foreach (array_values($fileIds) as $position => $fileId) {
				$this->database->table(self::MENU_ITEM_FILE_TABLE_NAME)
					->where('menuItemId', $itemId)
					->where('fileId', $fileId)
					->update(['position' => $position + 1]);
			}
		});
	}


	private static function toInt(mixed $value): int
	{
		if (is_int($value)) {
			return $value;
		}
		if (is_string($value) && preg_match('#^-?\d+$#D', $value)) {
			return (int) $value;
		}
		throw new \UnexpectedValueException('Expected integer value, got ' . get_debug_type($value) . '.');
	}


	/**
	 * @return Selection<ActiveRow>
	 */
	private function getItemsTable(): Selection
	{
		return $this->database->table(self::MENU_ITEM_TABLE_NAME);
	}


	/**
	 * @return Selection<ActiveRow>
	 */
	private function getSiblings(int $menuId, ?int $parentId): Selection
	{
		return $this->getItemsTable()
			->where('menuId', $menuId)
			->where('parentId', $parentId);
	}


	private function getNextItemPosition(int $menuId, ?int $parentId): int
	{
		$max = $this->getSiblings($menuId, $parentId)->max('position');

		return (is_numeric($max) ? (int) $max : 0) + 1;
	}


	/**
	 * Po odebrání položky z úrovně posune následující sourozence o jedno místo nahoru
	 */
	private function closePositionGap(int $menuId, ?int $parentId, int $position): void
	{
		$this->getSiblings($menuId, $parentId)
			->where('position > ?', $position)
			->update(['position' => new SqlLiteral('position - 1')]);
	}


	/**
	 * @param array<string, string|null> $labels languageId => popisek
	 */
	private function saveItemLabels(int $itemId, array $labels): void
	{
		foreach ($labels as $language => $label) {
			$this->saveItemLabel($itemId, $label, (string) $language);
		}
	}


	/**
	 * Prázdný popisek = žádný řádek (webové menu pak použije název kategorie/článku)
	 */
	private function saveItemLabel(int $itemId, ?string $label, string $language): void
	{
		$label = $label !== null && trim($label) !== '' ? trim($label) : null;
		$descriptions = $this->database->table(self::MENU_ITEM_TRANSLATION_TABLE_NAME)
			->where('menuItemId', $itemId)
			->where('languageId', $language);

		if ($label === null) {
			$descriptions->delete();
		} elseif ($descriptions->fetch()) {
			$descriptions->update(['label' => $label]);
		} else {
			$this->database->table(self::MENU_ITEM_TRANSLATION_TABLE_NAME)->insert([
				'menuItemId' => $itemId,
				'languageId' => $language,
				'label' => $label,
			]);
		}
	}

}
