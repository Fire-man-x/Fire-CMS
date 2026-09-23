<?php

declare(strict_types=1);

namespace Tests\Model;

use App\Model\Languages;
use App\Model\Tags;
use App\Service\LanguageService;
use Nette\Database\Explorer;
use Tester\Assert;
use Tester\TestCase;
use Tests\Helpers\SqliteDatabase;

require_once __DIR__ . '/../bootstrap.php';

/**
 * Integrační test nad in-memory SQLite: `TranslatedTitleTrait` (náhrada za zrušený sloupec `gridName`)
 * vrací název z překladové tabulky ve zvoleném jazyce administrace, jinak ve výchozím jazyce webu (a teprve
 * pak první dostupný překlad) - vždy jeden konkrétní řádek, žádné GROUP BY.
 * Hlídá zároveň, že poddotaz projde přes Nette Explorer (parsování `tabulka.sloupec`, parametry `?`)
 * i v kombinaci s count() a where(), jak ho používají datagridy a Service\Tag.
 */
final class TranslatedTitleTraitTest extends TestCase
{
	private Explorer $db;
	private Tags $tags;

	protected function setUp(): void
	{
		parent::setUp();

		$this->db = SqliteDatabase::create([
			'CREATE TABLE firecms_languages (
				languageId TEXT PRIMARY KEY,
				active INTEGER NOT NULL DEFAULT 1,
				"default" INTEGER NOT NULL DEFAULT 0,
				position INTEGER NOT NULL,
				name TEXT NOT NULL,
				shortcut TEXT NOT NULL
			)',
			'CREATE TABLE firecms_tags (
				id INTEGER PRIMARY KEY AUTOINCREMENT,
				createDate TEXT NULL
			)',
			'CREATE TABLE firecms_tagDescriptions (
				tagId INTEGER NOT NULL REFERENCES firecms_tags (id),
				languageId TEXT NOT NULL REFERENCES firecms_languages (languageId),
				name TEXT NOT NULL,
				PRIMARY KEY (tagId, languageId)
			)',
		]);

		$this->db->query('INSERT INTO firecms_languages (languageId, active, "default", position, name, shortcut) VALUES (?, 1, 0, 1, ?, ?)', 'en', 'English', 'en');
		$this->db->query('INSERT INTO firecms_languages (languageId, active, "default", position, name, shortcut) VALUES (?, 1, 1, 2, ?, ?)', 'cs', 'Čeština', 'cs');

		// 1: překlad v obou jazycích, 2: jen v en, 3: bez překladu; výchozí jazyk webu je cs
		$this->db->query('INSERT INTO firecms_tags (id) VALUES (1), (2), (3)');
		$this->db->query('INSERT INTO firecms_tagDescriptions (tagId, languageId, name) VALUES (?, ?, ?)', 1, 'en', 'Dog');
		$this->db->query('INSERT INTO firecms_tagDescriptions (tagId, languageId, name) VALUES (?, ?, ?)', 1, 'cs', 'Pes');
		$this->db->query('INSERT INTO firecms_tagDescriptions (tagId, languageId, name) VALUES (?, ?, ?)', 2, 'en', 'Cat');

		$this->tags = new Tags($this->db, new LanguageService(new Languages($this->db)));
	}

	public function testTitleUsesSelectedAdminLanguage(): void
	{
		Assert::same([1 => 'Dog', 2 => 'Cat', 3 => null], $this->selectWithTitle('en')->order('id')->fetchPairs('id', 'title'));
	}

	public function testTitleFallsBackToDefaultLanguage(): void
	{
		// bez zvoleného jazyka (nebo s neexistujícím) se bere výchozí jazyk webu, u položky 2 jediný překlad
		Assert::same([1 => 'Pes', 2 => 'Cat', 3 => null], $this->selectWithTitle(null)->order('id')->fetchPairs('id', 'title'));
		Assert::same([1 => 'Pes', 2 => 'Cat', 3 => null], $this->selectWithTitle('xx')->order('id')->fetchPairs('id', 'title'));
	}

	public function testSelectionWithTitleCanBeCountedAndSorted(): void
	{
		// datagrid volá count() (select zahodí, podmínky ponechá) a řadí podle aliasu
		Assert::same(3, $this->selectWithTitle()->count('*'));
		Assert::same(['Cat', 'Pes'], array_values(array_filter($this->selectWithTitle()->order('title')->fetchPairs('id', 'title'))));
	}

	public function testTitleSqlCanBeUsedInWhereCondition(): void
	{
		// stejné použití jako Service\Tag::findByName()
		$ids = $this->tags->findAll()
			->where($this->tags->getTitleSql('`firecms_tags`.`id`') . ' LIKE ?', ...[...$this->tags->getTitleParams(), '%at%'])
			->fetchPairs(null, 'id');

		Assert::same([2], $ids);
	}

	/**
	 * @return \Nette\Database\Table\Selection<\Nette\Database\Table\ActiveRow>
	 */
	private function selectWithTitle(?string $language = null): \Nette\Database\Table\Selection
	{
		$selection = $this->tags->findAll()->select('firecms_tags.*');
		return $this->tags->selectTitle($selection, '`firecms_tags`.`id`', $language);
	}
}

(new TranslatedTitleTraitTest())->run();
