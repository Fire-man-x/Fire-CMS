<?php

declare(strict_types=1);

namespace Tests\Model;

use App\Components\Menu\Model\MenuLinkType;
use App\Components\Menu\Model\Menus;
use App\Model\Languages;
use App\Model\Pages;
use App\Modules\UrlModule\Model;
use App\Modules\UrlModule\RedirectionsModel;
use App\Modules\UrlModule\UrlManager;
use App\Service\LanguageService;
use Nette\Database\Explorer;
use Tester\Assert;
use Tester\TestCase;
use Tests\Helpers\SqliteDatabase;

require_once __DIR__ . '/../bootstrap.php';

/**
 * Stránky (structures/20260924100000.sql): překlady, výběr publikovaných, úklid URL a položek menu při smazání.
 */
final class PagesTest extends TestCase
{
	private Explorer $db;
	private Pages $pages;
	private Menus $menus;


	protected function setUp(): void
	{
		$this->db = SqliteDatabase::create([
			'CREATE TABLE firecms_languages (
				languageId TEXT PRIMARY KEY, active INTEGER NOT NULL DEFAULT 1, "default" INTEGER NOT NULL DEFAULT 0,
				position INTEGER NOT NULL, name TEXT NOT NULL, shortcut TEXT NOT NULL
			)',
			'CREATE TABLE firecms_pages (
				id INTEGER PRIMARY KEY AUTOINCREMENT, parentId INTEGER NULL REFERENCES firecms_pages(id) ON DELETE SET NULL,
				createdBy INTEGER NULL, active INTEGER NOT NULL DEFAULT 1,
				status TEXT NOT NULL DEFAULT \'draft\', public INTEGER NOT NULL DEFAULT 1, position INTEGER NOT NULL DEFAULT 0
			)',
			'CREATE TABLE firecms_files (id INTEGER PRIMARY KEY AUTOINCREMENT, originalName TEXT NOT NULL)',
			'CREATE TABLE firecms_pageFiles (
				pageId INTEGER NOT NULL REFERENCES firecms_pages(id) ON DELETE CASCADE,
				fileId INTEGER NOT NULL REFERENCES firecms_files(id) ON DELETE CASCADE,
				position INTEGER NOT NULL DEFAULT 0, PRIMARY KEY (pageId, fileId)
			)',
			'CREATE TABLE firecms_pageDescriptions (
				pageId INTEGER NOT NULL REFERENCES firecms_pages(id) ON DELETE CASCADE, languageId TEXT NOT NULL,
				title TEXT NULL, content TEXT NULL, seoTitle TEXT NULL, seoDescription TEXT NULL, seoKeywords TEXT NULL,
				PRIMARY KEY (pageId, languageId)
			)',
			'CREATE TABLE firecms_urls (
				id INTEGER PRIMARY KEY AUTOINCREMENT, languageId TEXT NOT NULL, type TEXT NOT NULL, "key" INTEGER NOT NULL, url TEXT NOT NULL
			)',
			'CREATE TABLE firecms_redirections (id INTEGER PRIMARY KEY AUTOINCREMENT, languageId TEXT, oldUrl TEXT, newUrl TEXT)',
			'CREATE TABLE firecms_menus (id INTEGER PRIMARY KEY AUTOINCREMENT, active INTEGER NOT NULL DEFAULT 1, location TEXT NOT NULL)',
			'CREATE TABLE firecms_menuItems (
				id INTEGER PRIMARY KEY AUTOINCREMENT, menuId INTEGER NOT NULL, parentId INTEGER NULL REFERENCES firecms_menuItems(id) ON DELETE CASCADE,
				active INTEGER NOT NULL DEFAULT 1, position INTEGER NOT NULL DEFAULT 0, linkType TEXT NOT NULL, target TEXT NOT NULL,
				newWindow INTEGER NOT NULL DEFAULT 0
			)',
			'CREATE TABLE firecms_menuItemDescriptions (menuItemId INTEGER NOT NULL, languageId TEXT NOT NULL, label TEXT NULL, PRIMARY KEY (menuItemId, languageId))',
		]);
		$this->db->query('PRAGMA foreign_keys = ON');
		$this->db->query("INSERT INTO firecms_languages (languageId, active, \"default\", position, name, shortcut) VALUES ('cs', 1, 1, 1, 'Čeština', 'CS'), ('en', 1, 0, 2, 'English', 'EN')");
		$this->db->query("INSERT INTO firecms_menus (location) VALUES ('main-menu')");

		$languages = new LanguageService(new Languages($this->db));
		$this->menus = new Menus($this->db, $languages);
		$urlManager = new UrlManager(new Model($this->db), new RedirectionsModel($this->db), $languages, $this->db);
		$this->pages = new Pages($this->db, $languages, $this->menus, $urlManager);
	}


	/**
	 * BaseModel::insert() používá MySQL LAST_INSERT_ID() - fixture přímo přes query (viz CLAUDE.md)
	 */
	private function addPage(int $id, string $status = 'publish', bool $active = true): void
	{
		$this->db->query('INSERT INTO firecms_pages (id, status, active) VALUES (?, ?, ?)', $id, $status, (int) $active);
	}


	/**
	 * @return list<string> "-" podle úrovně + id@position
	 */
	private function shape(): array
	{
		return array_map(fn(array $page): string => str_repeat('-', $page['level']) . $page['id'] . '@' . $page['position'], $this->pages->getTree('cs'));
	}


	public function testSaveTranslationInsertsThenUpdates(): void
	{
		$this->addPage(1);
		$this->pages->saveTranslation(1, 'cs', ['title' => 'O nás', 'seoTitle' => 'O nás | Firma']);
		$this->pages->saveTranslation(1, 'cs', ['title' => 'O nás a týmu']);

		$translation = $this->pages->getTranslation(1, 'cs');
		Assert::same('O nás a týmu', $translation?->title);
		Assert::same('O nás | Firma', $translation?->seoTitle);
		Assert::null($this->pages->getTranslation(1, 'en'));
	}


	public function testFindPublishedSkipsDraftAndInactive(): void
	{
		$this->addPage(1);
		$this->addPage(2, 'draft');
		$this->addPage(3, 'publish', false);
		foreach ([1, 2, 3] as $id) {
			$this->pages->saveTranslation($id, 'cs', ['title' => "Stránka $id"]);
		}
		$this->pages->saveTranslation(1, 'en', ['title' => 'Page 1']);

		Assert::same([1], array_map(fn($row) => (int) $row->pageId, array_values($this->pages->findPublished('cs')->fetchAll())));
		Assert::same(['cs', 'en'], $this->pages->getPublishedLanguages(1));
		Assert::same([], $this->pages->getPublishedLanguages(2));
	}


	public function testDeleteRemovesUrlsAndMenuItems(): void
	{
		$this->addPage(1);
		$this->addPage(2);
		$this->pages->saveTranslation(1, 'cs', ['title' => 'Kontakt']);
		$this->db->query("INSERT INTO firecms_urls (languageId, type, \"key\", url) VALUES ('cs', 'page', 1, 'kontakt'), ('en', 'page', 1, 'contact'), ('cs', 'page', 2, 'jina'), ('cs', 'category', 1, 'kategorie')");
		$this->menus->insertItem(1, ['linkType' => MenuLinkType::Page->value, 'target' => '1']);
		$this->menus->insertItem(1, ['linkType' => MenuLinkType::Category->value, 'target' => '1']);

		$this->pages->delete(1);

		Assert::null($this->pages->getById(1));
		Assert::null($this->pages->getTranslation(1, 'cs'));
		Assert::same(['jina', 'kategorie'], $this->db->query('SELECT url FROM firecms_urls ORDER BY url')->fetchPairs(null, 'url'));
		Assert::same(['category'], array_map(fn($item) => $item->linkType, $this->menus->getItemsTree(1, 'cs')));
	}


	public function testTreeInsertMoveAndReparent(): void
	{
		$a = $this->pages->insertPage(['status' => 'publish']);
		$b = $this->pages->insertPage(['status' => 'publish']);
		$a1 = $this->pages->insertPage(['status' => 'publish', 'parentId' => $a]);
		$a2 = $this->pages->insertPage(['status' => 'publish', 'parentId' => $a]);
		Assert::same(["$a@1", "-$a1@1", "-$a2@2", "$b@2"], $this->shape());

		$this->pages->movePage($a2, null, $a1); // a2 před a1
		$this->pages->movePage($b, null, $a1); // soused z jiné úrovně - beze změny
		Assert::same(["$a@1", "-$a2@1", "-$a1@2", "$b@2"], $this->shape());

		$this->pages->updatePage($b, ['parentId' => $a2]);
		Assert::same(["$a@1", "-$a2@1", "--$b@1", "-$a1@2"], $this->shape());
		Assert::exception(fn() => $this->pages->updatePage($a, ['parentId' => $b]), \InvalidArgumentException::class);

		// smazání rodiče: podstránky o úroveň výš na konec sourozenců
		$this->pages->delete($a2);
		Assert::same(["$a@1", "-$a1@1", "-$b@2"], $this->shape());
	}


	public function testBreadcrumbParentsAndChildren(): void
	{
		$root = $this->pages->insertPage(['status' => 'publish']);
		$draft = $this->pages->insertPage(['status' => 'draft', 'parentId' => $root]);
		$leaf = $this->pages->insertPage(['status' => 'publish', 'parentId' => $draft]);
		foreach ([$root => 'O nás', $draft => 'Koncept', $leaf => 'Tým'] as $id => $title) {
			$this->pages->saveTranslation($id, 'cs', ['title' => $title]);
		}

		// nepublikovaný předek se přeskočí
		Assert::same([['id' => $root, 'title' => 'O nás']], $this->pages->getPublishedParents($leaf, 'cs'));
		Assert::same([], array_values($this->pages->findPublishedChildren($root, 'cs')->fetchAll())); // jediný potomek je koncept
		Assert::same([$leaf], array_map(fn($row) => (int) $row->pageId, array_values($this->pages->findPublishedChildren($draft, 'cs')->fetchAll())));
	}


	public function testFilesOrderAndMainImage(): void
	{
		$this->addPage(1);
		$this->db->query("INSERT INTO firecms_files (id, originalName) VALUES (10, 'a.jpg'), (11, 'b.jpg'), (12, 'c.jpg')");

		$this->pages->addFiles(1, [10, 11]);
		$this->pages->addFiles(1, [11, 12]); // 11 už je přiřazený
		Assert::same([10, 11, 12], array_map(fn($row) => (int) $row->id, array_values($this->pages->getFiles(1)->fetchAll())));

		$this->pages->sortFiles(1, [12, 10, 11]);
		$this->pages->removeFile(1, 10);
		Assert::same([12, 11], array_map(fn($row) => (int) $row->id, array_values($this->pages->getFiles(1)->fetchAll())));
	}
}

(new PagesTest())->run();
