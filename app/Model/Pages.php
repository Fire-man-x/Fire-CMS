<?php
declare(strict_types=1);

namespace App\Model;

use App\Components\Menu\Model\MenuLinkType;
use App\Components\Menu\Model\Menus;
use App\Model\TranslatedTitleTrait\TranslatedTitleTrait;
use App\Modules\UrlModule\UrlManager;
use App\Service\LanguageService;
use Nette\Database\Explorer;
use Nette\Database\SqlLiteral;
use Nette\Database\Table\ActiveRow;
use Nette\Database\Table\Selection;

/**
 * Stránky (`firecms_pages` + překlady `firecms_pageDescriptions` + obrázky `firecms_pageFiles`) - samostatný
 * typ obsahu vedle článků a kategorií článků. Hezká URL má typ `page` (firecms_urls, plochý slug i u
 * podstránek), v menu je to MenuLinkType::Page. Stránky se vnořují přes `parentId`, pořadí mezi sourozenci
 * je `position`.
 */
class Pages extends BaseModel implements Translatable
{
	use TranslatedTitleTrait;

	const
		TRANSLATION_TABLE_NAME = 'firecms_pageDescriptions',
		FILE_TABLE_NAME = 'firecms_pageFiles',
		URL_TYPE = 'page';

	/** @var array<string, string> hodnota => popisek pro administraci */
	public const Statuses = [
		'publish' => 'Publish',
		'draft' => 'Draft',
	];


	public function __construct(
		Explorer $database,
		protected LanguageService $languages, // čte TranslatedTitleTrait
		private Menus $menusModel,
		private UrlManager $urlManager,
	) {
		parent::__construct($database);

		$this->setTableName('firecms_pages');
		$this->setForeignKeyColumn('pageId');
	}


	protected function getTitleColumn(): string
	{
		return 'title';
	}


	/**
	 * @return Selection<ActiveRow>
	 */
	public function getTranslationTable(): Selection
	{
		return $this->database->table(self::TRANSLATION_TABLE_NAME);
	}


	/**
	 * Překlad stránky v jazyce (bez ohledu na stav)
	 */
	public function getTranslation(int $pageId, string $language): ?ActiveRow
	{
		$translation = $this->findTranslation($pageId, $language)->fetch();

		return $translation instanceof ActiveRow ? $translation : null;
	}


	/**
	 * Uloží překlad stránky (založí, pokud v jazyce ještě není)
	 * @param array<string, mixed> $data
	 */
	public function saveTranslation(int $pageId, string $language, array $data): void
	{
		if ($this->findTranslation($pageId, $language)->fetch()) {
			$this->findTranslation($pageId, $language)->update($data);
		} else {
			$this->getTranslationTable()->insert($data + [
				$this->getForeignKeyColumn() => $pageId,
				'languageId' => $language,
			]);
		}
	}


	/**
	 * Stránky zobrazitelné na webu (publikované, aktivní) s překladem v jazyce $language. Sloupce stránky
	 * jsou pod `page.*`, překladu pod `firecms_pageDescriptions.*` (včetně `pageId`).
	 * @return Selection<ActiveRow>
	 */
	public function findPublished(string $language): Selection
	{
		return $this->getTranslationTable()
			->select(self::TRANSLATION_TABLE_NAME . '.*')
			->select('page.active, page.status, page.public, page.createdBy, page.parentId, page.position')
			->where(self::TRANSLATION_TABLE_NAME . '.languageId', $language)
			->where('page.status', 'publish')
			->where('page.active', true);
	}


	/**
	 * Všechny jazyky, ve kterých je stránka publikovaná (pro přepínač jazyků na webu)
	 * @return list<string>
	 */
	public function getPublishedLanguages(int $pageId): array
	{
		$languages = [];
		foreach ($this->getTranslationTable()
			->where($this->getForeignKeyColumn(), $pageId)
			->where('page.status', 'publish')
			->where('page.active', true) as $row) {
			if (is_string($row->languageId)) {
				$languages[] = $row->languageId;
			}
		}

		return $languages;
	}


	/**
	 * Vloží stránku na konec jejích sourozenců
	 * @param array<string, mixed> $values
	 */
	public function insertPage(array $values): int
	{
		$parentId = self::toNullableInt($values['parentId'] ?? null);
		$values['parentId'] = $parentId;
		$values['position'] = $this->getNextPosition($parentId);

		$row = $this->findAll()->insert($values);
		if (!$row instanceof ActiveRow) {
			throw new \RuntimeException('Page was not inserted.');
		}

		return self::toInt($row->id);
	}


	/**
	 * Uloží stránku; při změně rodiče ji zařadí na konec nových sourozenců
	 * @param array<string, mixed> $values
	 */
	public function updatePage(int $pageId, array $values): void
	{
		$page = $this->getById($pageId);
		if (!$page) {
			throw new \InvalidArgumentException("Page '$pageId' doesn't exist.");
		}

		$parentId = self::toNullableInt($values['parentId'] ?? null);
		if ($parentId !== null && in_array($parentId, $this->getSubtreeIds($pageId), true)) {
			throw new \InvalidArgumentException('Page cannot be moved under itself or its sub-page.');
		}
		$values['parentId'] = $parentId;

		$this->database->transaction(function () use ($page, $pageId, $parentId, $values): void {
			$oldParentId = self::toNullableInt($page->parentId);
			if ($parentId !== $oldParentId) {
				$this->closePositionGap($oldParentId, self::toInt($page->position));
				$values['position'] = $this->getNextPosition($parentId);
			}
			$this->findAll()->where($this->getColumnId(), $pageId)->update($values);
		});
	}


	/**
	 * Smaže stránku i s překlady a obrázky (FK), hezkými URL a položkami menu, které na ni odkazují (položka
	 * menu má cíl jen v `target`, bez cizího klíče). Podstránky se přesunou o úroveň výš na konec sourozenců.
	 */
	public function delete(int $id): ?int
	{
		$page = $this->getById($id);
		if (!$page) {
			return 0;
		}
		$parentId = self::toNullableInt($page->parentId);

		$deleted = $this->database->transaction(function () use ($id, $page, $parentId): int {
			$position = $this->getNextPosition($parentId);
			foreach ($this->findAll()->where('parentId', $id)->order('position') as $child) {
				$this->findAll()->where($this->getColumnId(), $child->id)->update(['parentId' => $parentId, 'position' => $position++]);
			}

			$this->menusModel->deleteItemsByTarget(MenuLinkType::Page, (string) $id);
			$this->urlManager->deleteUrls(self::URL_TYPE, $id);
			$deleted = $this->findAll()->where($this->getColumnId(), $id)->delete();
			$this->closePositionGap($parentId, self::toInt($page->position));

			return $deleted;
		});

		return is_int($deleted) ? $deleted : null;
	}


	/**
	 * Všechny stránky ve stromovém pořadí (rodič, pak jeho podstránky podle `position`) s názvem v jazyce
	 * $language a hloubkou `level` - pro administraci
	 *
	 * @return list<array{id: int, parentId: int|null, position: int, active: bool, status: string, title: string|null, level: int}>
	 */
	public function getTree(?string $language = null): array
	{
		$selection = $this->findAll()->select('`' . $this->getTableName() . '`.*');
		$this->selectTitle($selection, '`' . $this->getTableName() . '`.`id`', $language);

		$byParent = [];
		foreach ($selection->order('position')->order('title') as $row) {
			$page = [
				'id' => self::toInt($row->id),
				'parentId' => self::toNullableInt($row->parentId),
				'position' => self::toInt($row->position),
				'active' => (bool) $row->active,
				'status' => is_string($row->status) ? $row->status : '',
				'title' => is_string($row->title) ? $row->title : null,
				'level' => 0,
			];
			$byParent[$page['parentId'] ?? 0][] = $page;
		}

		$tree = [];
		$walk = function (int $parentId, int $level) use (&$walk, &$tree, $byParent): void {
			foreach ($byParent[$parentId] ?? [] as $page) {
				$page['level'] = $level;
				$tree[] = $page;
				$walk($page['id'], $level + 1);
			}
		};
		$walk(0, 0);

		return $tree;
	}


	/**
	 * Id stránky a všech jejích podstránek
	 * @return list<int>
	 */
	public function getSubtreeIds(int $pageId): array
	{
		$ids = [$pageId];
		$parents = [$pageId];
		while ($parents !== []) {
			$children = [];
			foreach ($this->findAll()->where('parentId', $parents) as $row) {
				$children[] = self::toInt($row->id);
			}
			$ids = [...$ids, ...$children];
			$parents = $children;
		}

		return $ids;
	}


	/**
	 * Přesune stránku mezi sourozenci (drag & drop v administraci): za $previousId, nebo před $nextId.
	 * Soused z jiné úrovně se ignoruje (rodič se mění ve formuláři stránky).
	 */
	public function movePage(int $pageId, ?int $previousId, ?int $nextId): void
	{
		$page = $this->getById($pageId);
		if (!$page) {
			throw new \InvalidArgumentException("Page '$pageId' doesn't exist.");
		}

		$siblingIds = [];
		foreach ($this->getSiblings(self::toNullableInt($page->parentId))->where('id != ?', $pageId)->order('position') as $row) {
			$siblingIds[] = self::toInt($row->id);
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
		array_splice($siblingIds, $index, 0, [$pageId]);

		$this->database->transaction(function () use ($siblingIds): void {
			foreach ($siblingIds as $position => $id) {
				$this->findAll()->where($this->getColumnId(), $id)->update(['position' => $position + 1]);
			}
		});
	}


	/**
	 * Publikované nadřazené stránky od kořene k přímému rodiči (drobečková navigace). Nepublikovaný předek
	 * se přeskočí, výš se pokračuje.
	 * @return list<array{id: int, title: string}>
	 */
	public function getPublishedParents(int $pageId, string $language): array
	{
		$parents = [];
		$visited = [$pageId => true];
		$parentId = self::toNullableInt($this->getById($pageId)?->parentId);
		while ($parentId !== null && !isset($visited[$parentId])) {
			$visited[$parentId] = true;
			$published = $this->findPublished($language)->where('pageId', $parentId)->fetch();
			if ($published instanceof ActiveRow && is_string($published->title)) {
				array_unshift($parents, ['id' => $parentId, 'title' => $published->title]);
			}
			$parentId = self::toNullableInt($this->getById($parentId)?->parentId);
		}

		return $parents;
	}


	/**
	 * Publikované podstránky (přímí potomci) v pořadí
	 * @return Selection<ActiveRow>
	 */
	public function findPublishedChildren(int $pageId, string $language): Selection
	{
		return $this->findPublished($language)
			->where('page.parentId', $pageId)
			->order('page.position');
	}


	/**
	 * Obrázky stránky v pořadí (řádky obsahují sloupce `firecms_files`, viz Files::toFileEntity())
	 * @return Selection<ActiveRow>
	 */
	public function getFiles(int $pageId): Selection
	{
		return $this->database->table(self::FILE_TABLE_NAME)
			->select(self::FILE_TABLE_NAME . '.position')
			->select('file.*')
			->where('pageId', $pageId)
			->order(self::FILE_TABLE_NAME . '.position');
	}


	/**
	 * Přidá obrázky na konec (už přiřazené se přeskočí)
	 * @param list<int> $fileIds
	 */
	public function addFiles(int $pageId, array $fileIds): void
	{
		$max = $this->database->table(self::FILE_TABLE_NAME)->where('pageId', $pageId)->max('position');
		$position = is_numeric($max) ? (int) $max : 0;
		$existing = [];
		foreach ($this->database->table(self::FILE_TABLE_NAME)->where('pageId', $pageId) as $row) {
			$existing[self::toInt($row->fileId)] = true;
		}

		foreach ($fileIds as $fileId) {
			if (isset($existing[$fileId])) {
				continue;
			}
			$existing[$fileId] = true;
			$this->database->table(self::FILE_TABLE_NAME)->insert([
				'pageId' => $pageId,
				'fileId' => $fileId,
				'position' => ++$position,
			]);
		}
	}


	public function removeFile(int $pageId, int $fileId): void
	{
		$this->database->table(self::FILE_TABLE_NAME)
			->where('pageId', $pageId)
			->where('fileId', $fileId)
			->delete();
	}


	/**
	 * Nové pořadí obrázků (první = hlavní obrázek)
	 * @param list<int> $fileIds
	 */
	public function sortFiles(int $pageId, array $fileIds): void
	{
		$this->database->transaction(function () use ($pageId, $fileIds): void {
			foreach (array_values($fileIds) as $position => $fileId) {
				$this->database->table(self::FILE_TABLE_NAME)
					->where('pageId', $pageId)
					->where('fileId', $fileId)
					->update(['position' => $position + 1]);
			}
		});
	}


	/**
	 * @return Selection<ActiveRow>
	 */
	private function getSiblings(?int $parentId): Selection
	{
		return $this->findAll()->where('parentId', $parentId);
	}


	private function getNextPosition(?int $parentId): int
	{
		$max = $this->getSiblings($parentId)->max('position');

		return (is_numeric($max) ? (int) $max : 0) + 1;
	}


	private function closePositionGap(?int $parentId, int $position): void
	{
		$this->getSiblings($parentId)
			->where('position > ?', $position)
			->update(['position' => new SqlLiteral('position - 1')]);
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


	private static function toNullableInt(mixed $value): ?int
	{
		return $value === null || $value === '' ? null : self::toInt($value);
	}


	/**
	 * @return Selection<ActiveRow>
	 */
	private function findTranslation(int $pageId, string $language): Selection
	{
		return $this->getTranslationTable()
			->where($this->getForeignKeyColumn(), $pageId)
			->where('languageId', $language);
	}
}
