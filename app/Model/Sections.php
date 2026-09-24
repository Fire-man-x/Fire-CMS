<?php
declare(strict_types=1);

namespace App\Model;

use App\Components\Menu\Model\MenuLinkType;
use App\Components\Menu\Model\Menus;
use App\Model\TranslatedTitleTrait\TranslatedTitleTrait;
use App\Modules\UrlModule\UrlManager;
use App\Service\LanguageService;
use Nette\Database\Explorer;
use Nette\Database\Table\ActiveRow;
use Nette\Database\Table\Selection;

/**
 * Sekce (`firecms_sections` + překlady `firecms_sectionDescriptions`) - znovupoužitelné skupiny "kategorie
 * článků + články" (Blog, Novinky, ...). Kategorie i článek patří do jedné sekce (`sectionId`). Výpis sekce
 * na webu má URL typu `section`, v menu je to MenuLinkType::Section.
 */
class Sections extends BaseModel implements Translatable
{
	use TranslatedTitleTrait;

	const
		TRANSLATION_TABLE_NAME = 'firecms_sectionDescriptions',
		URL_TYPE = 'section';


	public function __construct(
		Explorer $database,
		protected LanguageService $languages, // čte TranslatedTitleTrait
		private Menus $menusModel,
		private UrlManager $urlManager,
	) {
		parent::__construct($database);

		$this->setTableName('firecms_sections');
		$this->setForeignKeyColumn('sectionId');
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
	 * Sekce v pořadí s názvem v jazyce $language (i neaktivní - pro administraci)
	 * @return array<int, string> id => název
	 */
	public function getList(?string $language = null): array
	{
		$selection = $this->findAll()->select('`' . $this->getTableName() . '`.*');
		$this->selectTitle($selection, '`' . $this->getTableName() . '`.`id`', $language);

		$list = [];
		foreach ($selection->order('position')->order('id') as $row) {
			$id = self::toInt($row->id);
			$list[$id] = is_string($row->title) ? $row->title : '#' . $id;
		}

		return $list;
	}


	/**
	 * Id existující sekce: zadané, jinak první podle pořadí (null = žádná sekce neexistuje)
	 */
	public function resolveId(?int $sectionId): ?int
	{
		if ($sectionId !== null) {
			return $this->getById($sectionId) ? $sectionId : null;
		}
		$first = $this->findAll()->order('position')->order('id')->fetch();

		return $first instanceof ActiveRow ? self::toInt($first->id) : null;
	}


	public function getTranslation(int $sectionId, string $language): ?ActiveRow
	{
		$translation = $this->findTranslation($sectionId, $language)->fetch();

		return $translation instanceof ActiveRow ? $translation : null;
	}


	/**
	 * @param array<string, mixed> $data
	 */
	public function saveTranslation(int $sectionId, string $language, array $data): void
	{
		if ($this->findTranslation($sectionId, $language)->fetch()) {
			$this->findTranslation($sectionId, $language)->update($data);
		} else {
			$this->getTranslationTable()->insert($data + [
				$this->getForeignKeyColumn() => $sectionId,
				'languageId' => $language,
			]);
		}
	}


	/**
	 * Aktivní sekce s překladem v jazyce $language (web). Sloupce překladu + `section.*` pod aliasy.
	 * @return Selection<ActiveRow>
	 */
	public function findActive(string $language): Selection
	{
		return $this->getTranslationTable()
			->select(self::TRANSLATION_TABLE_NAME . '.*')
			->select('section.active, section.position')
			->where(self::TRANSLATION_TABLE_NAME . '.languageId', $language)
			->where('section.active', true);
	}


	/**
	 * Jazyky, ve kterých je aktivní sekce přeložená (přepínač jazyků na webu)
	 * @return list<string>
	 */
	public function getActiveLanguages(int $sectionId): array
	{
		$languages = [];
		foreach ($this->getTranslationTable()->where($this->getForeignKeyColumn(), $sectionId)->where('section.active', true) as $row) {
			if (is_string($row->languageId)) {
				$languages[] = $row->languageId;
			}
		}

		return $languages;
	}


	/**
	 * Vloží sekci na konec pořadí
	 * @param array<string, mixed> $values
	 */
	public function insertSection(array $values): int
	{
		$max = $this->findAll()->max('position');
		$values['position'] = (is_numeric($max) ? (int) $max : 0) + 1;

		$row = $this->findAll()->insert($values);
		if (!$row instanceof ActiveRow) {
			throw new \RuntimeException('Section was not inserted.');
		}

		return self::toInt($row->id);
	}


	/**
	 * Počet kategorií a článků v sekci (sekci s obsahem nejde smazat)
	 */
	public function countContent(int $sectionId): int
	{
		return $this->database->table('firecms_categories')->where('sectionId', $sectionId)->count('*')
			+ $this->database->table('firecms_articles')->where('sectionId', $sectionId)->count('*');
	}


	/**
	 * Smaže prázdnou sekci i s URL a položkami menu, které na ni odkazují
	 * @throws \LogicException sekce obsahuje kategorie nebo články
	 */
	public function delete(int $id): ?int
	{
		if ($this->countContent($id) > 0) {
			throw new \LogicException("Section '$id' contains categories or articles.");
		}

		$deleted = $this->database->transaction(function () use ($id): int {
			$this->menusModel->deleteItemsByTarget(MenuLinkType::Section, (string) $id);
			$this->urlManager->deleteUrls(self::URL_TYPE, $id);

			return $this->findAll()->where($this->getColumnId(), $id)->delete();
		});

		return is_int($deleted) ? $deleted : null;
	}


	/**
	 * @return Selection<ActiveRow>
	 */
	private function findTranslation(int $sectionId, string $language): Selection
	{
		return $this->getTranslationTable()
			->where($this->getForeignKeyColumn(), $sectionId)
			->where('languageId', $language);
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
}
